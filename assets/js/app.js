/**
 * Modern & Güvenli İletişim Formu - İstemci Mantığı
 * (Tema, Çoklu Dil, AJAX Form Gönderimi, Telefon Girişi, Mobil Tooltip,
 *  KVKK Aydınlatma Modalı, Otomatik Taslak Kaydetme)
 */
(function () {
    'use strict';

    /* ==========================================================================
       1) Tema Yönetimi (Dark / Light)
       ========================================================================== */
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const mql = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
    let storedTheme = localStorage.getItem('theme');
    let theme;
    let hasManualPreference = !!storedTheme;

    function applyTheme(next) {
        theme = next;
        document.body.setAttribute('data-theme', theme);
        if (themeIcon) {
            themeIcon.textContent = theme === 'dark' ? '🌙' : '☀️';
        }
    }

    if (storedTheme === 'dark' || storedTheme === 'light') {
        applyTheme(storedTheme);
    } else {
        const prefersDark = mql && mql.matches;
        applyTheme(prefersDark ? 'dark' : 'light');
    }

    if (mql && mql.addEventListener) {
        mql.addEventListener('change', (e) => {
            if (!hasManualPreference) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const next = theme === 'dark' ? 'light' : 'dark';
            hasManualPreference = true;
            localStorage.setItem('theme', next);
            applyTheme(next);
        });
    }

    /* ==========================================================================
       2) Dil & Çeviriler (TR / EN)
       ========================================================================== */
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
            ip_consent_helper: 'IP adresiniz yalnızca bu form gönderimiyle ilişkili güvenlik kayıtlarında ve olası kötüye kullanım incelemelerinde kullanılacaktır.',
            kvkk_link: "(Aydınlatma Metni'ni Oku)",
            kvkk_modal_title: 'KVKK Aydınlatma Metni',
            modal_close: 'Kapat',
            modal_accept: 'Okudum, Anladım ve Onaylıyorum',
            draft_restored: 'Daha önce yazdığınız taslak mesaj geri yüklendi.',
            draft_clear: 'Taslağı Sil'
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
            ip_consent_helper: 'Your IP address will only be used in security logs and abuse investigations related to this form submission.',
            kvkk_link: '(Read Privacy Policy)',
            kvkk_modal_title: 'Privacy Policy & GDPR',
            modal_close: 'Close',
            modal_accept: 'I have read, understood & accept',
            draft_restored: 'Your previously saved draft has been restored.',
            draft_clear: 'Discard Draft'
        }
    };

    const errorMap = {
        'Lütfen ad soyad girin.': { en: 'Please enter your full name.' },
        'Geçerli bir e-posta adresi girin.': { en: 'Please enter a valid email address.' },
        'Geçici (kullan-at) e-posta adresleri kabul edilmemektedir. Lütfen kurumsal veya kişisel geçerli bir e-posta adresi girin.': { en: 'Disposable temporary email addresses are not accepted. Please use a valid personal or corporate email.' },
        'Girdiğiniz e-posta alan adına ait aktif bir mail sunucusu (MX kaydı) bulunamadı.': { en: 'No active mail server (MX record) was found for the entered email domain.' },
        'Konu en az 5 karakter olmalıdır.': { en: 'Subject must be at least 5 characters long.' },
        'Mesaj en az 10 karakter olmalıdır.': { en: 'Message must be at least 10 characters long.' },
        'Geçerli bir telefon numarası girin.': { en: 'Please enter a valid phone number.' },
        'Devam edebilmek için IP adresinizin bu formda kaydedilmesini onaylamalısınız.': { en: 'To continue, you must agree that your IP address will be stored for this form.' },
        'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.': { en: 'Security verification failed. Please refresh the page and try again.' }
    };

    let currentLang = localStorage.getItem('lang') || 'tr';

    function applyLang(lang) {
        currentLang = lang;
        localStorage.setItem('lang', lang);

        langButtons.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.langBtn === lang);
        });

        const dict = translations[lang] || translations.tr;

        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (dict[key]) el.textContent = dict[key];
        });

        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.getAttribute('data-i18n-placeholder');
            if (dict[key]) el.setAttribute('placeholder', dict[key]);
        });

        document.querySelectorAll('.tooltip').forEach(el => {
            if (!el.dataset.tooltipTr && el.getAttribute('data-tooltip')) {
                el.dataset.tooltipTr = el.getAttribute('data-tooltip');
            }
            const trText = el.dataset.tooltipTr || '';
            const enText = el.getAttribute('data-tooltip-en') || '';
            const text = (lang === 'en') ? (enText || trText) : (trText || enText);
            if (text) el.setAttribute('data-tooltip', text);
        });

        if (lang === 'en') {
            document.querySelectorAll('.error-item').forEach(li => {
                const trText = li.textContent.trim();
                if (errorMap[trText] && errorMap[trText].en) {
                    li.textContent = errorMap[trText].en;
                }
            });
        }

        updateCharCount();
        renderKvkkText();
    }

    langButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            applyLang(btn.dataset.langBtn);
        });
    });

    /* ==========================================================================
       3) KVKK / Gizlilik Politikası Modalı
       ========================================================================== */
    const kvkkModal = document.getElementById('kvkkModal');
    const openKvkkBtn = document.getElementById('openKvkkBtn');
    const closeKvkkBtn = document.getElementById('closeKvkkBtn');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalAcceptBtn = document.getElementById('modalAcceptBtn');
    const kvkkBody = document.getElementById('kvkkBody');
    const ipConsent = document.getElementById('ipConsent');

    const kvkkTextTr = `
        <p><strong>6698 Sayılı Kişisel Verilerin Korunması Kanunu (“KVKK”) Kapsamında Aydınlatma Metni</strong></p>
        <p>Bu aydınlatma metni, web sitemiz üzerindeki iletişim formu aracılığıyla toplanan kişisel verilerinizin işlenme usul ve esasları hakkında sizleri bilgilendirmek amacıyla hazırlanmıştır.</p>
        <h3>1. Veri Sorumlusu</h3>
        <p>Kişisel verileriniz, veri sorumlusu sıfatıyla şirketimiz tarafından 6698 sayılı KVKK ve ilgili mevzuat uyarınca aşağıda belirtilen çerçevede işlenmektedir.</p>
        <h3>2. İşlenen Kişisel Veriler</h3>
        <ul>
            <li><strong>Kimlik Verisi:</strong> Ad Soyad</li>
            <li><strong>İletişim Verisi:</strong> E-posta Adresi, Telefon Numarası</li>
            <li><strong>İşlem Güvenliği Verisi:</strong> Gönderim Tarihi, IP Adresi, Sistem Logları</li>
            <li><strong>Talep İçeriği:</strong> Mesaj Konusu ve İlettiğiniz Mesaj Detayları</li>
        </ul>
        <h3>3. Kişisel Verilerin İşlenme Amaçları</h3>
        <ul>
            <li>İletişim talebinizin, soru ve önerilerinizin değerlendirilmesi ve yanıtlanması,</li>
            <li>Gerektiğinde sizinle e-posta veya telefon yoluyla iletişime geçilmesi,</li>
            <li>Web sitemizin güvenliğinin sağlanması, yetkisiz erişim, bot ve kötüye kullanım (spam/DoS) girişimlerinin tespiti ve önlenmesi.</li>
        </ul>
        <h3>4. Hukuki Sebepler ve Saklama Süresi</h3>
        <p>Kişisel verileriniz, KVKK madde 5/2 (f) uyarınca veri sorumlusunun meşru menfaatleri ve açık rızanız hukuki sebeplerine dayalı olarak toplanmaktadır. Veriler yasal zamanaşımı süreleri boyunca güvenli sistemlerimizde saklanır.</p>
        <h3>5. İlgili Kişinin Hakları (KVKK Madde 11)</h3>
        <p>Dilediğiniz zaman veri sorumlusuna başvurarak kişisel verilerinizin işlenip işlenmediğini öğrenme, düzeltilmesini veya silinmesini talep etme haklarına sahipsiniz.</p>
    `;

    const kvkkTextEn = `
        <p><strong>Clarification Text on Personal Data Protection and Privacy Policy (GDPR / KVKK)</strong></p>
        <p>This privacy notice is provided to inform you regarding how your personal data collected via our website contact form is processed and protected.</p>
        <h3>1. Data Controller</h3>
        <p>Your personal data is processed by our organization as the data controller in accordance with applicable data protection laws and GDPR principles.</p>
        <h3>2. Collected Personal Data</h3>
        <ul>
            <li><strong>Identity:</strong> Full Name</li>
            <li><strong>Contact:</strong> Email Address, Phone Number</li>
            <li><strong>Security & Technical Data:</strong> Submission Timestamp, IP Address, Audit Logs</li>
            <li><strong>Inquiry Content:</strong> Subject and the content of your message</li>
        </ul>
        <h3>3. Purpose of Processing</h3>
        <ul>
            <li>To evaluate, process, and respond to your inquiries and requests,</li>
            <li>To contact you via email or phone regarding your submission,</li>
            <li>To protect our website infrastructure, prevent spam, abuse, unauthorized access, and automated bot attacks.</li>
        </ul>
        <h3>4. Legal Basis & Retention</h3>
        <p>Data is processed on the basis of legitimate interest (responding to inquiries and maintaining web security) and explicit consent. Data is retained strictly as long as necessary to fulfill the inquiry and comply with legal obligations.</p>
        <h3>5. Your Rights</h3>
        <p>You have the right to request access to, rectification of, or erasure of your personal data at any time by contacting us.</p>
    `;

    function renderKvkkText() {
        if (!kvkkBody) return;
        kvkkBody.innerHTML = (currentLang === 'en') ? kvkkTextEn : kvkkTextTr;
    }

    function openModal() {
        if (!kvkkModal) return;
        renderKvkkText();
        kvkkModal.classList.add('is-open');
        kvkkModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!kvkkModal) return;
        kvkkModal.classList.remove('is-open');
        kvkkModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (openKvkkBtn) openKvkkBtn.addEventListener('click', openModal);
    if (closeKvkkBtn) closeKvkkBtn.addEventListener('click', closeModal);
    if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);

    if (modalAcceptBtn) {
        modalAcceptBtn.addEventListener('click', () => {
            if (ipConsent) {
                ipConsent.checked = true;
                updateSubmitState();
            }
            closeModal();
        });
    }

    if (kvkkModal) {
        kvkkModal.addEventListener('click', (e) => {
            if (e.target === kvkkModal) closeModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && kvkkModal && kvkkModal.classList.contains('is-open')) {
            closeModal();
        }
    });

    /* ==========================================================================
       4) Otomatik Taslak Kaydetme (Autosave / Draft Recovery)
       ========================================================================== */
    const DRAFT_KEY = 'contact_form_draft';
    const draftNotice = document.getElementById('draftNotice');
    const draftClearBtn = document.getElementById('draftClearBtn');

    function saveDraft() {
        const draft = {
            fullName: document.getElementById('fullName')?.value || '',
            email: document.getElementById('email')?.value || '',
            phone: document.getElementById('phone')?.value || '',
            subject: document.getElementById('subject')?.value || '',
            message: document.getElementById('message')?.value || ''
        };
        const hasContent = Object.values(draft).some(val => val.trim().length > 0);
        if (hasContent) {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
        } else {
            localStorage.removeItem(DRAFT_KEY);
        }
    }

    function loadDraft() {
        try {
            const raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return;
            const draft = JSON.parse(raw);
            if (!draft) return;

            let restored = false;
            ['fullName', 'email', 'phone', 'subject', 'message'].forEach(key => {
                const input = document.getElementById(key);
                if (input && !input.value.trim() && draft[key]) {
                    input.value = draft[key];
                    restored = true;
                }
            });

            if (restored && draftNotice) {
                draftNotice.style.display = 'flex';
            }
        } catch (e) {
            console.warn('Draft load failed', e);
        }
    }

    function clearDraft() {
        localStorage.removeItem(DRAFT_KEY);
        if (draftNotice) {
            draftNotice.style.display = 'none';
        }
        ['fullName', 'email', 'phone', 'subject', 'message'].forEach(key => {
            const input = document.getElementById(key);
            if (input) input.value = '';
        });
        const pInput = document.getElementById('phone');
        if (pInput) pInput.value = '+90 ';
        updateCharCount();
    }

    if (draftClearBtn) {
        draftClearBtn.addEventListener('click', clearDraft);
    }

    // Input değişikliklerinde taslağı otomatik kaydet
    ['fullName', 'email', 'phone', 'subject', 'message'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', saveDraft);
        }
    });

    /* ==========================================================================
       5) Tooltip Tıklama (Mobil & Dokunmatik Uyumlu)
       ========================================================================== */
    document.querySelectorAll('.tooltip').forEach(tp => {
        tp.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = tp.classList.contains('tooltip-open');
            document.querySelectorAll('.tooltip.tooltip-open').forEach(other => other.classList.remove('tooltip-open'));
            if (!isOpen) {
                tp.classList.add('tooltip-open');
            }
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('.tooltip.tooltip-open').forEach(tp => tp.classList.remove('tooltip-open'));
    });

    /* ==========================================================================
       6) Mesaj Karakter Sayacı
       ========================================================================== */
    const messageEl = document.getElementById('message');
    const charCount = document.getElementById('charCount');
    const MIN_MESSAGE = 10;

    function updateCharCount() {
        if (!messageEl || !charCount) return;
        const len = messageEl.value.length;
        const label = (currentLang === 'en') ? 'Characters' : 'Karakter';
        charCount.textContent = `${label}: ${len} / min ${MIN_MESSAGE}`;
        charCount.classList.toggle('invalid', len > 0 && len < MIN_MESSAGE);
    }

    if (messageEl) {
        messageEl.addEventListener('input', updateCharCount);
    }

    /* ==========================================================================
       7) intl-tel-input Entegrasyonu
       ========================================================================== */
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

        if (!phoneInput.value.trim()) {
            phoneInput.value = "+90 ";
        }

        const allCountries = window.intlTelInputGlobals
            ? window.intlTelInputGlobals.getCountryData()
            : [];

        function detectCountryFromDigits() {
            if (!allCountries.length) return;
            const raw = phoneInput.value || '';
            const digits = raw.replace(/\D/g, '');
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
            let digits = raw.replace(/\D/g, '');
            const MAX_DIGITS = 15;
            if (digits.length > MAX_DIGITS) {
                digits = digits.slice(0, MAX_DIGITS);
            }
            e.target.value = digits;
            detectCountryFromDigits();
            updateCountryDisplayFromIti();
        });

        updateCountryDisplayFromIti();
    }

    /* ==========================================================================
       8) Form Doğrulama ve AJAX / Fetch API Gönderimi
       ========================================================================== */
    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');
    const alertContainer = document.getElementById('alertContainer');

    function updateSubmitState() {
        if (!ipConsent || !submitBtn) return;
        const allowed = ipConsent.checked;
        submitBtn.disabled = !allowed;
        submitBtn.classList.toggle('btn-disabled', !allowed);
    }

    if (ipConsent) {
        updateSubmitState();
        ipConsent.addEventListener('change', updateSubmitState);
    }

    // Input blur/focus validasyon efektleri
    const requiredFields = ['fullName', 'email', 'subject', 'message'];
    requiredFields.forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;

        input.addEventListener('blur', () => {
            const value = input.value.trim();
            let invalid = false;

            if (id === 'email') {
                const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
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

    // Alert bildirimlerini dinamik oluştur
    function showAlert(type, content) {
        if (!alertContainer) return;

        if (type === 'success') {
            const dict = translations[currentLang] || translations.tr;
            alertContainer.innerHTML = `
                <div class="alert alert-success">
                    <div class="success-view">
                        <div class="success-icon-wrap">
                            <div class="success-icon"></div>
                        </div>
                        <div class="success-title">${dict.success_title}</div>
                        <div class="success-text">${content}</div>
                    </div>
                </div>
            `;
        } else if (type === 'error') {
            const dict = translations[currentLang] || translations.tr;
            let listHtml = '';
            if (Array.isArray(content)) {
                content.forEach(msg => {
                    const transMsg = (currentLang === 'en' && errorMap[msg]?.en) ? errorMap[msg].en : msg;
                    listHtml += `<li class="error-item">${transMsg}</li>`;
                });
            } else {
                listHtml = `<li class="error-item">${content}</li>`;
            }

            alertContainer.innerHTML = `
                <div class="alert alert-error" id="errorBox">
                    <strong>${dict.error_title}</strong>
                    <ul class="error-list">${listHtml}</ul>
                </div>
            `;
        }

        alertContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearAlerts() {
        if (alertContainer) {
            alertContainer.innerHTML = '';
        }
    }

    // AJAX / Fetch Form Gönderimi
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            clearAlerts();
            submitBtn.classList.add('is-loading');
            submitBtn.disabled = true;

            const formData = new FormData(form);

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                const contentType = res.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    return res.json();
                } else {
                    throw new Error('Beklenmeyen sunucu yanıtı.');
                }
            })
            .then(data => {
                submitBtn.classList.remove('is-loading');
                updateSubmitState();

                if (data.success) {
                    showAlert('success', data.message || 'Mesajınız başarıyla gönderildi.');
                    fireConfetti();
                    form.reset();
                    localStorage.removeItem(DRAFT_KEY);
                    if (draftNotice) draftNotice.style.display = 'none';
                    if (phoneInput) phoneInput.value = "+90 ";
                    updateCharCount();
                    updateSubmitState();
                } else {
                    showAlert('error', data.errors && data.errors.length ? data.errors : 'Bir hata oluştu.');
                }
            })
            .catch(err => {
                submitBtn.classList.remove('is-loading');
                updateSubmitState();
                showAlert('error', 'İstek iletilirken bir bağlantı hatası oluştu. Lütfen tekrar deneyin.');
            });
        });
    }

    /* ==========================================================================
       9) Konfeti Kutlama Efekti (Canvas Confetti)
       ========================================================================== */
    function fireConfetti() {
        if (typeof window.confetti !== 'function') return;

        // Merkezden canlı patlama
        window.confetti({
            particleCount: 90,
            spread: 70,
            origin: { y: 0.65 },
            colors: ['#38bdf8', '#6366f1', '#10b981', '#f59e0b', '#ec4899']
        });

        // 250ms sonra yanlardan neşeli çift patlama
        setTimeout(() => {
            window.confetti({
                particleCount: 50,
                angle: 60,
                spread: 55,
                origin: { x: 0.15, y: 0.7 },
                colors: ['#38bdf8', '#10b981', '#fbbf24']
            });
            window.confetti({
                particleCount: 50,
                angle: 120,
                spread: 55,
                origin: { x: 0.85, y: 0.7 },
                colors: ['#6366f1', '#ec4899', '#38bdf8']
            });
        }, 250);
    }

    // Sayfa hazır olduğunda dili uygula ve varsa taslağı yükle
    applyLang(currentLang);
    loadDraft();
    updateCharCount();
})();
