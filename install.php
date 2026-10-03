<?php
/**
 * install.php - Turnkey Web Kurulum Sihirbazı (Zero-Config Installer)
 * Web İletişim Formu için Tek Tıkla Otomatik Kurulum ve SMTP Doğrulama
 * 
 * Güvenlik: Kurulum tamamlandığında installed.lock dosyası oluşturulur ve yeniden çalıştırma kilitlenir.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$lockFile = __DIR__ . '/installed.lock';
$envFile  = __DIR__ . '/.env';

// 1. Kurulum zaten yapılmışsa sihirbazı kilitle
if (file_exists($lockFile)) {
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sistem Zaten Kurulu — İletişim Formu</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 antialiased">
        <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center">
            <div class="w-16 h-16 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-5 shadow-inner">
                🔒
            </div>
            <h1 class="text-2xl font-bold mb-2 tracking-tight">Sistem Zaten Kurulu!</h1>
            <p class="text-slate-400 text-sm mb-6 leading-relaxed">
                Güvenliğiniz için kurulum sihirbazı kilitlenmiştir. Yeniden kurulum yapmak istiyorsanız ana dizindeki <code class="text-amber-300 bg-slate-800 px-2 py-1 rounded text-xs">installed.lock</code> dosyasını silmeniz gerekir.
            </p>
            <div class="space-y-3">
                <a href="index.php" class="block w-full py-3.5 bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white rounded-xl font-semibold text-sm transition shadow-lg shadow-sky-500/20">
                    İletişim Formunu Aç →
                </a>
                <p class="text-xs text-rose-400/90 font-medium pt-2">
                    ⚠️ Güvenliğiniz için <code class="bg-rose-950/40 text-rose-300 px-1.5 py-0.5 rounded">install.php</code> dosyasını sunucunuzdan silebilirsiniz.
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 2. Sistem Gereksinimlerini Denetle
$requirements = [
    'php_version' => [
        'name'    => 'PHP Sürümü (>= 7.4)',
        'pass'    => version_compare(PHP_VERSION, '7.4.0', '>='),
        'current' => PHP_VERSION,
        'info'    => 'Modern PHP özellikleri ve güvenlik katmanları için gereklidir.'
    ],
    'openssl' => [
        'name'    => 'OpenSSL Eklentisi',
        'pass'    => extension_loaded('openssl'),
        'current' => extension_loaded('openssl') ? 'Yüklü' : 'Eksik',
        'info'    => 'TLS/SSL güvenli SMTP e-posta bağlantısı için zorunludur.'
    ],
    'mbstring' => [
        'name'    => 'Mbstring Eklentisi',
        'pass'    => extension_loaded('mbstring'),
        'current' => extension_loaded('mbstring') ? 'Yüklü' : 'Eksik',
        'info'    => 'Türkçe karakterlerin ve çoklu dil yapısının doğru işlenmesi için gereklidir.'
    ],
    'json' => [
        'name'    => 'JSON Eklentisi',
        'pass'    => extension_loaded('json'),
        'current' => extension_loaded('json') ? 'Yüklü' : 'Eksik',
        'info'    => 'AJAX yanıtları ve yerel JSON log yedeklemesi için gereklidir.'
    ],
    'session' => [
        'name'    => 'Session (Oturum) Desteği',
        'pass'    => extension_loaded('session'),
        'current' => extension_loaded('session') ? 'Yüklü' : 'Eksik',
        'info'    => 'Anti-CSRF güvenlik belirteçlerinin tutulması için gereklidir.'
    ],
    'storage' => [
        'name'    => 'Storage Klasörü Yazma İzni',
        'pass'    => false,
        'current' => 'Kontrol ediliyor',
        'info'    => 'Loglar, oturumlar ve rate limit önbelleği için yazılabilir olmalıdır.'
    ]
];

// Storage dizinini hazırla ve yazma iznini test et
$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0755, true);
}
$isStorageWritable = is_dir($storageDir) && is_writable($storageDir);
$requirements['storage']['pass'] = $isStorageWritable;
$requirements['storage']['current'] = $isStorageWritable ? 'Yazılabilir (OK)' : 'Yazılamıyor (chmod 755 veriniz)';

$allRequirementsPassed = !in_array(false, array_column($requirements, 'pass'), true);

$errorMsg   = '';
$successMsg = '';
$step       = 'form';

// 3. Form Gönderildiğinde Kurulumu İşle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kurulum_yap'])) {
    if (!$allRequirementsPassed) {
        $errorMsg = 'Lütfen kuruluma devam etmeden önce eksik sistem gereksinimlerini gideriniz.';
    } else {
        // Form girdilerini al
        $smtpHost   = trim($_POST['smtp_host'] ?? '');
        $smtpPort   = (int)($_POST['smtp_port'] ?? 587);
        $smtpUser   = trim($_POST['smtp_user'] ?? '');
        $smtpPass   = trim($_POST['smtp_pass'] ?? '');
        $smtpSecure = strtolower(trim($_POST['smtp_secure'] ?? 'tls'));

        $mailFrom     = trim($_POST['mail_from'] ?? '');
        if (empty($mailFrom)) $mailFrom = $smtpUser;
        $mailFromName = trim($_POST['mail_from_name'] ?? 'Web İletişim Formu');
        if (empty($mailFromName)) $mailFromName = 'Web İletişim Formu';

        $mailTo     = trim($_POST['mail_to'] ?? '');
        $mailToName = trim($_POST['mail_to_name'] ?? 'Site Yetkilisi');
        if (empty($mailToName)) $mailToName = 'Site Yetkilisi';

        $autoresponder = isset($_POST['autoresponder_enabled']) ? 'true' : 'false';

        $turnstileSiteKey   = trim($_POST['turnstile_site_key'] ?? '');
        $turnstileSecretKey = trim($_POST['turnstile_secret_key'] ?? '');
        $testEmailGonder    = isset($_POST['test_email_gonder']);

        if (empty($smtpHost)) {
            $errorMsg = 'Lütfen SMTP Sunucu Adresini (Host) giriniz.';
        } elseif (empty($smtpUser)) {
            $errorMsg = 'Lütfen SMTP Kullanıcı Adını (E-posta) giriniz.';
        } elseif (empty($smtpPass)) {
            $errorMsg = 'Lütfen SMTP Parolasını giriniz.';
        } elseif (empty($mailTo) || !filter_var($mailTo, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Lütfen bildirimlerin gideceği geçerli bir Yetkili E-posta Adresi giriniz.';
        } else {
            // PHPMailer'ı dahil et
            if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                require_once __DIR__ . '/vendor/autoload.php';
            } else {
                require_once __DIR__ . '/PHPMailer/Exception.php';
                require_once __DIR__ . '/PHPMailer/PHPMailer.php';
                require_once __DIR__ . '/PHPMailer/SMTP.php';
            }

            // Test E-postası gönderilecekse veya bağlantı test edilecekse
            $testSuccess = true;
            if ($testEmailGonder) {
                try {
                    $testMail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $testMail->isSMTP();
                    $testMail->Host     = $smtpHost;
                    $testMail->SMTPAuth = true;
                    $testMail->Username = $smtpUser;
                    $testMail->Password = $smtpPass;
                    $testMail->CharSet  = 'UTF-8';

                    if ($smtpSecure === 'ssl') {
                        $testMail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                        $testMail->Port       = $smtpPort ?: 465;
                    } else {
                        $testMail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $testMail->Port       = $smtpPort ?: 587;
                    }

                    $testMail->setFrom($mailFrom, $mailFromName);
                    $testMail->addAddress($mailTo, $mailToName);
                    $testMail->Subject = '🎉 Kurulum Testi: İletişim Formunuz Başarıyla Kuruldu!';
                    $testMail->isHTML(true);
                    $testMail->Body    = "<div style='font-family:sans-serif;padding:20px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;'><h2 style='color:#0284c7;margin-top:0;'>Tebrikler! Kurulum Başarılı 🚀</h2><p>Bu test e-postası, <strong>install.php</strong> kurulum sihirbazı tarafından SMTP ayarlarınızın doğrulanması amacıyla gönderilmiştir.</p><p style='color:#64748b;font-size:13px;'>Tarih: " . date('d.m.Y H:i:s') . "</p></div>";
                    $testMail->AltBody = "Tebrikler! Kurulum Başarılı. Bu test e-postası SMTP ayarlarınızın çalıştığını doğrular.";

                    $testMail->send();
                } catch (\Exception $e) {
                    $testSuccess = false;
                    $errorMsg = 'SMTP Bağlantı Hatası: ' . $testMail->ErrorInfo . ' (Lütfen port, şifre ve SSL/TLS ayarlarınızı kontrol ediniz.)';
                }
            }

            if ($testSuccess) {
                // 1. .env Dosyasını Oluştur
                $envContent = "# ==========================================================================\n"
                            . "# Web İletişim Formu - Konfigürasyon Dosyası\n"
                            . "# Otomatik Kurulum Sihirbazı (install.php) Tarafından Oluşturuldu: " . date('Y-m-d H:i:s') . "\n"
                            . "# ==========================================================================\n\n"
                            . "SMTP_HOST={$smtpHost}\n"
                            . "SMTP_PORT={$smtpPort}\n"
                            . "SMTP_USER={$smtpUser}\n"
                            . "SMTP_PASS={$smtpPass}\n"
                            . "SMTP_SECURE={$smtpSecure}\n\n"
                            . "MAIL_FROM={$mailFrom}\n"
                            . "MAIL_FROM_NAME=\"{$mailFromName}\"\n\n"
                            . "MAIL_TO={$mailTo}\n"
                            . "MAIL_TO_NAME=\"{$mailToName}\"\n\n"
                            . "# Otomatik Müşteri Teyit E-postası (Autoresponder)\n"
                            . "AUTORESPONDER_ENABLED={$autoresponder}\n\n"
                            . "# Cloudflare Turnstile Bot Koruması (Opsiyonel)\n"
                            . "TURNSTILE_SITE_KEY={$turnstileSiteKey}\n"
                            . "TURNSTILE_SECRET_KEY={$turnstileSecretKey}\n";

                @file_put_contents($envFile, $envContent, LOCK_EX);

                // 2. Storage Alt Dizinlerini Hazırla
                $subDirs = ['logs', 'cache', 'sessions'];
                foreach ($subDirs as $sd) {
                    $p = $storageDir . '/' . $sd;
                    if (!is_dir($p)) {
                        @mkdir($p, 0755, true);
                    }
                    @file_put_contents($p . '/.gitkeep', "# keep directory\n");
                }

                // 3. storage/.htaccess dosyasını oluştur (erişim engelleme)
                @file_put_contents($storageDir . '/.htaccess', "# Storage klasörüne doğrudan web erişimini tamamen engelle\nRequire all denied\n");

                // 4. installed.lock Dosyasını Oluşturarak Sihirbazı Kilitle
                $lockContent = "Kurulum Tarihi: " . date('Y-m-d H:i:s') . "\n"
                             . "Kurulum IP: " . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . "\n"
                             . "Yetkili E-posta: " . $mailTo . "\n";
                @file_put_contents($lockFile, $lockContent, LOCK_EX);

                $step = 'success';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web İletişim Formu — Kurulum Sihirbazı</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 sm:p-6 antialiased selection:bg-sky-500 selection:text-white">

    <div class="max-w-2xl w-full my-8">

        <!-- Logo & Başlık -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white text-3xl shadow-lg shadow-sky-500/25 mb-4">
                ✉️
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                Web İletişim Formu Kurulumu
            </h1>
            <p class="text-slate-400 text-sm mt-2 max-w-md mx-auto">
                Turnkey Web Kurulum Sihirbazı ile SMTP ve form ayarlarınızı tek seferde yapılandırın.
            </p>
        </div>

        <?php if ($step === 'success'): ?>
            <!-- BAŞARI EKRANI -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl text-center animate-fade-in">
                <div class="w-20 h-20 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-3xl flex items-center justify-center text-4xl mx-auto mb-6 shadow-inner">
                    ✓
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">Kurulum Başarıyla Tamamlandı!</h2>
                <p class="text-slate-400 text-sm mb-6 max-w-lg mx-auto leading-relaxed">
                    Tüm ayarlarınız başarıyla <code class="text-sky-400 bg-slate-800 px-2 py-1 rounded text-xs">.env</code> dosyasına yazıldı, <code class="text-emerald-400 bg-slate-800 px-2 py-1 rounded text-xs">storage</code> klasörleri hazırlandı ve sistem güvenliği için <code class="text-amber-400 bg-slate-800 px-2 py-1 rounded text-xs">installed.lock</code> oluşturuldu.
                </p>

                <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-5 mb-8 text-left space-y-2 text-xs sm:text-sm">
                    <div class="flex justify-between items-center text-slate-300">
                        <span class="text-slate-400">SMTP Sunucusu:</span>
                        <span class="font-mono font-medium text-sky-400"><?php echo htmlspecialchars($smtpHost); ?>:<?php echo htmlspecialchars((string)$smtpPort); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-slate-300">
                        <span class="text-slate-400">Bildirim Alıcısı:</span>
                        <span class="font-mono font-medium text-slate-200"><?php echo htmlspecialchars($mailTo); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-slate-300">
                        <span class="text-slate-400">Otomatik Teyit (Autoresponder):</span>
                        <span class="font-medium text-emerald-400"><?php echo $autoresponder === 'true' ? 'Aktif ✓' : 'Kapalı'; ?></span>
                    </div>
                </div>

                <div class="space-y-4">
                    <a href="index.php" class="inline-flex items-center justify-center w-full py-4 px-6 bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white rounded-2xl font-bold text-sm sm:text-base transition duration-200 shadow-xl shadow-sky-500/25">
                        İletişim Formuna Git →
                    </a>
                    <p class="text-xs text-rose-400/90 font-medium">
                        🛡️ Güvenliğiniz için <code class="bg-rose-950/40 text-rose-300 px-1.5 py-0.5 rounded">install.php</code> dosyasını sunucunuzdan silebilirsiniz.
                    </p>
                </div>
            </div>

        <?php else: ?>

            <!-- HATA BİLDİRİMİ -->
            <?php if (!empty($errorMsg)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-start gap-3">
                    <span class="text-lg">⚠️</span>
                    <div class="flex-1 font-medium leading-relaxed"><?php echo htmlspecialchars($errorMsg); ?></div>
                </div>
            <?php endif; ?>

            <!-- SİSTEM GEREKSİNİMLERİ KARTI -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-xl mb-6 backdrop-blur">
                <h2 class="text-base font-bold text-slate-200 mb-4 flex items-center gap-2">
                    <span>⚙️</span> Sistem Gereksinimleri
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs sm:text-sm">
                    <?php foreach ($requirements as $req): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-800/50 border border-slate-700/50">
                            <div>
                                <div class="font-semibold text-slate-200"><?php echo htmlspecialchars($req['name']); ?></div>
                                <div class="text-[11px] text-slate-400"><?php echo htmlspecialchars($req['current']); ?></div>
                            </div>
                            <div>
                                <?php if ($req['pass']): ?>
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 text-xs font-bold">✓</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-500/20 text-rose-400 text-xs font-bold">✕</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- KURULUM FORMU -->
            <form method="POST" action="" class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">

                <!-- 1. BÖLÜM: SMTP BİLGİLERİ -->
                <div>
                    <h3 class="text-sm font-bold text-sky-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span>📤</span> 1. SMTP Sunucu Ayarları
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">SMTP Sunucu Adresi (Host) *</label>
                            <input type="text" name="smtp_host" required placeholder="mail.siteniz.com" value="<?php echo htmlspecialchars($_POST['smtp_host'] ?? 'mail.bozer.com.tr'); ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">SMTP Port *</label>
                            <input type="number" name="smtp_port" required placeholder="587" value="<?php echo htmlspecialchars((string)($_POST['smtp_port'] ?? 587)); ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Şifreleme (Encryption) *</label>
                            <select name="smtp_secure" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
                                <option value="tls" <?php echo (($_POST['smtp_secure'] ?? '') === 'tls') ? 'selected' : ''; ?>>TLS (Port 587 - Önerilen)</option>
                                <option value="ssl" <?php echo (($_POST['smtp_secure'] ?? '') === 'ssl') ? 'selected' : ''; ?>>SSL (Port 465)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">SMTP Kullanıcı Adı (E-posta) *</label>
                            <input type="email" name="smtp_user" required placeholder="bildirim@siteniz.com" value="<?php echo htmlspecialchars($_POST['smtp_user'] ?? 'bildirim@bozer.com.tr'); ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">SMTP Parolası *</label>
                            <input type="password" name="smtp_pass" required placeholder="••••••••" value="<?php echo htmlspecialchars($_POST['smtp_pass'] ?? ''); ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
                        </div>
                    </div>
                </div>

                <!-- 2. BÖLÜM: BİLDİRİM VE ALICI AYARLARI -->
                <div class="pt-2 border-t border-slate-800/80">
                    <h3 class="text-sm font-bold text-sky-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span>🎯</span> 2. Bildirim & Alıcı Ayarları
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Mesajların Gideceği Yetkili E-posta *</label>
                            <input type="email" name="mail_to" required placeholder="yetkili@siteniz.com" value="<?php echo htmlspecialchars($_POST['mail_to'] ?? 'haktan@bozer.com.tr'); ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Yetkili Adı / Departman</label>
                            <input type="text" name="mail_to_name" placeholder="Site Yetkilisi" value="<?php echo htmlspecialchars($_POST['mail_to_name'] ?? 'Site Yetkilisi'); ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Gönderen E-posta (From)</label>
                            <input type="email" name="mail_from" placeholder="bildirim@siteniz.com" value="<?php echo htmlspecialchars($_POST['mail_from'] ?? ''); ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">Gönderen Başlığı (Firma Adı)</label>
                            <input type="text" name="mail_from_name" placeholder="Web İletişim Formu" value="<?php echo htmlspecialchars($_POST['mail_from_name'] ?? 'Web İletişim Formu'); ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
                        </div>
                    </div>

                    <!-- Otomatik Teyit Checkbox -->
                    <div class="mt-4 p-3.5 rounded-xl bg-slate-950 border border-slate-800/80 flex items-start gap-3">
                        <input type="checkbox" id="autoresponder_enabled" name="autoresponder_enabled" value="1" checked
                            class="mt-1 w-4 h-4 rounded text-sky-500 focus:ring-sky-500 bg-slate-900 border-slate-700 cursor-pointer">
                        <label for="autoresponder_enabled" class="text-xs text-slate-300 cursor-pointer leading-relaxed">
                            <strong class="text-white block font-medium">Müşteriye Otomatik Teyit E-postası (Autoresponder) Gönderilsin</strong>
                            Formu dolduran müşteriye *"Mesajınız Bize Ulaştı ✓"* başlıklı teşekkür ve bilgilendirme e-postası otomatik iletilir.
                        </label>
                    </div>
                </div>

                <!-- 3. BÖLÜM: OPSİYONEL CLOUDFLARE TURNSTILE -->
                <div class="pt-2 border-t border-slate-800/80">
                    <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-2">
                        <span>🛡️</span> 3. Cloudflare Turnstile Bot Koruması <span class="text-[11px] font-normal text-slate-500">(Opsiyonel)</span>
                    </h3>
                    <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                        Boş bırakırsanız sistem yerleşik <strong>Honeypot + CSRF + Rate Limiting</strong> ile tam koruma sağlar.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Turnstile Site Key</label>
                            <input type="text" name="turnstile_site_key" placeholder="0x4AAAAAA..." value="<?php echo htmlspecialchars($_POST['turnstile_site_key'] ?? ''); ?>"
                                class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 placeholder-slate-700 focus:outline-none focus:border-sky-500 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Turnstile Secret Key</label>
                            <input type="password" name="turnstile_secret_key" placeholder="0x4AAAAAA..." value="<?php echo htmlspecialchars($_POST['turnstile_secret_key'] ?? ''); ?>"
                                class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 placeholder-slate-700 focus:outline-none focus:border-sky-500 transition">
                        </div>
                    </div>
                </div>

                <!-- 4. BÖLÜM: CANLI TEST VE GÖNDERİM -->
                <div class="pt-4 border-t border-slate-800 flex flex-col gap-4">
                    <label class="flex items-center gap-3 cursor-pointer text-xs text-slate-300">
                        <input type="checkbox" name="test_email_gonder" value="1" checked class="w-4 h-4 rounded text-sky-500 bg-slate-950 border-slate-800">
                        <span>Kurulum sırasında yetkili e-posta adresine <strong>deneme e-postası göndererek</strong> bağlantıyı doğrula.</span>
                    </label>

                    <button type="submit" name="kurulum_yap" <?php echo !$allRequirementsPassed ? 'disabled' : ''; ?>
                        class="w-full py-4 px-6 bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl font-bold text-sm sm:text-base transition duration-200 shadow-xl shadow-sky-500/25 flex items-center justify-center gap-2">
                        <span>Kurulumu Tamamla ve Kilitle</span>
                        <span>→</span>
                    </button>
                </div>

            </form>

        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center text-xs text-slate-600 mt-8">
            Web İletişim Formu &copy; <?php echo date('Y'); ?> — Turnkey Installer v1.0
        </div>

    </div>

</body>
</html>
