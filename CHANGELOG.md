# 📋 Değişiklik Günlüğü

Tüm önemli değişiklikler bu dosyada belgelenmektedir.

Format: [Keep a Changelog](https://keepachangelog.com/tr/1.0.0/)
Sürümleme: [Semantic Versioning](https://semver.org/lang/tr/)

---

## [Unreleased]

### ✨ Eklenen
- 🐳 Docker demo sistemi (port 8043) — tüm 9 gateway interaktif test
- 🧪 7 yeni gateway unit testi (Vepara, ParamPos, iPara, Ödeal, Paynet, PayU, Papara)
- 📄 `CONTRIBUTING.md` — katkıda bulunma rehberi
- 📜 `CODE_OF_CONDUCT.md` — topluluk davranış kuralları (Contributor Covenant v2.1)
- 🔒 `SECURITY.md` — güvenlik politikası ve sorumlu açıklama süreci
- 🔄 GitHub Actions CI/CD (PHP 8.2/8.3/8.4 matrix, PHPUnit, PHPStan, CS-Fixer)
- 📝 Issue template'leri (bug report, feature request)
- 📬 Pull request template
- 💰 `FUNDING.yml` — GitHub Sponsors
- ⚙️ `.editorconfig` — tutarlı editör ayarları
- 🎨 `.php-cs-fixer.dist.php` — PSR-12 kod stili yapılandırması
- 📦 `composer.json` scripts (test, analyse, cs-fix, cs-check, check)
- 🔁 Response DTO'larına `toArray()` ve `JsonSerializable` desteği

### 🐛 Düzeltilen
- 🔧 PayTR `getTestBaseUrl()` artık sandbox URL döndürüyor (`test.paytr.com`)
- 🔧 PayTR `pay()` yanıtında `trans_id` doğru okunuyor (önceden `merchant_oid` okunuyordu)
- 🔧 `EnumTest` — Papara displayName beklentisi düzeltildi (`'Papara Sanal POS'` → `'Papara'`)
- 🔧 `EnumTest` — `TransactionType::Secure3D` değer beklentisi düzeltildi (`'secure_3d'` → `'secure3d'`)

### ♻️ Değiştirilen
- 📖 `README.md` tamamen yeniden yazıldı — badge'ler, emoji'ler, detaylı örnekler, mimari şema
- 📦 `composer.json` — author bilgileri, support, funding, scripts eklendi
- 🗑️ `.gitignore`'dan `/composer.lock` kaldırıldı (tekrarlanabilir build için)
- 🗑️ `demo/DemoMockHttpClient.php` silindi (index.php'ye inline edilmişti)

---

## [1.0.0] - 2026-02-24

### ✨ Eklenen
- 🎉 İlk kararlı sürüm
- 🏦 **9 Türk ödeme altyapısı** desteği:
  - PayTR — HMAC-SHA256 token, Direct API, iframe
  - Iyzico — REST API, sandbox desteği
  - Vepara — API key/secret tabanlı
  - ParamPos — SOAP/REST hibrit
  - iPara — Public/private key
  - Ödeal — REST API
  - Paynet — Merchant tabanlı
  - PayU — Merchant/secret key
  - Papara — Dijital cüzdan
- 💳 Tek çekim ödeme (`PayableInterface`)
- 🔒 3D Secure ödeme (`SecurePayableInterface`)
- ↩️ İade işlemi (`RefundableInterface`)
- 🔍 Ödeme sorgulama (`QueryableInterface`)
- 🔄 Abonelik / tekrarlayan ödeme (`SubscribableInterface`)
- 📊 Taksit oranı sorgulama (`InstallmentQueryableInterface`)
- 📦 DTO sınıfları: PaymentRequest, CreditCard, Customer, CartItem, Address
- 🔧 Config, HashGenerator, MoneyFormatter destek sınıfları
- 🌐 GuzzleHTTP tabanlı HTTP katmanı
- 🛡️ PHPStan Level 8 statik analiz
- 🧪 PHPUnit 11 birim testleri
- 📄 MIT Lisansı

---

[Unreleased]: https://github.com/armagan-gkc/arpay/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/armagan-gkc/arpay/releases/tag/v1.0.0
