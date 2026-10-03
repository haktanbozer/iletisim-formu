<?php

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * İletişim Formu İşleyicisi
 * Doğrulama, E-posta Gönderimi, Güvenli Yedekleme ve Yanıt Yönetimi
 */
class ContactForm
{
    private array $errors = [];
    private string $successMessage = '';

    public function __construct()
    {
        // PHPMailer kütüphanesini dahil et (Composer veya yerel klasör)
        $this->loadMailer();
    }

    /**
     * PHPMailer sınıflarını yükler
     */
    private function loadMailer(): void
    {
        $baseDir = dirname(__DIR__);

        if (file_exists($baseDir . '/vendor/autoload.php')) {
            require_once $baseDir . '/vendor/autoload.php';
        } else {
            require_once $baseDir . '/PHPMailer/Exception.php';
            require_once $baseDir . '/PHPMailer/PHPMailer.php';
            require_once $baseDir . '/PHPMailer/SMTP.php';
        }
    }

    /**
     * Form isteğini işler
     *
     * @param array $input $_POST veya JSON verisi
     * @return array ['success' => bool, 'errors' => array, 'message' => string]
     */
    public function handle(array $input): array
    {
        $this->errors = [];
        $this->successMessage = '';

        $userIp = Security::getClientIp();

        // 1. Honeypot Kontrolü (Bot Tuzağı)
        if (!Security::validateHoneypot($input, 'website')) {
            // Sessizce başarılı gibi yanıt vererek botu yanılt
            return [
                'success' => true,
                'errors' => [],
                'message' => 'Mesajınız başarıyla iletildi.'
            ];
        }

        // 2. CSRF Token Doğrulaması
        $csrfToken = $input['csrf_token'] ?? '';
        if (!Security::validateCsrfToken($csrfToken)) {
            $this->errors[] = 'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
            return $this->response(false);
        }

        // 3. Rate Limiting Kontrolü (Dakikada aşırı istek engelleme)
        $rateCheck = Security::checkRateLimit($userIp, 4, 300);
        if (!$rateCheck['allowed']) {
            $dakika = ceil($rateCheck['retry_after'] / 60);
            $this->errors[] = "Çok fazla istek gönderdiniz. Lütfen {$dakika} dakika sonra tekrar deneyin.";
            return $this->response(false);
        }

        // 4. Cloudflare Turnstile Kontrolü (Eğer aktifse)
        $turnstileSecret = Config::get('TURNSTILE_SECRET_KEY', '');
        if (!empty($turnstileSecret)) {
            $turnstileResponse = $input['cf-turnstile-response'] ?? '';
            if (!Security::verifyTurnstile($turnstileResponse, $turnstileSecret, $userIp)) {
                $this->errors[] = 'Güvenlik doğrulaması (Captcha) başarısız oldu. Lütfen tekrar deneyin.';
                return $this->response(false);
            }
        }

        // 5. Alanları Temizle ve Doğrula
        $fullName   = Security::sanitizeString($input['fullName'] ?? '');
        $emailRaw   = trim($input['email'] ?? '');
        $phone      = Security::sanitizeString($input['phone'] ?? '');
        $subject    = Security::sanitizeString($input['subject'] ?? '');
        $message    = Security::sanitizeString($input['message'] ?? '');
        $ipConsent  = !empty($input['ipConsent']);

        $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);

        // Ad Soyad kontrolü
        if (empty($fullName) || mb_strlen($fullName) < 2) {
            $this->errors[] = 'Lütfen ad soyad girin.';
        }

        // E-posta kontrolü
        if (!$email) {
            $this->errors[] = 'Geçerli bir e-posta adresi girin.';
        } elseif (Security::isDisposableEmail($email)) {
            $this->errors[] = 'Geçici (kullan-at) e-posta adresleri kabul edilmemektedir. Lütfen kurumsal veya kişisel geçerli bir e-posta adresi girin.';
        } elseif (!Security::hasValidMxRecord($email)) {
            $this->errors[] = 'Girdiğiniz e-posta alan adına ait aktif bir mail sunucusu (MX kaydı) bulunamadı.';
        }

        // Telefon kontrolü (opsiyonel ama girilmişse kontrol edilir)
        $phoneDigits = preg_replace('/\D+/', '', $phone);
        if ($phoneDigits !== '' && strlen($phoneDigits) >= 4) {
            if (!preg_match('/^\+?\d{1,3}[\s\d\-()]{4,}$/', $phone)) {
                $this->errors[] = 'Geçerli bir telefon numarası girin.';
            }
        }

