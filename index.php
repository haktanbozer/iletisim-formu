<?php
// iletisim.php

// PHPMailer klasik include
require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer; use PHPMailer\PHPMailer\Exception;

// .env dosyasını oku (parse_ini_file ile)
$env = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);

// Hata ve başarı mesajları
$errors = [];
$successMessage = '';

// Form alanları için başlangıç değerleri
$fullName = '';
$emailRaw = '';
$phone = '';
$subject = '';
$message = '';

// Form gönderildiyse
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    function temizle($value) {
        return trim(filter_var($value, FILTER_SANITIZE_STRING));
    }

    $fullName   = temizle($_POST['fullName']   ?? '');
    $emailRaw   = trim($_POST['email']         ?? '');
    $phone      = temizle($_POST['phone']      ?? '');
    $subject    = temizle($_POST['subject']    ?? '');
    $message    = temizle($_POST['message']    ?? '');

    // IP onay kutusu (işaretlendiyse 1 gelir)
    $ipConsent = isset($_POST['ipConsent']) ? 1 : 0;

    // Kullanıcının IP adresini al
    function getUserIp()
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ipList = explode(',', $_SERVER[$key]);
                $ip = trim($ipList[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'Bilinmiyor';
    }

    $userIp = getUserIp();

    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);

    // Telefon alanı opsiyonel:
    // - Hiç rakam yoksa veya çok az rakam varsa "boş" kabul et
    // - Yeterince rakam varsa genel bir format kontrolü yap
    $phoneStr    = trim($phone);
    $phoneDigits = preg_replace('/\\D+/', '', $phoneStr); // sadece rakamlar

    // Eğer hiç rakam yoksa veya 4'ten az rakam varsa -> hiç yazmamış gibi davran (hata üretme)
    if ($phoneDigits !== '' && strlen($phoneDigits) >= 4) {
        // Başta isteğe bağlı +, 1–3 haneli ülke kodu, sonrasında boşluk/rakam/parantez/çizgi karışımı en az 4 karakter
        if (!preg_match('/^\\+?\\d{1,3}[\\s\\d\\-()]{4,}$/', $phoneStr)) {
            $errors[] = 'Geçerli bir telefon numarası girin.';
        }
    }

    if (!$fullName) {
        $errors[] = 'Lütfen ad soyad girin.';
    }
    if (!$email) {
        $errors[] = 'Geçerli bir e-posta adresi girin.';
    }
    // Konu min. 5 karakter
    if (!$subject || mb_strlen($subject) < 5) {
        $errors[] = 'Konu en az 5 karakter olmalıdır.';
    }
    // Mesaj min. 10 karakter
    if (!$message || mb_strlen($message) < 10) {
        $errors[] = 'Mesaj en az 10 karakter olmalıdır.';
    }
    // IP adresi paylaşımı onayı zorunlu
    if (!$ipConsent) {
        $errors[] = 'Devam edebilmek için IP adresinizin bu formda kaydedilmesini onaylamalısınız.';
    }

    if (empty($errors)) {
        $mail = new PHPMailer(true);

        try {
            // SMTP ayarları .env'den
            $mail->isSMTP();
            $mail->Host       = $env['SMTP_HOST']   ?? '';
            $mail->SMTPAuth   = true;
            $mail->Username   = $env['SMTP_USER']   ?? '';
            $mail->Password   = $env['SMTP_PASS']   ?? '';

            $secure = strtolower($env['SMTP_SECURE'] ?? 'tls');
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = isset($env['SMTP_PORT']) ? (int)$env['SMTP_PORT'] : 465;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = isset($env['SMTP_PORT']) ? (int)$env['SMTP_PORT'] : 587;
            }

            $mail->CharSet = 'UTF-8';

            $fromEmail = $env['MAIL_FROM']      ?? ($env['SMTP_USER'] ?? '');
            $fromName  = $env['MAIL_FROM_NAME'] ?? 'Web İletişim Formu';

            $toEmail   = $env['MAIL_TO']        ?? $fromEmail;
            $toName    = $env['MAIL_TO_NAME']   ?? 'Site Yetkilisi';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);

            $mail->addReplyTo($emailRaw, $fullName);

            $mail->isHTML(false);
            $mail->Subject = 'Web İletişim Formu: ' . $subject;

            $body  = "Ad Soyad: {$fullName}\\n";
            $body .= "E-posta: {$emailRaw}\\n";
            $body .= "Telefon: {$phone}\\n";
            $body .= "Konu: {$subject}\\n\\n";
            $body .= "Mesaj:\\n{$message}\\n\\n";
            $body .= "-----------------------------\\n";
            $body .= "Gönderim IP Adresi: {$userIp}\\n";

            $mail->Body = $body;

            $mail->send();

            $successMessage = 'Mesajınız başarıyla gönderildi. En kısa sürede sizinle iletişime geçeceğiz.';

            $fullName = '';
            $emailRaw = '';
            $phone    = '';
            $subject  = '';
            $message  = '';
        } catch (Exception $e) {
            $errors[] = 'Mesajınız gönderilirken bir hata oluştu: ' . $mail->ErrorInfo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İletişim</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

<style>
    /* Her şey border-box olsun, taşma olmasın */
    *,
    *::before,
    *::after {
        box-sizing: border-box;
    }

    /* iOS zoom + yatay kaymayı engelle */
    html,
    body {
        max-width: 100%;
        overflow-x: hidden;
        -webkit-text-size-adjust: 100%;
    }

    :root {
        color-scheme: light dark;
        --radius-lg: 16px;
        --radius-md: 10px;
        --transition-fast: 0.15s ease-out;
    }

    body {
        margin: 0;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 100vh;
        padding: 24px;
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    body[data-theme="dark"] {
        background: #020617;
        color: #e5e7eb;
    }

    body[data-theme="light"] {
        background: #f3f4f6;
        color: #111827;
    }

    .container {
        width: 100%;
        max-width: 700px;
        background: rgba(15, 23, 42, 0.96);
        border-radius: var(--radius-lg);
        padding: 24px 24px 28px;
        box-shadow: 0 25px 60px -25px rgba(15, 23, 42, 0.9);
        border: 1px solid rgba(148, 163, 184, 0.3);
        position: relative;
    }

    body[data-theme="light"] .container {
        background: #ffffff;
        border-color: rgba(148, 163, 184, 0.4);
        box-shadow: 0 20px 40px -20px rgba(15, 23, 42, 0.18);
    }

    .top-bar {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-bottom: 8px;
    }

    .toggle-btn,
    .lang-btn {
        border-radius: 999px;
        border: 1px solid rgba(148, 163, 184, 0.5);
        background: transparent;
        color: inherit;
        padding: 4px 10px;
        font-size: 0.8rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background var(--transition-fast), border-color var(--transition-fast), transform 0.1s;
    }

    .toggle-btn:hover,
    .lang-btn:hover {
        background: rgba(148, 163, 184, 0.15);
        transform: translateY(-1px);
    }

    .lang-btn.active {
        background: linear-gradient(135deg, #38bdf8, #6366f1);
        border-color: transparent;
        color: #ffffff;
    }

    h1 {
        font-size: 1.6rem;
        margin-bottom: 0.3rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .title-icon {
        font-size: 1.6rem;
    }

    .subtitle {
        font-size: 0.9rem;
        opacity: 0.8;
        margin-bottom: 1.6rem;
    }

    form {
        margin-top: 8px;
    }

    .field {
        margin-bottom: 16px;
        position: relative;
        padding: 6px 10px 10px;
        border-radius: 12px;
        transition: background var(--transition-fast), box-shadow var(--transition-fast), border-color var(--transition-fast), transform 0.1s;
    }

    .field:hover {
        background: rgba(15, 23, 42, 0.65);
    }

    body[data-theme="light"] .field:hover {
        background: rgba(15, 23, 42, 0.03);
    }

    .field:focus-within {
        background: rgba(15, 23, 42, 0.8);
        box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.7), 0 18px 35px -20px rgba(37, 99, 235, 0.8);
        transform: translateY(-1px);
    }

    body[data-theme="light"] .field:focus-within {
        background: #ffffff;
        box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.8), 0 18px 35px -20px rgba(37, 99, 235, 0.75);
    }

    .field-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
    }

    label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: color var(--transition-fast), transform 0.1s;
    }

    .field:focus-within label {
        color: #38bdf8;
        transform: translateY(-1px);
    }

    .label-icon {
        font-size: 1rem;
    }

    .required {
        color: #f97373;
        margin-left: 2px;
    }

    .tooltip {
        position: relative;
        font-size: 0.8rem;
        border-radius: 999px;
        padding: 2px 7px;
        border: 1px solid rgba(148, 163, 184, 0.6);
        cursor: default;
        user-select: none;
        opacity: 0.8;
    }

    .tooltip::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 50%;
        transform: translateX(-50%) translateY(4px);
        bottom: 135%;
        min-width: 190px;
        max-width: 260px;
        background: #020617;
        color: #e5e7eb;
        padding: 6px 8px;
        border-radius: 8px;
        font-size: 0.75rem;
        line-height: 1.3;
        opacity: 0;
        pointer-events: none;
        box-shadow: 0 12px 25px -15px rgba(15, 23, 42, 0.9);
        transition: opacity 0.15s ease, transform 0.15s ease;
        z-index: 10;
        white-space: normal;
    }

    body[data-theme="light"] .tooltip::after {
        background: #111827;
        color: #f9fafb;
    }

