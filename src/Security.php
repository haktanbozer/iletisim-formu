<?php

namespace App;

/**
 * Güvenlik ve Doğrulama Katmanı
 * (CSRF, Rate Limiting, Honeypot, Güvenli IP, PHP 8.2+ Sanitization)
 */
class Security
{
    /**
     * Güvenli oturum başlatır
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // cPanel paylaşımlı hostinglerde varsayılan /var/cpanel/... yolu eksik olabiliyor.
            // Bu durumda projemizdeki güvenli storage/sessions veya sunucu /tmp dizinine yönlendiriyoruz.
            $customSessionPath = dirname(__DIR__) . '/storage/sessions';
            if (!is_dir($customSessionPath)) {
                @mkdir($customSessionPath, 0755, true);
            }

            $currentSavePath = session_save_path();
            if (empty($currentSavePath) || !@is_dir($currentSavePath) || !@is_writable($currentSavePath)) {
                if (@is_dir($customSessionPath) && @is_writable($customSessionPath)) {
                    session_save_path($customSessionPath);
                } elseif (@is_dir(sys_get_temp_dir()) && @is_writable(sys_get_temp_dir())) {
                    session_save_path(sys_get_temp_dir());
                }
            }

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            @session_start();
        }
    }

    /**
     * CSRF Token oluşturur veya mevcut olanı döndürür
     */
    public static function getCsrfToken(): string
    {
        self::initSession();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * CSRF Token'ı doğrular
     */
    public static function validateCsrfToken(?string $token): bool
    {
        self::initSession();

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Honeypot (bot tuzağı) alanını kontrol eder.
     * Alan doluysa istek bir bot tarafından yapılmıştır.
     */
    public static function validateHoneypot(array $postData, string $field = 'website'): bool
    {
        return empty($postData[$field]);
    }

    /**
     * Güvenli istemci IP adresini tespit eder (Spoofing önlemi)
     */
    public static function getClientIp(): string
    {
        // Cloudflare arkasında çalışıyorsa CF başlığını kontrol et
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $cfIp = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($cfIp, FILTER_VALIDATE_IP)) {
                return $cfIp;
            }
        }

        // Güvenilir proxy yoksa en güvenli kaynak doğrudan bağlantıyı yapan REMOTE_ADDR'dir
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $remoteIp = trim($_SERVER['REMOTE_ADDR']);
            if (filter_var($remoteIp, FILTER_VALIDATE_IP)) {
                return $remoteIp;
            }
        }

        return '127.0.0.1';
    }

    /**
     * Rate Limiting (İstek Sınırlandırma):
     * Belirli bir IP'nin belirli süre içinde maksimum istek sayısını aşmasını engeller.
     *
     * @param string $ip İstemci IP adresi
     * @param int $maxAttempts İzin verilen maksimum istek (varsayılan: 5 dakikada 4 istek)
     * @param int $decaySeconds Süre aşımı (saniye, varsayılan: 300sn = 5dk)
     * @return array ['allowed' => bool, 'retry_after' => int]
     */
    public static function checkRateLimit(string $ip, int $maxAttempts = 4, int $decaySeconds = 300): array
    {
        $cacheDir = dirname(__DIR__) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $rateFile = $cacheDir . '/rate_limits.json';
        $now = time();
        $data = [];

        if (file_exists($rateFile)) {
            $raw = @file_get_contents($rateFile);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        // Süresi dolmuş kayıtları temizle
        foreach ($data as $key => $timestamps) {
            $data[$key] = array_filter($timestamps, function ($ts) use ($now, $decaySeconds) {
                return ($now - $ts) < $decaySeconds;
            });
            if (empty($data[$key])) {
                unset($data[$key]);
            }
        }

        $ipKey = md5($ip);
        $attempts = $data[$ipKey] ?? [];

        if (count($attempts) >= $maxAttempts) {
            $oldestAttempt = min($attempts);
            $retryAfter = $decaySeconds - ($now - $oldestAttempt);
            return [
                'allowed' => false,
                'retry_after' => max(1, $retryAfter)
            ];
        }

        // Yeni denemeyi kaydet
        $attempts[] = $now;
        $data[$ipKey] = $attempts;

        @file_put_contents($rateFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);

        return [
            'allowed' => true,
            'retry_after' => 0
        ];
    }

    /**
     * PHP 8.2+ uyumlu metin temizleme (FILTER_SANITIZE_STRING yerine)
     */
    public static function sanitizeString(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // Görünmeyen kontrol karakterlerini ve HTML etiketlerini kaldır
        $cleaned = strip_tags($value);
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned);

        return trim($cleaned);
    }

    /**
     * Cloudflare Turnstile token doğrulaması (Opsiyonel)
     */
    public static function verifyTurnstile(?string $turnstileToken, string $secretKey, string $ip): bool
    {
        if (empty($secretKey)) {
            return true; // Secret tanımlı değilse adımı atla
        }

        if (empty($turnstileToken)) {
            return false;
        }

        $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $postData = http_build_query([
            'secret' => $secretKey,
            'response' => $turnstileToken,
            'remoteip' => $ip
        ]);

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($postData) . "\r\n",
                'content' => $postData,
                'timeout' => 5
            ]
        ];

        $context  = stream_context_create($options);
        $response = @file_get_contents($verifyUrl, false, $context);

        if ($response === false) {
            return false;
        }

        $result = json_decode($response, true);
        return !empty($result['success']);
    }

    /**
     * Geçici / Kullan-at (Disposable) e-posta adreslerini kontrol eder
     */
    public static function isDisposableEmail(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, "@") ?: '', 1));
        if (empty($domain)) {
            return false;
        }

        // Popüler geçici / sahte e-posta alan adları listesi
        $disposableDomains = [
            'tempmail.com', 'temp-mail.org', '10minutemail.com', '10minutemail.net',
            'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org',
            'throwawaymail.com', 'yopmail.com', 'yopmail.net', 'yopmail.fr',
            'trashmail.com', 'trashmail.net', 'trashmail.me', 'sharklasers.com',
            'dispostable.com', 'getairmail.com', 'mohmal.com', 'mohmal.im',
            'fakeinbox.com', 'generator.email', 'mytemp.email', 'crazymailing.com',
            'minuteinbox.com', 'burnermail.io', 'maildrop.cc', 'tempail.com',
            'nada.ltd', 'inboxkitten.com', 'getnada.com', 'emailondeck.com',
            'tempmailaddress.com', 'harakirimail.com', 'fakemailgenerator.com'
        ];

        return in_array($domain, $disposableDomains, true);
    }

    /**
     * E-posta alan adına ait geçerli bir MX veya A DNS kaydı olup olmadığını denetler
     */
    public static function hasValidMxRecord(string $email): bool
    {
        $domain = substr(strrchr($email, "@") ?: '', 1);
        if (empty($domain)) {
            return false;
        }

        // DNS sorgu fonksiyonu mevcutsa kontrol et
        if (function_exists('checkdnsrr')) {
            // Önce MX kaydı ara, bazı küçük domainler için A kaydı da geçerli olabilir
            return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
        }

        return true; // Fonksiyon yoksa engelleme yapma
    }
}