        // Konu kontrolü
        if (empty($subject) || mb_strlen($subject) < 5) {
            $this->errors[] = 'Konu en az 5 karakter olmalıdır.';
        }

        // Mesaj kontrolü
        if (empty($message) || mb_strlen($message) < 10) {
            $this->errors[] = 'Mesaj en az 10 karakter olmalıdır.';
        }

        // IP onayı
        if (!$ipConsent) {
            $this->errors[] = 'Devam edebilmek için IP adresinizin bu formda kaydedilmesini onaylamalısınız.';
        }

        if (!empty($this->errors)) {
            return $this->response(false);
        }

        // 6. Güvenli Yerel Yedekleme (SMTP arızası olsa bile talep kaybolmasın)
        $this->backupSubmission([
            'timestamp'  => date('Y-m-d H:i:s'),
            'ip'         => $userIp,
            'full_name'  => $fullName,
            'email'      => $email,
            'phone'      => $phone,
            'subject'    => $subject,
            'message'    => $message
        ]);

        // 7. PHPMailer ile SMTP Gönderimi (Yetkiliye ilet)
        $sent = $this->sendEmail([
            'fullName'  => $fullName,
            'email'     => $email,
            'phone'     => $phone,
            'subject'   => $subject,
            'message'   => $message,
            'userIp'    => $userIp
        ]);

        if ($sent) {
            // Kullanıcıya otomatik teyit e-postası (Autoresponder) gönder
            $autoresponderEnabled = Config::get('AUTORESPONDER_ENABLED', 'true');
            if ($autoresponderEnabled === true || strtolower((string)$autoresponderEnabled) === 'true' || $autoresponderEnabled === '1') {
                $this->sendAutoresponder([
                    'fullName' => $fullName,
                    'email'    => $email,
                    'subject'  => $subject,
                    'message'  => $message
                ]);
            }

            $this->successMessage = 'Mesajınız başarıyla gönderildi. En kısa sürede sizinle iletişime geçeceğiz.';
            return $this->response(true);
        }

