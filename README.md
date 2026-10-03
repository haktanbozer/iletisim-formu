# ✉️ Modern, Güvenli & Responsive Web İletişim Formu

<p align="center">
  <img src="https://img.shields.io/badge/PHP-%3E%3D%207.4%20%7C%208.0%20%7C%208.1%20%7C%208.2%20%7C%208.3-777bb4?style=for-the-badge&logo=php&logoColor=white" alt="PHP Version" />
  <img src="https://img.shields.io/badge/PHPMailer-v7.0.1-blue?style=for-the-badge" alt="PHPMailer" />
  <img src="https://img.shields.io/badge/Security-Hardened-success?style=for-the-badge&logo=shield" alt="Security" />
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License" />
</p>

---

## 📖 Proje Hakkında

İnternetteki klasik web iletişim formlarının büyük bir çoğunluğu; **CSRF açıkları, bot istilaları (Mail Bombing), spagetti kod yapısı, sayfa yenilenme mecburiyeti ve zayıf hata yönetimi** gibi ciddi eksiklikler barındırır.

Bu proje, bu eksiklikleri ortadan kaldırmak amacıyla geliştirilmiş **kurumsal seviyede, bağımsız ve siber güvenlik odaklı** bir iletişim formudur. Harici ağır framework'lere ihtiyaç duymadan, saf ve temiz bir nesne yönelimli (OOP) mimariyle çalışır.

---

## 🚀 Öne Çıkan Özellikler

### 🛡️ 1. Siber Güvenlik Katmanı
* **Anti-CSRF Belirteçleri:** Oturum tabanlı, kriptografik rastgele üretilen ve `hash_equals()` ile doğrulanan token koruması.
* **Honeypot Bot Tuzağı:** Kullanıcıya görünmeyen ancak web tarayıcı botlarının doldurduğu alanı denetleyerek spam gönderimleri sessizce engeller.
* **IP Tabanlı Rate Limiting:** Aynı IP adresinden 5 dakika içerisinde en fazla 4 gönderime izin vererek sunucunuzu DoS ve Mail Bombing saldırılarına karşı korur.
* **Geçici (Disposable) E-posta Engelleme:** 35'ten fazla popüler kullan-at / sahte e-posta sağlayıcısını (`tempmail`, `10minutemail`, `mailinator`, `guerrillamail` vb.) anında engeller.
* **DNS MX Kaydı Doğrulaması:** Girilen e-posta alan adının gerçekten aktif bir e-posta sunucusuna sahip olup olmadığını DNS üzerinden sorgular (`checkdnsrr`).
* **PHP 8.2 & 8.3 Tam Uyumluluğu:** Kaldırılan eski fonksiyonlar (`FILTER_SANITIZE_STRING` vb.) yerine modern ve güvenli metin filtreleme katmanı kullanır.
* **Bilgi İfşası (Information Disclosure) Koruması:** Sunucu port, IP ve SMTP hata detayları ziyaretçiye gösterilmez; güvenli `storage/logs/error.log` dosyasına yazılır.
* **Dizin ve Dosya Güvenliği:** `.env`, `src/` ve `storage/` klasörlerine doğrudan internetten erişimi engelleyen katı `.htaccess` kuralları içerir.

---

### 🎨 2. Modern Kullanıcı Deneyimi (UX) & Tasarım
* **AJAX / Fetch API İletimi:** Sayfa beyazlayıp yeniden yüklenmez. Buton içinde şık bir yükleniyor (spinner) animasyonu ile anında yanıt verilir.
* **🎉 Canvas Confetti:** Mesaj başarıyla gönderildiği anda ekranda çok renkli ve neşeli bir kutlama konfetisi patlar.
* **📝 Otomatik Taslak Kaydetme (Autosave):** Kullanıcı mesaj yazarken tarayıcıyı kapatsa bile, geri geldiğinde mesajı `localStorage`'dan otomatik kurtarılır.
* **🌓 Karanlık / Aydınlık Mod (Dark/Light):** Sistem temasını otomatik algılar ve sağ üstteki butonla manuel değiştirilebilir.
* **🌐 Çoklu Dil Desteği (TR / EN):** Arayüz, form etiketleri, uyarılar ve KVKK metinleri tek tıkla Türkçe ve İngilizce arasında geçiş yapar.
* **📞 Uluslararası Telefon Girişi:** `intl-tel-input` kütüphanesiyle dinamik bayrak, ülke kodu ve formatlama sunar.
* **📜 KVKK / GDPR Aydınlatma Modalı:** 6698 sayılı Kanun standartlarında hazırlanmış, buzlu cam efektli (backdrop blur) açılır aydınlatma metni ve hızlı onaylama.

---

