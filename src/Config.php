<?php

namespace App;

/**
 * Konfigürasyon Yöneticisi (.env ve ortam değişkenleri)
 */
class Config
{
    private static ?array $data = null;

    /**
     * .env dosyasını ve ortam değişkenlerini yükler
     */
    public static function load(string $envPath = ''): void
    {
        if (self::$data !== null) {
            return;
        }

        self::$data = [];

        if (empty($envPath)) {
            $envPath = dirname(__DIR__) . '/.env';
        }

        if (file_exists($envPath) && is_readable($envPath)) {
            $parsed = parse_ini_file($envPath, false, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                self::$data = $parsed;
            }
        }
    }

    /**
     * Anahtara karşılık gelen değeri döndürür
     */
    public static function get(string $key, $default = null)
    {
        if (self::$data === null) {
            self::load();
        }

        // Önce getenv / $_ENV kontrolü
        $envVal = getenv($key);
        if ($envVal !== false) {
            return $envVal;
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        return self::$data[$key] ?? $default;
    }
}