        return $this->response(false);
    }

    /**
     * PHPMailer SMTP gönderimini gerçekleştirir
     */
    private function sendEmail(array $data): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = Config::get('SMTP_HOST', '');
            $mail->SMTPAuth   = true;
            $mail->Username   = Config::get('SMTP_USER', '');
            $mail->Password   = Config::get('SMTP_PASS', '');

            $secure = strtolower(Config::get('SMTP_SECURE', 'tls'));
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = (int)Config::get('SMTP_PORT', 465);
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int)Config::get('SMTP_PORT', 587);
            }

            $mail->CharSet = 'UTF-8';

            $fromEmail = Config::get('MAIL_FROM', Config::get('SMTP_USER', ''));
            $fromName  = Config::get('MAIL_FROM_NAME', 'Web İletişim Formu');
            $toEmail   = Config::get('MAIL_TO', $fromEmail);
            $toName    = Config::get('MAIL_TO_NAME', 'Site Yetkilisi');

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->addReplyTo($data['email'], $data['fullName']);

            $mail->Subject = 'Web İletişim Formu: ' . $data['subject'];

            // HTML E-posta ve Düz Metin Alternatifi
            $mail->isHTML(true);
            $mail->Body    = $this->buildHtmlTemplate($data);
            $mail->AltBody = $this->buildPlainTextTemplate($data);

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Bilgi sızıntısını engelle: Teknik detayı hata loguna yaz, kullanıcıya genel mesaj ver
            $this->logError('Mail gönderim hatası: ' . $mail->ErrorInfo . ' | Exception: ' . $e->getMessage());
            $this->errors[] = 'Mesajınız gönderilirken bir sunucu hatası oluştu. Lütfen biraz sonra tekrar deneyin.';
            return false;
        }
    }

    /**
     * Modern HTML E-posta Şablonu Üretir
     */
    private function buildHtmlTemplate(array $d): string
    {
        $safeName    = htmlspecialchars($d['fullName'], ENT_QUOTES, 'UTF-8');
        $safeEmail   = htmlspecialchars($d['email'], ENT_QUOTES, 'UTF-8');
        $safePhone   = htmlspecialchars($d['phone'] ?: 'Belirtilmedi', ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($d['subject'], ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($d['message'], ENT_QUOTES, 'UTF-8'));
        $safeIp      = htmlspecialchars($d['userIp'], ENT_QUOTES, 'UTF-8');
        $dateStr     = date('d.m.Y H:i:s');

        return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6; margin: 0; padding: 20px; color: #1f2937; }
  .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; }
  .header { background: linear-gradient(135deg, #0284c7, #4f46e5); color: #ffffff; padding: 24px; text-align: left; }
  .header h2 { margin: 0; font-size: 20px; font-weight: 600; }
  .content { padding: 24px; }
  .field-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  .field-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
  .field-label { width: 130px; font-weight: 600; color: #4b5563; background: #f9fafb; }
  .field-val { color: #111827; }
  .msg-box { background: #f8fafc; border-left: 4px solid #0284c7; padding: 14px 16px; border-radius: 4px; font-size: 14px; line-height: 1.6; margin-top: 8px; color: #334155; }
  .footer { background: #f9fafb; padding: 14px 24px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h2>Yeni Web İletişim Formu Mesajı</h2>
  </div>
  <div class="content">
    <table class="field-table">
      <tr><td class="field-label">Gönderen:</td><td class="field-val"><strong>{$safeName}</strong></td></tr>
      <tr><td class="field-label">E-posta:</td><td class="field-val"><a href="mailto:{$safeEmail}">{$safeEmail}</a></td></tr>
      <tr><td class="field-label">Telefon:</td><td class="field-val">{$safePhone}</td></tr>
      <tr><td class="field-label">Konu:</td><td class="field-val">{$safeSubject}</td></tr>
      <tr><td class="field-label">Tarih:</td><td class="field-val">{$dateStr}</td></tr>
      <tr><td class="field-label">IP Adresi:</td><td class="field-val"><code>{$safeIp}</code></td></tr>
    </table>
    <div style="font-weight: 600; font-size: 14px; color: #374151; margin-top: 16px;">Mesaj İçeriği:</div>
    <div class="msg-box">{$safeMessage}</div>
  </div>
  <div class="footer">
    <span>Bu e-posta web sitenizdeki iletişim formundan otomatik oluşturulmuştur.</span>
  </div>
</div>
</body>
</html>
HTML;
    }

    /**
     * Düz Metin Alternatif Şablonu
     */
    private function buildPlainTextTemplate(array $d): string
    {
        $phone = $d['phone'] ?: 'Belirtilmedi';
        return "YENİ İLETİŞİM FORMU MESAJI\n"
             . "===========================\n"
             . "Tarih    : " . date('d.m.Y H:i:s') . "\n"
             . "Ad Soyad : {$d['fullName']}\n"
             . "E-posta  : {$d['email']}\n"
             . "Telefon  : {$phone}\n"
             . "Konu     : {$d['subject']}\n"
             . "IP Adresi: {$d['userIp']}\n\n"
             . "MESAJ:\n{$d['message']}\n\n"
             . "---------------------------\n"
             . "Bu mesaj web sitesi iletişim formundan otomatik gönderilmiştir.";
    }

    /**
     * Gelen formu güvenli JSON dosyasına yedekler
     */
    private function backupSubmission(array $data): void
    {
        $logDir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/contacts.log';
        $logEntry = json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Hata kaydını tutar
     */
    private function logError(string $msg): void
    {
        $logDir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/error.log';
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Yanıt yapısını oluşturur
     */
    private function response(bool $success): array
    {
        return [
            'success' => $success,
            'errors'  => $this->errors,
            'message' => $success ? $this->successMessage : ''
        ];
    }

    /**
     * Müşteriye otomatik teyit e-postası (Autoresponder) gönderir
     */
    private function sendAutoresponder(array $data): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = Config::get('SMTP_HOST', '');
            $mail->SMTPAuth   = true;
            $mail->Username   = Config::get('SMTP_USER', '');
            $mail->Password   = Config::get('SMTP_PASS', '');

            $secure = strtolower(Config::get('SMTP_SECURE', 'tls'));
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = (int)Config::get('SMTP_PORT', 465);
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int)Config::get('SMTP_PORT', 587);
            }

            $mail->CharSet = 'UTF-8';

            $fromEmail  = Config::get('MAIL_FROM', Config::get('SMTP_USER', ''));
            $fromName   = Config::get('MAIL_FROM_NAME', 'Web İletişim Formu');
            $adminEmail = Config::get('MAIL_TO', $fromEmail);

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($data['email'], $data['fullName']);
            // Kullanıcı bu maile cevap yazarsa doğrudan site yetkilisine ulaşsın
            $mail->addReplyTo($adminEmail, $fromName);

            // E-posta döngülerini ve sonsuz zincirleri önlemek için standart başlıklar
            $mail->addCustomHeader('Auto-Submitted', 'auto-replied');
            $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');

            $mail->Subject = 'Mesajınız Alındı: ' . $data['subject'];

            $mail->isHTML(true);
            $mail->Body    = $this->buildAutoresponderHtmlTemplate($data);
            $mail->AltBody = $this->buildAutoresponderPlainTextTemplate($data);

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Ana gönderim başarılı olduğu için autoresponder hatası kullanıcıya yansıtılmaz
            $this->logError('Otomatik yanıt e-postası gönderilemedi: ' . $mail->ErrorInfo . ' | Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Müşteri Teyit E-postası HTML Şablonu
     */
    private function buildAutoresponderHtmlTemplate(array $d): string
    {
        $safeName    = htmlspecialchars($d['fullName'], ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($d['subject'], ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($d['message'], ENT_QUOTES, 'UTF-8'));
        $dateStr     = date('d.m.Y H:i');
        $siteTitle   = htmlspecialchars(Config::get('MAIL_FROM_NAME', 'Web İletişim Formu'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px 12px; color: #1e293b; }
  .card { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
  .header { background: linear-gradient(135deg, #0284c7, #3b82f6); color: #ffffff; padding: 26px 24px; text-align: center; }
  .header h1 { margin: 0; font-size: 21px; font-weight: 700; letter-spacing: -0.02em; }
  .content { padding: 28px 24px; line-height: 1.65; font-size: 15px; color: #334155; }
  .greeting { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 12px; }
  .highlight-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 18px; margin: 20px 0; color: #166534; font-size: 14px; }
  .summary-card { background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; padding: 16px; margin: 18px 0; font-size: 14px; }
  .summary-title { font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; }
  .summary-msg { color: #64748b; font-style: italic; margin-top: 6px; }
  .footer { background: #f8fafc; padding: 16px 24px; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; text-align: center; line-height: 1.5; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>Mesajınız Bize Ulaştı ✓</h1>
  </div>
  <div class="content">
    <div class="greeting">Sayın {$safeName},</div>
    <p>Web sitemiz üzerinden bizimle iletişime geçtiğiniz için teşekkür ederiz. İletişim talebiniz başarıyla kaydedilmiştir.</p>
    
    <div class="highlight-box">
      <strong>Bilgilendirme:</strong><br>
      Ekibimiz mesajınızı inceleyerek mesai saatleri içerisinde en kısa sürede sizinle iletişime geçecektir.
    </div>

    <div class="summary-card">
      <div class="summary-title">İlettiğiniz Talep Detayları:</div>
      <div><strong>Konu:</strong> {$safeSubject}</div>
      <div><strong>Tarih:</strong> {$dateStr}</div>
      <div class="summary-msg">"{$safeMessage}"</div>
    </div>

    <p style="margin-bottom: 0;">Saygılarımızla,<br><strong>{$siteTitle}</strong></p>
  </div>
  <div class="footer">
    Bu e-posta web sitesi iletişim formundan otomatik teyit amacıyla gönderilmiştir.<br>
    Ek bilgi iletmek isterseniz bu e-postayı doğrudan yanıtlayabilirsiniz.
  </div>
</div>
</body>
</html>
HTML;
    }

    /**
     * Müşteri Teyit E-postası Düz Metin Şablonu
     */
    private function buildAutoresponderPlainTextTemplate(array $d): string
    {
        $siteTitle = Config::get('MAIL_FROM_NAME', 'Web İletişim Formu');
        return "Sayın {$d['fullName']},\n\n"
             . "Web sitemiz üzerinden bizimle iletişime geçtiğiniz için teşekkür ederiz. İletişim talebiniz başarıyla tarafımıza ulaşmıştır.\n\n"
             . "Talebiniz mesai saatleri içerisinde ekibimiz tarafından incelenerek en kısa sürede sizinle iletişime geçilecektir.\n\n"
             . "TALEP ÖZETİ:\n"
             . "---------------------------\n"
             . "Konu : {$d['subject']}\n"
             . "Tarih: " . date('d.m.Y H:i') . "\n"
             . "Mesaj: {$d['message']}\n"
             . "---------------------------\n\n"
             . "Saygılarımızla,\n"
             . "{$siteTitle}\n\n"
             . "* Bu e-posta otomatik bir teyit mesajıdır.";
    }
}