.tooltip:hover::after,
.tooltip.tooltip-open::after {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}


    /* TEXT INPUT, TEXTAREA, SELECT – checkbox hariç */
    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="password"],
    input[type="search"],
    textarea,
    select {
        width: 100%;
        padding: 10px 12px;
        border-radius: var(--radius-md);
        border: 1px solid rgba(148, 163, 184, 0.5);
        background: rgba(15, 23, 42, 0.95);
        color: inherit;
        font-size: 16px; /* iOS zoom fix */
        outline: none;
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast), transform 0.06s;
    }

    body[data-theme="light"] input[type="text"],
    body[data-theme="light"] input[type="email"],
    body[data-theme="light"] input[type="tel"],
    body[data-theme="light"] input[type="password"],
    body[data-theme="light"] input[type="search"],
    body[data-theme="light"] textarea,
    body[data-theme="light"] select {
        background: #f9fafb;
        border-color: rgba(148, 163, 184, 0.7);
    }

    input[type="text"]::placeholder,
    input[type="email"]::placeholder,
    input[type="tel"]::placeholder,
    input[type="password"]::placeholder,
    input[type="search"]::placeholder,
    textarea::placeholder {
        color: rgba(148, 163, 184, 0.9);
    }

    textarea {
        min-height: 120px;
        resize: vertical;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="tel"]:focus,
    input[type="password"]:focus,
    input[type="search"]:focus,
    textarea:focus,
    select:focus {
        border-color: #38bdf8;
        box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.6);
        background: #020617;
        transform: translateY(-1px);
    }

    body[data-theme="light"] input[type="text"]:focus,
    body[data-theme="light"] input[type="email"]:focus,
    body[data-theme="light"] input[type="tel"]:focus,
    body[data-theme="light"] input[type="password"]:focus,
    body[data-theme="light"] input[type="search"]:focus,
    body[data-theme="light"] textarea:focus,
    body[data-theme="light"] select:focus {
        background: #ffffff;
    }

    .input-error {
        border-color: #f97373 !important;
        box-shadow: 0 0 0 1px rgba(248, 113, 113, 0.7) !important;
    }

    /* IP onay kutusu (checkbox) için ayrı stil */
    .ip-consent-checkbox {
        width: auto;
        height: auto;
        margin-right: 8px;
        margin-top: 2px;
        accent-color: #38bdf8;
        cursor: pointer;
    }

    .helper {
        font-size: 0.78rem;
        color: #9ca3af;
        margin-top: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }

    body[data-theme="light"] .helper {
        color: #6b7280;
    }

    .char-count {
        font-variant-numeric: tabular-nums;
    }

    .char-count.invalid {
        color: #f97373;
    }

    .btn {
        width: 100%;
        padding: 11px 18px;
        border-radius: 999px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.95rem;
        background: linear-gradient(135deg, #38bdf8, #6366f1);
        color: white;
        transition: transform 0.08s, box-shadow 0.1s, filter 0.1s, opacity 0.1s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-inner {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-spinner {
        width: 16px;
        height: 16px;
        border-radius: 999px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: #ffffff;
        animation: spin 0.75s linear infinite;
        opacity: 0;
        transform: scale(0.4);
        transition: opacity var(--transition-fast), transform var(--transition-fast);
    }

    .btn:hover {
        filter: brightness(1.05);
        box-shadow: 0 16px 35px -18px rgba(59, 130, 246, 0.95);
        transform: translateY(-1px);
    }

    .btn:active {
        transform: translateY(0) scale(0.97);
        box-shadow: 0 8px 20px -12px rgba(37, 99, 235, 0.8);
    }

    .btn.is-loading {
        cursor: wait;
        opacity: 0.92;
    }

    .btn.is-loading .btn-spinner {
        opacity: 1;
        transform: scale(1);
    }

    .alert {
        padding: 10px 12px;
        border-radius: 12px;
        font-size: 0.85rem;
        margin-bottom: 14px;
        animation: fadeInUp 0.28s ease-out;
    }

    .alert-success {
        background: rgba(22, 163, 74, 0.14);
        border: 1px solid rgba(22, 163, 74, 0.8);
        color: #bbf7d0;
    }

    .alert-error {
        background: rgba(220, 38, 38, 0.15);
        border: 1px solid rgba(220, 38, 38, 0.9);
        color: #fecaca;
    }

    body[data-theme="light"] .alert-success {
        background: rgba(22, 163, 74, 0.07);
        color: #166534;
    }

    body[data-theme="light"] .alert-error {
        background: rgba(220, 38, 38, 0.07);
        color: #b91c1c;
    }

    .error-list {
        margin: 4px 0 0;
        padding-left: 18px;
    }

    /* Success view */
    .success-view {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .success-icon-wrap {
        width: 40px;
        height: 40px;
        border-radius: 999px;
        background: radial-gradient(circle at 30% 20%, #bbf7d0, #15803d);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 4px;
        animation: popIn 0.4s ease-out;
    }

    .success-icon {
        width: 22px;
        height: 22px;
        border-radius: 999px;
        border: 2px solid #ecfdf5;
        position: relative;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.65);
        animation: pulseSoft 1.4s infinite ease-out;
    }

    .success-icon::after {
        content: '';
        position: absolute;
        left: 5px;
        top: 2px;
        width: 7px;
        height: 12px;
        border-right: 2px solid #ecfdf5;
        border-bottom: 2px solid #ecfdf5;
        transform: rotate(40deg);
    }

    .success-title {
        font-size: 1.05rem;
        font-weight: 600;
        animation: bounceIn 0.5s ease-out;
    }

    .success-text {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(4px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes popIn {
        0% {
            transform: scale(0.6);
            opacity: 0;
        }
        70% {
            transform: scale(1.05);
            opacity: 1;
        }
        100% {
            transform: scale(1);
        }
    }

    @keyframes pulseSoft {
        0% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
        }
    }

    @keyframes bounceIn {
        0% {
            transform: translateY(6px);
            opacity: 0;
        }
        60% {
            transform: translateY(-3px);
            opacity: 1;
        }
        100% {
            transform: translateY(0);
        }
    }

    /* === Mobil uyum iyileştirmeleri === */
    @media (max-width: 640px) {
        body {
            padding: 12px;
            align-items: stretch;
        }

        .container {
            max-width: 100%;
            padding: 16px 14px 20px;
            border-radius: 12px;
            box-shadow: 0 14px 30px -18px rgba(15, 23, 42, 0.9);
        }

        .top-bar {
            justify-content: space-between;
            gap: 6px;
            margin-bottom: 10px;
        }

        .toggle-btn,
        .lang-btn {
            padding: 3px 8px;
            font-size: 0.75rem;
        }

        h1 {
            font-size: 1.3rem;
            gap: 6px;
        }

        .title-icon {
            font-size: 1.3rem;
        }

        .subtitle {
            font-size: 0.82rem;
            margin-bottom: 1.2rem;
        }

        .field {
            padding: 6px 8px 9px;
            margin-bottom: 12px;
        }

        .field-header {
            align-items: flex-start;
            gap: 6px;
            flex-direction: column;
        }

        label {
            font-size: 0.8rem;
        }

/* Masaüstü için (mevcut kısım, aynen kalabilir) */
.tooltip::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    bottom: 135%;
    min-width: 190px;
    max-width: 260px;
    background: #020617;
    color: #e5e7eb;
    padding: 6px 8px;
    border-radius: 8px;
    font-size: 0.75rem;
    line-height: 1.3;
    opacity: 0;
    pointer-events: none;
    box-shadow: 0 12px 25px -15px rgba(15, 23, 42, 0.9);
    transition: opacity 0.15s ease, transform 0.15s ease;
    z-index: 10;
    white-space: normal;
}

.tooltip {
    position: relative;
    font-size: 0.8rem;
    border-radius: 999px;
    padding: 2px 7px;
    border: 1px solid rgba(148, 163, 184, 0.6);
    cursor: pointer;             /* mobilde tıklanabilir olduğu belli olsun */
    user-select: none;
    opacity: 0.8;
}

/* Varsayılan (desktop) tooltip – ikonun üstünde */
.tooltip::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    bottom: 135%;
    min-width: 190px;
    max-width: 260px;
    background: #020617;
    color: #e5e7eb;
    padding: 6px 8px;
    border-radius: 8px;
    font-size: 0.75rem;
    line-height: 1.3;
    opacity: 0;
    pointer-events: none;
    box-shadow: 0 12px 25px -15px rgba(15, 23, 42, 0.9);
    transition: opacity 0.15s ease, transform 0.15s ease;
    z-index: 999;
    white-space: normal;
}

body[data-theme="light"] .tooltip::after {
    background: #111827;
    color: #f9fafb;
}

/* Hem hover’da hem tıklamayla açılan hâl */
.tooltip:hover::after,
.tooltip.tooltip-open::after {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* === MOBİLDE: ekranın altına sabit toast gibi göster === */
@media (max-width: 640px) {
    .tooltip::after {
        position: fixed;
        left: 50%;
        bottom: 12px;                 /* ekranın altı */
        top: auto;
        transform: translateX(-50%) translateY(8px);
        max-width: 90vw;
        text-align: center;
        padding: 8px 10px;
    }

    .tooltip:hover::after,
    .tooltip.tooltip-open::after {
        transform: translateX(-50%) translateY(0);
    }
}

// ==== Tooltip tıklama (mobil + iOS uyumlu) ====
document.querySelectorAll('.tooltip').forEach(function(tp) {
    tp.addEventListener('click', function (e) {
        e.stopPropagation(); // diğer tıklamaları etkilemesin
        const isOpen = tp.classList.contains('tooltip-open');
        // önce tüm tooltipleri kapat
        document.querySelectorAll('.tooltip.tooltip-open')
            .forEach(function(other){ other.classList.remove('tooltip-open'); });
        // sonra bu tooltiple toggle et
        if (!isOpen) {
            tp.classList.add('tooltip-open');
        }
    });
});

// Tooltip dışına tıklanınca kapat
document.addEventListener('click', function () {
    document.querySelectorAll('.tooltip.tooltip-open')
        .forEach(function(tp){ tp.classList.remove('tooltip-open'); });
});


        /* input/textarea font-size 16px kalıyor, sadece padding küçülüyor */
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        input[type="search"],
        textarea,
        select {
            padding: 9px 10px;
        }

        .helper {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            font-size: 0.74rem;
        }

        .btn {
            margin-top: 4px;
            padding: 10px 14px;
            font-size: 0.9rem;
        }
    }

    /* Çok küçük ekranlar için (eski telefonlar vs.) */
    @media (max-width: 380px) {
        body {
            padding: 8px;
        }

        .container {
            padding: 14px 10px 18px;
        }

        .top-bar {
            flex-direction: column-reverse;
            align-items: flex-end;
        }
    }
</style>



    <link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.css">

    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/intlTelInput.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js"></script>

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

    <form method="post" action="" id="contactForm" novalidate>
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
                value="<?php echo htmlspecialchars($fullName ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Adınızı ve soyadınızı yazın"
                data-i18n-placeholder="ph_fullName"
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
                value="<?php echo htmlspecialchars($emailRaw ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="ornek@mail.com"
                data-i18n-placeholder="ph_email"
                pattern="^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$"
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
                    <span id="countryDisplay"
                          style="font-size:0.78rem; opacity:0.85;">
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
                value="<?php echo htmlspecialchars($phone ?? '', ENT_QUOTES, 'UTF-8'); ?>"
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
                value="<?php echo htmlspecialchars($subject ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Mesajınızın konusu"
                data-i18n-placeholder="ph_subject"
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
            ><?php echo htmlspecialchars($message ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
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
                        style="margin-right:8px; margin-top:2px;"
                        <?php echo isset($_POST['ipConsent']) ? 'checked' : ''; ?>
                    >
                    <span>
                        <span class="label-text" data-i18n="ip_consent_label">
                            Bu formu gönderirken IP adresimin güvenlik ve kötüye kullanımın önlenmesi amacıyla kaydedilmesini kabul ediyorum.
                        </span>
                    </span>
                </label>
            </div>
            <div class="helper">
                <span data-i18n="ip_consent_helper">
                    IP adresiniz yalnızca bu form gönderimiyle ilişkili güvenlik kayıtlarında ve olası kötüye kullanım incelemelerinde kullanılacaktır.
                </span>
            </div>
        </div>

        <button type="submit" class="btn" id="submitBtn" disabled>
            <span class="btn-inner">
                <span class="btn-spinner" aria-hidden="true"></span>
                <span class="btn-label" data-i18n="submit_text">Mesajı Gönder</span>
            </span>
        </button>
    </form>
</div>

<script>
(function () {
    /* =========================
       1) Tema (dark/light)
       ========================= */
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    const mql = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
    let storedTheme = localStorage.getItem('theme'); // 'dark' | 'light' | null
    let theme;
    let hasManualPreference = !!storedTheme;

    function applyTheme(next) {
        theme = next;
        document.body.setAttribute('data-theme', theme);
        themeIcon.textContent = theme === 'dark' ? '🌙' : '☀️';
    }

    // İlk yükleme: önce kullanıcı tercihi, yoksa sistem teması
    if (storedTheme === 'dark' || storedTheme === 'light') {
        applyTheme(storedTheme);
    } else {
        const prefersDark = mql && mql.matches;
        applyTheme(prefersDark ? 'dark' : 'light');
    }

    // Sistem teması değişirse ve kullanıcı elle değiştirmediyse otomatik uyum sağla
    if (mql && mql.addEventListener) {
        mql.addEventListener('change', (e) => {
            if (!hasManualPreference) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    } else if (mql && mql.addListener) {
        mql.addListener((e) => {
            if (!hasManualPreference) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    // Kullanıcı butona basarsa manuel tercih geçerli olsun
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const next = theme === 'dark' ? 'light' : 'dark';
            hasManualPreference = true;
            localStorage.setItem('theme', next);
            applyTheme(next);
        });
    }

    /* =========================
       2) Dil / çeviriler
       ========================= */
    const langButtons = document.querySelectorAll('[data-lang-btn]');
    const translations = {
        tr: {
            heading: 'Bizimle İletişime Geçin',
            subtitle: 'Sorularınız, önerileriniz veya proje fikirleriniz için formu doldurmanız yeterli.',
            label_fullName: 'Ad Soyad',
            label_email: 'E-posta Adresi',
            label_phone: 'Telefon Numarası',
            label_optional: '(opsiyonel)',
            label_subject: 'Konu',
            label_message: 'Mesajınız',
            helper_text: 'En kısa sürede e-posta ile dönüş yapacağız.',
            submit_text: 'Mesajı Gönder',
            error_title: 'Form gönderilirken bazı hatalar oluştu:',
            success_title: 'Teşekkürler!',
            success_text: 'Mesajınız başarıyla gönderildi. En kısa sürede sizinle iletişime geçeceğiz.',
            ph_fullName: 'Adınızı ve soyadınızı yazın',
            ph_email: 'ornek@mail.com',
            ph_phone: '+90 5xx xxx xx xx',
            ph_subject: 'Mesajınızın konusu',
            ph_message: 'Bize iletmek istediğiniz mesajı yazın...',
            ip_consent_label: 'Bu formu gönderirken IP adresimin güvenlik ve kötüye kullanımın önlenmesi amacıyla kaydedilmesini kabul ediyorum.',
            ip_consent_helper: 'IP adresiniz yalnızca bu form gönderimiyle ilişkili güvenlik kayıtlarında ve olası kötüye kullanım incelemelerinde kullanılacaktır.'
        },
        en: {
            heading: 'Get in Touch',
            subtitle: 'For questions, suggestions, or project ideas, just fill out the form.',
            label_fullName: 'Full Name',
            label_email: 'Email Address',
            label_phone: 'Phone Number',
            label_optional: '(optional)',
            label_subject: 'Subject',
            label_message: 'Your Message',
            helper_text: 'We will get back to you via email as soon as possible.',
            submit_text: 'Send Message',
            error_title: 'There were some problems with your submission:',
            success_title: 'Thank you!',
            success_text: 'Your message has been sent successfully. We will contact you as soon as possible.',
            ph_fullName: 'Enter your full name',
            ph_email: 'example@mail.com',
            ph_phone: '+90 5xx xxx xx xx',
            ph_subject: 'Subject of your message',
            ph_message: 'Write the message you want to send us...',
            ip_consent_label: 'I agree that my IP address will be stored for security and abuse prevention purposes when submitting this form.',
            ip_consent_helper: 'Your IP address will only be used in security logs and abuse investigations related to this form submission.'
        }
    };

    const errorMap = {
        'Lütfen ad soyad girin.': {
            en: 'Please enter your full name.'
        },
        'Geçerli bir e-posta adresi girin.': {
            en: 'Please enter a valid email address.'
        },
        'Konu en az 5 karakter olmalıdır.': {
            en: 'Subject must be at least 5 characters long.'
        },
        'Mesaj en az 10 karakter olmalıdır.': {
            en: 'Message must be at least 10 characters long.'
        },
        'Geçerli bir telefon numarası girin.': {
            en: 'Please enter a valid phone number.'
        },
        'Devam edebilmek için IP adresinizin bu formda kaydedilmesini onaylamalısınız.': {
            en: 'To continue, you must agree that your IP address will be stored for this form.'
        }
    };

    let lang = localStorage.getItem('lang') || 'tr';

    function applyLang(nextLang) {
        lang = nextLang;
        localStorage.setItem('lang', lang);

        langButtons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.langBtn === lang);
        });

        const dict = translations[lang];

        // Başlıklar / label metinleri
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (dict[key]) el.textContent = dict[key];
        });

        // Placeholder'lar
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.getAttribute('data-i18n-placeholder');
            if (dict[key]) el.setAttribute('placeholder', dict[key]);
        });

        // Tooltips (TR metni cache'leniyor)
        document.querySelectorAll('.tooltip').forEach(el => {
            if (!el.dataset.tooltipTr && el.getAttribute('data-tooltip')) {
                el.dataset.tooltipTr = el.getAttribute('data-tooltip');
            }

            const trText = el.dataset.tooltipTr || '';
            const enText = el.getAttribute('data-tooltip-en') || '';

            const text = (lang === 'en')
                ? (enText || trText)
                : (trText || enText);

            if (text) {
                el.setAttribute('data-tooltip', text);
            }
        });

        // Hata mesajlarını İngilizceye çevir (tek yön)
        if (lang === 'en') {
            document.querySelectorAll('.error-item').forEach(li => {
                const trText = li.textContent.trim();
                if (errorMap[trText] && errorMap[trText].en) {
                    li.textContent = errorMap[trText].en;
                }
            });
        }
    }

    applyLang(lang);

    langButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            applyLang(btn.dataset.langBtn);
        });
    });

    /* =========================
       3) Mesaj karakter sayacı
       ========================= */
