<?php
/**
 * Modern ve Güvenli Web İletişim Formu
 * Giriş Kontrolcüsü (Entrypoint Controller)
 */

declare(strict_types=1);

// Sistem henüz kurulmamışsa otomatik kurulum sihirbazına yönlendir
if (!file_exists(__DIR__ . '/.env') && !file_exists(__DIR__ . '/installed.lock') && file_exists(__DIR__ . '/install.php')) {
    header('Location: install.php');
    exit;
}

// Temel sınıfları dahil et
require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Security.php';
require_once __DIR__ . '/src/ContactForm.php';

use App\Config;
use App\Security;
use App\ContactForm;

// Güvenli oturumu başlat ve CSRF belirteci üret
Security::initSession();
$csrfToken = Security::getCsrfToken();

// Form değişkenleri ve mesajlar
$errors = [];
$successMessage = '';
$fullName = '';
$emailRaw = '';
$phone = '';
$subject = '';
$message = '';

// Turnstile anahtarı (varsa)
$turnstileSiteKey = Config::get('TURNSTILE_SITE_KEY', '');

// Form POST edildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactForm = new ContactForm();
    $result = $contactForm->handle($_POST);

    // İstek AJAX / Fetch ile mi geldi kontrol et
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Klasik POST yöntemi (JavaScript kapalıysa)
    if ($result['success']) {
        $successMessage = $result['message'];
    } else {
        $errors = $result['errors'];
        $fullName = Security::sanitizeString($_POST['fullName'] ?? '');
        $emailRaw = trim($_POST['email'] ?? '');
        $phone    = Security::sanitizeString($_POST['phone'] ?? '');
        $subject  = Security::sanitizeString($_POST['subject'] ?? '');
        $message  = Security::sanitizeString($_POST['message'] ?? '');
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İletişim</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Ana Stil Dosyası -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Uluslararası Telefon Kodu Kütüphanesi -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.css">
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/intlTelInput.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js"></script>

    <?php if (!empty($turnstileSiteKey)): ?>
        <!-- Cloudflare Turnstile Script -->
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <button type="button" class="toggle-btn" id="themeToggle" aria-label="Tema değiştir">
            <span id="themeIcon">🌙</span>
        </button>
        <div>
            <button type="button" class="lang-btn active" data-lang-btn="tr">TR</button>
            <button type="button" class="lang-btn" data-lang-btn="en">EN</button>
        </div>
    </div>

    <h1><span class="title-icon">💬</span><span data-i18n="heading">Bizimle İletişime Geçin</span></h1>
    <div class="subtitle" data-i18n="subtitle">
        Sorularınız, önerileriniz veya proje fikirleriniz için formu doldurmanız yeterli.
    </div>

    <!-- Dinamik ve Statik Bildirim Alanı -->
    <div id="alertContainer">
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <div class="success-view">
                    <div class="success-icon-wrap">
                        <div class="success-icon"></div>
                    </div>
                    <div class="success-title" data-i18n="success_title">Teşekkürler!</div>
                    <div class="success-text" data-i18n="success_text">
                        <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" id="errorBox">
                <strong data-i18n="error_title">Form gönderilirken bazı hatalar oluştu:</strong>
                <ul class="error-list">
                    <?php foreach ($errors as $err): ?>
                        <li class="error-item"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <!-- Taslak Mesaj Bildirimi -->
    <div class="draft-notice" id="draftNotice">
        <span>📝 <span data-i18n="draft_restored">Daha önce yazdığınız taslak mesaj geri yüklendi.</span></span>
        <button type="button" class="draft-clear-btn" id="draftClearBtn" data-i18n="draft_clear">Taslağı Sil</button>
    </div>

    <form method="post" action="" id="contactForm" novalidate>
        <!-- CSRF Güvenlik Belirteci -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <!-- Honeypot Bot Tuzağı (Kullanıcıya görünmez) -->
        <div class="hp-field" aria-hidden="true">
            <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
        </div>

        <div class="field" data-field="fullName">
            <div class="field-header">
                <label for="fullName">
                    <span class="label-icon">👤</span>
                    <span class="label-text" data-i18n="label_fullName">Ad Soyad</span>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <span class="tooltip"
                      data-tooltip="Lütfen tam adınızı ve soyadınızı yazın."
                      data-tooltip-en="Please enter your full legal name.">?</span>
            </div>
            <input
                type="text"
                id="fullName"
                name="fullName"
                autocomplete="name"
                value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Adınızı ve soyadınızı yazın"
                data-i18n-placeholder="ph_fullName"
                required
            >
        </div>

        <div class="field" data-field="email">
            <div class="field-header">
                <label for="email">
                    <span class="label-icon">✉️</span>
                    <span class="label-text" data-i18n="label_email">E-posta Adresi</span>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <span class="tooltip"
                      data-tooltip="Aktif kullandığınız bir e-posta adresi yazın. Örn: ornek@mail.com"
                      data-tooltip-en="Use an active email address, e.g., example@mail.com">?</span>
            </div>
            <input
                type="email"
                id="email"
                name="email"
                autocomplete="email"
                value="<?php echo htmlspecialchars($emailRaw, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="ornek@mail.com"
                data-i18n-placeholder="ph_email"
                required
            >
        </div>

        <div class="field" data-field="phone">
            <div class="field-header">
                <label for="phone">
                    <span class="label-icon">📞</span>
                    <span class="label-text" data-i18n="label_phone">Telefon Numarası</span>
                    <span style="font-size:0.75rem; opacity:0.75;" data-i18n="label_optional">(opsiyonel)</span>
                </label>

                <div style="display:flex; align-items:center; gap:8px;">
                    <span id="countryDisplay" style="font-size:0.78rem; opacity:0.85;">
                        🇹🇷 Türkiye (+90)
                    </span>

                    <span class="tooltip"
                          data-tooltip="+90 5xx xxx xx xx formatında mobil numara yazabilirsiniz."
                          data-tooltip-en="You can enter a mobile number in +90 5xx xxx xx xx format.">?</span>
                </div>
            </div>

            <input
                type="tel"
                id="phone"
                name="phone"
                autocomplete="tel"
                value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="+90 5xx xxx xx xx"
                data-i18n-placeholder="ph_phone"
            >
        </div>

        <div class="field" data-field="subject">
            <div class="field-header">
                <label for="subject">
                    <span class="label-icon">📝</span>
                    <span class="label-text" data-i18n="label_subject">Konu</span>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <span class="tooltip"
                      data-tooltip="Talebinizi birkaç kelimeyle özetleyin. Örn: Web sitesi teklifi"
                      data-tooltip-en="Summarize your inquiry. E.g., Website quotation">?</span>
            </div>
            <input
                type="text"
                id="subject"
                name="subject"
                autocomplete="subject"
                value="<?php echo htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Mesajınızın konusu"
                data-i18n-placeholder="ph_subject"
                required
            >
        </div>

        <div class="field" data-field="message">
            <div class="field-header">
                <label for="message">
                    <span class="label-icon">💬</span>
                    <span class="label-text" data-i18n="label_message">Mesajınız</span>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <span class="tooltip"
                      data-tooltip="İhtiyacınızı kısaca ama anlaşılır biçimde açıklayın (en az 10 karakter)."
                      data-tooltip-en="Briefly explain what you need (at least 10 characters).">?</span>
            </div>

            <textarea
                id="message"
                name="message"
                autocomplete="off"
                placeholder="Bize iletmek istediğiniz mesajı yazın..."
                data-i18n-placeholder="ph_message"
                required
            ><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="helper">
                <span data-i18n="helper_text">En kısa sürede e-posta ile dönüş yapacağız.</span>
                <span id="charCount" class="char-count"></span>
            </div>
        </div>

        <div class="field" data-field="ipConsent">
            <div class="field-header">
                <label for="ipConsent" style="align-items:flex-start;">
                    <input
                        type="checkbox"
                        id="ipConsent"
                        name="ipConsent"
                        value="1"
                        class="ip-consent-checkbox"
                        <?php echo isset($_POST['ipConsent']) ? 'checked' : ''; ?>
                    >
                    <span>
                        <span class="label-text" data-i18n="ip_consent_label">
                            Bu formu gönderirken IP adresimin güvenlik ve kötüye kullanımın önlenmesi amacıyla kaydedilmesini kabul ediyorum.
                        </span>
                        <button type="button" class="kvkk-link" id="openKvkkBtn" data-i18n="kvkk_link">(Aydınlatma Metni'ni Oku)</button>
                    </span>
                </label>
            </div>
            <div class="helper">
                <span data-i18n="ip_consent_helper">
                    IP adresiniz yalnızca bu form gönderimiyle ilişkili güvenlik kayıtlarında ve olası kötüye kullanım incelemelerinde kullanılacaktır.
                </span>
            </div>
        </div>

        <?php if (!empty($turnstileSiteKey)): ?>
            <!-- Cloudflare Turnstile Doğrulama Kutusu -->
            <div class="captcha-container">
                <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstileSiteKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn" id="submitBtn" disabled>
            <span class="btn-inner">
                <span class="btn-spinner" aria-hidden="true"></span>
                <span class="btn-label" data-i18n="submit_text">Mesajı Gönder</span>
            </span>
        </button>
    </form>
</div>

<!-- KVKK / Gizlilik Politikası Modalı -->
<div class="modal-backdrop" id="kvkkModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="kvkkTitle">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2 class="modal-title" id="kvkkTitle">
                <span>📜</span> <span data-i18n="kvkk_modal_title">KVKK Aydınlatma Metni</span>
            </h2>
            <button type="button" class="modal-close" id="closeKvkkBtn" aria-label="Kapat">&times;</button>
        </div>
        <div class="modal-body" id="kvkkBody">
            <!-- Dil tercihine göre JS ile dinamik doldurulur -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="modalCloseBtn" data-i18n="modal_close">Kapat</button>
            <button type="button" class="btn-accept" id="modalAcceptBtn" data-i18n="modal_accept">Okudum, Onaylıyorum</button>
        </div>
    </div>
</div>

<!-- Canvas Confetti Efekti Kütüphanesi -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>

<!-- Uygulama JavaScript Mantığı -->
<script src="assets/js/app.js"></script>
</body>
</html>