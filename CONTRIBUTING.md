# 🤝 Katkıda Bulunma Rehberi

Arpay'e katkıda bulunmak istediğiniz için teşekkür ederiz! 🎉

Bu belge, projeye nasıl katkıda bulunabileceğinizi adım adım açıklar. Her seviyeden geliştirici katkıda bulunabilir — ilk PR'ınız olsa bile sizi memnuniyetle karşılıyoruz! 🚀

---

## 📋 İçindekiler

- [🐛 Hata Bildirimi](#-hata-bildirimi)
- [💡 Özellik Talebi](#-özellik-talebi)
- [🔧 Geliştirme Ortamı](#-geliştirme-ortamı)
- [📝 Kodlama Standartları](#-kodlama-standartları)
- [🧪 Test Yazma](#-test-yazma)
- [📬 Pull Request Süreci](#-pull-request-süreci)
- [📐 Commit Mesajları](#-commit-mesajları)

---

## 🐛 Hata Bildirimi

Bir hata buldunuz mu? Harika, düzeltmemize yardım edin!

1. Önce [mevcut issue'ları](https://github.com/armagan-gkc/arpay/issues) kontrol edin
2. Bulunamadıysa [yeni bir issue açın](https://github.com/armagan-gkc/arpay/issues/new?template=bug_report.md)
3. Şu bilgileri eklemeyi unutmayın:
   - 🔄 Hatayı tekrarlama adımları
   - ✅ Beklenen davranış
   - ❌ Gerçekleşen davranış
   - 🖥️ PHP versiyonu ve ortam bilgileri
   - 🏦 Hangi gateway'de oluştuğu

---

## 💡 Özellik Talebi

Yeni bir özellik mi istiyorsunuz?

1. [Feature request açın](https://github.com/armagan-gkc/arpay/issues/new?template=feature_request.md)
2. Kullanım senaryonuzu açıklayın
3. Mümkünse bir kod örneği ekleyin

---

## 🔧 Geliştirme Ortamı

### Gereksinimler

- PHP **8.2** veya üzeri
- Composer **2.x**
- Docker (opsiyonel, demo için)

### Kurulum

```bash
# 1. Fork edin ve clone edin
git clone https://github.com/YOUR_USERNAME/arpay.git
cd arpay

# 2. Bağımlılıkları yükleyin
composer install

# 3. Testleri çalıştırın (her şey yeşil olmalı ✅)
composer test

# 4. Feature branch oluşturun
git checkout -b feature/your-amazing-feature
```

### Demo Ortamı

```bash
# Docker ile
docker compose up --build
# → http://localhost:8043

# veya PHP built-in server ile
php -S localhost:8043 -t demo
```

---

## 📝 Kodlama Standartları

Projede aşağıdaki standartları takip ediyoruz:

### PHP

- ✅ **PSR-12** kodlama standardı
- ✅ **strict_types** her dosyada zorunlu
- ✅ **PHPStan Level 8** — maksimum statik analiz
- ✅ Türkçe PHPDoc yorumları
- ✅ Named arguments tercih edilir

### Kontrol Komutları

```bash
# Kod stili kontrolü
composer cs-check

# Otomatik düzeltme
composer cs-fix

# PHPStan analizi
composer analyse

# Tümünü bir seferde çalıştır
composer check
```

### Dosya Yapısı

- Yeni gateway: `src/Gateways/{GatewayName}/` klasörüne
- Yeni DTO: `src/DTO/` klasörüne
- Yeni test: `tests/Unit/` klasörüne
- Her sınıfın kendi dosyası olmalı (PSR-4)

---

## 🧪 Test Yazma

Her PR'da test bekliyoruz! Mevcut test dosyalarını referans alabilirsiniz.

### Test Yapısı

```php
<?php

declare(strict_types=1);

namespace Arpay\Tests\Unit;

use Arpay\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;

class YourGatewayTest extends TestCase
{
    private YourGateway $gateway;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->gateway = new YourGateway();
        $this->httpClient = new MockHttpClient();

        $this->gateway->configure(new Config([...]));
        $this->gateway->setHttpClient($this->httpClient);
    }

    public function test_successful_payment(): void
    {
        $this->httpClient->addResponse(200, ['status' => 'success']);
        // ...
        $this->assertTrue($response->isSuccessful());
    }
}
```

### Test Çalıştırma

```bash
# Tüm testler
composer test

# Belirli bir test dosyası
./vendor/bin/phpunit tests/Unit/YourGatewayTest.php

# Belirli bir test metodu
./vendor/bin/phpunit --filter test_successful_payment
```

---

## 📬 Pull Request Süreci

1. 🍴 Projeyi fork edin
2. 🌿 Feature branch oluşturun (`feature/`, `fix/`, `docs/` prefix'leri)
3. ✍️ Değişikliklerinizi yapın
4. 🧪 Testlerinizi yazın ve geçirin
5. 📝 `CHANGELOG.md`'ye `[Unreleased]` bölümüne ekleyin
6. ✅ `composer check` komutunun geçtiğinden emin olun
7. 📬 Pull Request açın

### PR Kontrol Listesi

- [ ] Testler geçiyor (`composer test`)
- [ ] PHPStan temiz (`composer analyse`)
- [ ] Kod stili uygun (`composer cs-check`)
- [ ] CHANGELOG güncellendi
- [ ] PHPDoc yorumları eklendi

---

## 📐 Commit Mesajları

[Conventional Commits](https://www.conventionalcommits.org/) formatını kullanıyoruz:

| Tip | Açıklama | Örnek |
|-----|----------|-------|
| `feat` | ✨ Yeni özellik | `feat: add Halkbank gateway` |
| `fix` | 🐛 Hata düzeltme | `fix: PayTR refund amount calculation` |
| `docs` | 📚 Belge güncellemesi | `docs: update README installation` |
| `test` | 🧪 Test ekleme/düzeltme | `test: add Vepara gateway tests` |
| `refactor` | ♻️ Kod yeniden yapılandırma | `refactor: extract common gateway logic` |
| `chore` | 🔧 Araç/yapılandırma | `chore: update CI workflow` |
| `style` | 💄 Kod stili | `style: fix PSR-12 violations` |

---

## 🏦 Yeni Gateway Ekleme

Yeni bir ödeme altyapısı eklemek istiyorsanız:

1. `src/Gateways/{GatewayName}/` klasörü oluşturun
2. `{GatewayName}Gateway.php` — `AbstractGateway` extend edin
3. İlgili interface'leri implement edin (`PayableInterface`, `RefundableInterface`, vb.)
4. Helper sınıfı ekleyin (token, hash, format vb.)
5. `src/ArpayFactory.php`'ye gateway'i kaydedin
6. `src/Enums/Gateway.php`'ye enum değerini ekleyin
7. `tests/Unit/{GatewayName}GatewayTest.php` testlerini yazın
8. `README.md` gateway tablosunu güncelleyin
9. `CHANGELOG.md`'ye ekleyin

---

## 💬 İletişim

- 🐛 **Bug/Feature**: [GitHub Issues](https://github.com/armagan-gkc/arpay/issues)
- 📧 **Email**: [ben@armagangokce.com](mailto:ben@armagangokce.com)
- 🌐 **Website**: [armagangokce.com](https://www.armagangokce.com)
- 💼 **LinkedIn**: [Armağan Gökce](https://www.linkedin.com/in/armağan-gökce-b326432a4)

---

## 📜 Davranış Kuralları

Bu proje [Contributor Covenant](CODE_OF_CONDUCT.md) davranış kurallarına uygundur. Katılarak bu kurallara uymayı kabul etmiş olursunuz.

---

<p align="center">
  <sub>Her katkı değerlidir! İster bir typo düzeltmesi, ister yeni bir gateway — hepsi önemli. 💙</sub><br>
  <sub>Teşekkürler! 🙏</sub>
</p>