// ==== Mesaj karakter sayacı ====
const messageEl = document.getElementById('message');
const charCount = document.getElementById('charCount');
const MIN_MESSAGE = 10;

// Aktif dile göre label’i data-attribute’ta tut
function setCharCountLabelForLang() {
    if (!charCount) return;
    charCount.dataset.label = (lang === 'en') ? 'Characters' : 'Karakter';
}

// Sayaç metnini güncelle
function updateCharCount() {
    if (!messageEl || !charCount) return;

    const len = messageEl.value.length;
    const label = charCount.dataset.label || ((lang === 'en') ? 'Characters' : 'Karakter');

    charCount.textContent = `${label}: ${len} / min ${MIN_MESSAGE}`;
    charCount.classList.toggle('invalid', len > 0 && len < MIN_MESSAGE);
}

// Input dinleyicisi
if (messageEl) {
    messageEl.addEventListener('input', updateCharCount);
}



    /* =========================
       4) intl-tel-input & ülke algılama
       ========================= */
    const phoneInput = document.getElementById('phone');
    const countryDisplay = document.getElementById('countryDisplay');
    let iti = null;

    if (phoneInput && window.intlTelInput) {
        iti = window.intlTelInput(phoneInput, {
            initialCountry: "tr",
            preferredCountries: ["tr", "us", "gb", "de", "fr"],
            nationalMode: true,
            separateDialCode: false,
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js"
        });

        // İlk açılışta bir şey yoksa +90 yaz
        if (!phoneInput.value.trim()) {
            phoneInput.value = "+90 ";
        }

        const allCountries = window.intlTelInputGlobals
            ? window.intlTelInputGlobals.getCountryData()
            : [];

        function detectCountryFromDigits() {
            if (!allCountries.length) return;
            const raw = phoneInput.value || '';
            const digits = raw.replace(/\\D/g, '');
            if (!digits) return;

            let bestMatch = null;
            allCountries.forEach(c => {
                if (!c.dialCode) return;
                if (digits.startsWith(c.dialCode)) {
                    if (!bestMatch || c.dialCode.length > bestMatch.dialCode.length) {
                        bestMatch = c;
                    }
                }
            });

            if (bestMatch) {
                iti.setCountry(bestMatch.iso2);
            }
        }

        function updateCountryDisplayFromIti() {
            if (!iti || !countryDisplay) return;
            const data = iti.getSelectedCountryData();
            if (!data) return;

            const iso = (data.iso2 || "").toUpperCase();
            const flagEmoji = iso
                ? String.fromCodePoint(...[...iso].map(c => 0x1F1E6 - 65 + c.charCodeAt(0)))
                : "🌐";

            const name = data.name;
            countryDisplay.textContent = `${flagEmoji} ${name} (+${data.dialCode})`;
        }

        phoneInput.addEventListener("countrychange", updateCountryDisplayFromIti);

        phoneInput.addEventListener("input", (e) => {
            let raw = e.target.value || '';
            let digits = raw.replace(/\\D/g, '');

            const MAX_DIGITS = 15;
            if (digits.length > MAX_DIGITS) {
                digits = digits.slice(0, MAX_DIGITS);
            }

            // Basit format: sadece rakam
            e.target.value = digits;

            detectCountryFromDigits();
            updateCountryDisplayFromIti();
        });

        updateCountryDisplayFromIti();
    }

    /* =========================
       5) Form validasyon + IP onayı
       ========================= */
    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        const ipConsent = document.getElementById('ipConsent');

        // IP onayı checkbox'ına göre gönder butonu
        function updateSubmitState() {
            if (!ipConsent) return;
            const allowed = ipConsent.checked;
            submitBtn.disabled = !allowed;
            submitBtn.classList.toggle('btn-disabled', !allowed);
        }

        if (ipConsent) {
            updateSubmitState();
            ipConsent.addEventListener('change', updateSubmitState);
        }

        form.addEventListener('submit', function () {
            submitBtn.classList.add('is-loading');
        });

        // Field bazlı odak hatası belirtimi
        const requiredFields = ['fullName', 'email', 'subject', 'message'];
        requiredFields.forEach(id => {
            const input = document.getElementById(id);
            const wrapper = document.querySelector('.field[data-field="' + id + '"]');
            if (!input || !wrapper) return;

            input.addEventListener('blur', () => {
                const value = input.value.trim();
                let invalid = false;

                if (id === 'email') {
                    const pattern = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
                    invalid = !pattern.test(value);
                } else if (id === 'subject') {
                    invalid = value.length < 5;
                } else if (id === 'message') {
                    invalid = value.length < MIN_MESSAGE;
                } else {
                    invalid = value.length === 0;
                }

                input.classList.toggle('input-error', invalid);
            });

            input.addEventListener('input', () => {
                input.classList.remove('input-error');
            });
        });
    }
})();
</script>
</body>
</html>