### 📬 3. Kurumsal E-posta & Veri Yedeklemesi
* **Zarif HTML E-posta:** Yöneticiye giden şık, tablolu, logolu ve mobil uyumlu bildirim e-postası.
* **🤖 Otomatik Müşteri Teyiti (Autoresponder):** Formu dolduran ziyaretçiye anında *"Mesajınız Bize Ulaştı ✓"* başlıklı kurumsal bir bilgilendirme e-postası iletilir (`.env` ile kapatılabilir).
* **💾 Sıfır Veri Kaybı (JSON Yedekleme):** SMTP sunucunuz kapalı veya arızalı olsa bile gelen formlar `storage/logs/contacts.log` içerisine kaydedilir.

---

## 📂 Proje Dizin Yapısı

```text
├── assets/
│   ├── css/style.css         # Responsive stiller, dark/light tema ve modal tasarımı
│   └── js/app.js             # AJAX gönderimi, çoklu dil, taslak kaydetme ve konfeti
├── src/
│   ├── Config.php            # .env ortam değişkenleri yöneticisi
│   ├── Security.php          # CSRF, Honeypot, Rate Limit ve DNS doğrulamaları
│   └── ContactForm.php       # E-posta motoru, HTML şablonları ve yedekleme
├── storage/
│   ├── .htaccess             # Dış web erişimini tamamen engelleyen kural
│   ├── cache/                # Rate limit önbellek kayıtları
│   ├── logs/                 # contacts.log ve error.log
│   └── sessions/             # Güvenli PHP oturum dosyaları
├── PHPMailer/                # PHPMailer v7 kütüphanesi
├── .env.example              # Ortam değişkenleri şablonu (GitHub'a eklenir)
├── .gitignore                # Hassas verileri filtreleyen Git kuralı
├── .htaccess                 # Sunucu güvenlik başlıkları ve rewrite kuralları
├── composer.json             # PSR-4 standart paket yapılandırması
├── install.php               # Tek tıkla otomatik kurulum sihirbazı (Turnkey Installer)
├── index.php                 # Giriş kontrolcüsü ve form arayüzü
├── LICENSE                   # MIT Lisans dosyası
└── README.md                 # Proje dokümantasyonu
```

---

## 🛠️ Kurulum Seçenekleri

### ⚡ Yöntem 1: Otomatik Web Kurulum Sihirbazı (Önerilen)

> **İndir / Yükle ➔ Tarayıcıdan Aç ➔ `install.php` Formunu Doldur ➔ Tamamlandı!**

1. Projeyi sunucunuza yükleyin.
2. Tarayıcınızdan `https://siteniz.com/install.php` adresine gidin. *(Akıllı Yönlendirme sayesinde doğrudan `index.php`'yi açsanız bile sistem kurulmamışsa otomatik olarak `install.php`'ye yönlendirilirsiniz).*
3. Sihirbaz sistem gereksinimlerini kontrol eder; SMTP bilgilerinizi ve yetkili e-posta adresinizi girmenizi ister.
4. İsteğe bağlı olarak tek tıkla **deneme e-postası** göndererek SMTP bağlantınızı anında doğrular.
5. Tüm ayarları içeren `.env` dosyasını otomatik oluşturur, `storage/` klasörlerini yapılandırır ve güvenliğiniz için `installed.lock` oluşturarak sihirbazı kilitler.

---

### 💻 Yöntem 2: Manuel Kurulum (Geliştiriciler İçin)

1. Depoyu klonlayın:
```bash
git clone https://github.com/KULLANICI_ADINIZ/REPO_ADINIZ.git
cd REPO_ADINIZ
```

2. `.env.example` dosyasını `.env` olarak kopyalayın:
```bash
cp .env.example .env
```

3. `.env` dosyasını açıp SMTP sunucu bilgilerinizi tanımlayın:
```ini
SMTP_HOST=mail.siteniz.com
SMTP_PORT=587
SMTP_USER=bildirim@siteniz.com
SMTP_PASS=guclu_parolaniz
SMTP_SECURE=tls

MAIL_FROM=bildirim@siteniz.com
MAIL_FROM_NAME="Web İletişim Formu"
MAIL_TO=yetkili@siteniz.com
MAIL_TO_NAME="Site Yetkilisi"

# Otomatik Müşteri Teyit E-postası
AUTORESPONDER_ENABLED=true
```

4. `storage/` klasörüne yazma izni (755) verin:
```bash
chmod -R 755 storage/
```

---

## 🔒 Güvenlik Notu
* Gerçek SMTP şifrelerinizin yer aldığı `.env` dosyasını **kesinlikle GitHub'a yüklemeyiniz**. Projedeki `.gitignore` dosyası bu dosyayı otomatik olarak hariç tutacak şekilde yapılandırılmıştır.

---

## 📄 Lisans
Bu proje [MIT Lisansı](LICENSE) kapsamında açık kaynak olarak paylaşılmıştır.
