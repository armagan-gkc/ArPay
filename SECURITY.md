# 🔒 Güvenlik Politikası

Arpay projesinin güvenliğini ciddiye alıyoruz. Ödeme kütüphanesi olarak güvenlik bizim için en yüksek önceliktir.

---

## ✅ Desteklenen Sürümler

| Sürüm | Destek Durumu |
|--------|:------------:|
| 1.0.x  | ✅ Aktif destek |
| < 1.0  | ❌ Desteklenmiyor |

---

## 🐛 Güvenlik Açığı Bildirimi

Bir güvenlik açığı keşfettiyseniz, lütfen **sorumlu açıklama** (responsible disclosure) sürecini takip edin.

### ⚠️ Önemli

> **Güvenlik açıklarını asla public GitHub issue olarak açmayın!**

### 📧 Bildirme Yöntemi

Güvenlik açığını aşağıdaki e-posta adresine bildirin:

📧 **[ben@armagangokce.com](mailto:ben@armagangokce.com)**

### 📝 Bildirimde Belirtilecekler

Lütfen bildiriminize aşağıdaki bilgileri ekleyin:

1. 🔍 **Açığın Türü** — Ne tür bir güvenlik sorunu (SQL injection, XSS, vb.)
2. 📂 **Etkilenen Dosyalar** — Hangi kaynak dosyalar etkileniyor
3. 🔄 **Yeniden Üretme Adımları** — Açığı tekrarlama adımları
4. 💥 **Potansiyel Etki** — Açığın kötü amaçla kullanılması durumunda olası etkiler
5. 💡 **Önerilen Düzeltme** (opsiyonel) — Varsa önerdiğiniz çözüm

---

## ⏱️ Yanıt Süreci

| Adım | Süre | Açıklama |
|------|------|----------|
| 📩 **Alındı Onayı** | 24-48 saat | Bildiriminizi aldığımızı onaylarız |
| 🔍 **Değerlendirme** | 3-5 gün | Açığı doğrular ve önceliklendiriz |
| 🛠️ **Düzeltme** | 7-14 gün | Yamanın geliştirilmesi ve test edilmesi |
| 📢 **Duyuru** | Yama sonrası | Güvenlik danışma belgesi yayınlanır |

---

## 🛡️ Güvenlik Uygulamalarımız

Arpay'de uygulanan güvenlik önlemleri:

- 🔐 **HMAC-SHA256** token imzalama (PayTR, Iyzico, vb.)
- 🔒 **HTTPS** zorunluluğu tüm API çağrılarında
- 🧹 **Input sanitizasyonu** tüm kullanıcı girdilerinde
- 📊 **PHPStan Level 8** statik analiz ile potansiyel güvenlik sorunlarının tespiti
- 🚫 **Hassas veri loglama yasağı** — kart numaraları asla loglanmaz
- ✅ **`strict_types`** tüm dosyalarda zorunlu

---

## 🏆 Teşekkür

Güvenlik açığı bildiren araştırmacılara teşekkür ederiz. İzninizle, adınızı bu bölümde yayınlayabiliriz.

---

## 📞 İletişim

- 📧 **Email**: [ben@armagangokce.com](mailto:ben@armagangokce.com)
- 🌐 **Website**: [armagangokce.com](https://www.armagangokce.com)
- 💻 **GitHub**: [armagan-gkc](https://github.com/armagan-gkc)

---

<p align="center">
  <sub>Güvenlik, bir özellik değil — temel bir gerekliliktir. 🛡️</sub>
</p>
