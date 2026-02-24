<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/PHPStan-Level%208-4FC08D?style=for-the-badge" alt="PHPStan Level 8">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="MIT License">
  <img src="https://img.shields.io/badge/Gateways-9-orange?style=for-the-badge" alt="9 Gateways">
  <img src="https://img.shields.io/github/actions/workflow/status/armagan-gkc/arpay/ci.yml?style=for-the-badge&label=CI" alt="CI Status">
</p>

<h1 align="center">💳 Arpay</h1>

<p align="center">
  <strong>Türkiye'nin Birleşik Ödeme Kütüphanesi</strong><br>
  <em>9 farklı Türk ödeme altyapısını tek bir API ile yönetin.</em>
</p>

<p align="center">
  <a href="#-kurulum">Kurulum</a> •
  <a href="#-hızlı-başlangıç">Hızlı Başlangıç</a> •
  <a href="#-desteklenen-gatewayler">Gateway'ler</a> •
  <a href="#-özellikler">Özellikler</a> •
  <a href="#-demo">Demo</a> •
  <a href="#-katkıda-bulunma">Katkıda Bulunma</a>
</p>

---

## 🌟 Nedir?

**Arpay**, Türkiye'deki popüler ödeme altyapılarını tek bir birleşik PHP arayüzü altında toplayan açık kaynak ödeme kütüphanesidir. Gateway değiştirmek artık tek satır değişiklik demek!

```php
// PayTR ile ödeme al
$gateway = Arpay::create('paytr', $config);
$response = $gateway->pay($request);

// Iyzico'ya geçmek mi istiyorsun? Sadece gateway adını değiştir!
$gateway = Arpay::create('iyzico', $config);
$response = $gateway->pay($request); // Aynı $request, aynı API!
```

---

## ✨ Özellikler

| Özellik | Açıklama |
|---------|----------|
| 💳 **Tek Çekim Ödeme** | Tüm gateway'lerle anında ödeme |
| 🔒 **3D Secure** | Güvenli ödeme ile banka doğrulama |
| 💰 **İade İşlemleri** | Tam veya kısmi iade desteği |
| 🔍 **Ödeme Sorgulama** | İşlem durumu kontrolü |
| 🔄 **Abonelik / Tekrarlayan Ödeme** | Otomatik periyodik tahsilat |
| 📊 **Taksit Sorgulama** | BIN bazlı taksit oranları |
| 🧪 **Test Modu** | Sandbox ortamında güvenli geliştirme |
| 🐳 **Docker Demo** | Anında çalışan interaktif demo |
| 🛡️ **PHPStan Level 8** | Maksimum statik analiz güvencesi |

---

## 🏦 Desteklenen Gateway'ler

| # | Gateway | Ödeme | İade | Sorgu | 3D Secure | Abonelik | Taksit |
|---|---------|:-----:|:----:|:-----:|:---------:|:--------:|:------:|
| 1 | **PayTR** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 2 | **Iyzico** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 3 | **Vepara** | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| 4 | **ParamPos** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 5 | **iPara** | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| 6 | **Ödeal** | ✅ | ✅ | ✅ | ✅ | — | — |
| 7 | **Paynet** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8 | **PayU** | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| 9 | **Papara** | ✅ | ✅ | ✅ | — | — | — |

---

## 📦 Kurulum

```bash
composer require armagangokce/arpay
```

### Gereksinimler

- PHP **8.2** veya üzeri
- `ext-json`, `ext-openssl`, `ext-mbstring`
- Guzzle HTTP `^7.8`

---

## 🚀 Hızlı Başlangıç

### 💳 Tek Çekim Ödeme

```php
<?php

use Arpay\Arpay;
use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\CartItem;

// 1. Gateway oluştur
$gateway = Arpay::create('paytr', [
    'merchant_id'   => 'YOUR_MERCHANT_ID',
    'merchant_key'  => 'YOUR_MERCHANT_KEY',
    'merchant_salt' => 'YOUR_MERCHANT_SALT',
    'test_mode'     => true,
]);

// 2. Kart bilgileri
$card = CreditCard::create(
    cardHolderName: 'Armağan Gökce',
    cardNumber:     '5528790000000008',
    expireMonth:    '12',
    expireYear:     '2030',
    cvv:            '123',
);

// 3. Müşteri bilgileri
$customer = Customer::create(
    firstName: 'Armağan',
    lastName:  'Gökce',
    email:     'ben@armagangokce.com',
    phone:     '05551234567',
    ip:        '127.0.0.1',
);

// 4. Ödeme isteği
$request = PaymentRequest::create()
    ->amount(150.00)
    ->currency('TRY')
    ->orderId('ORDER-' . time())
    ->description('Premium Paket')
    ->installmentCount(1)
    ->card($card)
    ->customer($customer)
    ->addCartItem(CartItem::create('P001', 'Premium Paket', 'Yazılım', 150.00));

// 5. Ödeme yap!
$response = $gateway->pay($request);

if ($response->isSuccessful()) {
    echo "✅ Ödeme başarılı! İşlem No: " . $response->getTransactionId();
} else {
    echo "❌ Hata: " . $response->getErrorMessage();
}
```

### ↩️ İade İşlemi

```php
use Arpay\DTO\RefundRequest;

$refund = RefundRequest::create()
    ->transactionId('TXN-12345')
    ->amount(50.00)
    ->reason('Müşteri talebi');

$response = $gateway->refund($refund);

if ($response->isSuccessful()) {
    echo "✅ İade başarılı! Tutar: " . $response->getRefundedAmount() . " TL";
}
```

### 🔒 3D Secure Ödeme

```php
use Arpay\DTO\SecurePaymentRequest;

$request = SecurePaymentRequest::create()
    ->amount(250.00)
    ->currency('TRY')
    ->orderId('ORDER-3D-001')
    ->card($card)
    ->customer($customer)
    ->callbackUrl('https://example.com/callback')
    ->successUrl('https://example.com/success')
    ->failUrl('https://example.com/fail');

$response = $gateway->initSecurePayment($request);

if ($response->isRedirectRequired()) {
    // Müşteriyi banka sayfasına yönlendir
    echo $response->getRedirectForm();
}
```

### 🔍 Ödeme Sorgulama

```php
use Arpay\DTO\QueryRequest;

$query = QueryRequest::create()
    ->transactionId('TXN-12345')
    ->orderId('ORDER-001');

$response = $gateway->query($query);

if ($response->isSuccessful()) {
    echo "Durum: " . $response->getPaymentStatus()->value;
    echo "Tutar: " . $response->getAmount() . " TL";
}
```

### 📊 Taksit Sorgulama

```php
$installments = $gateway->queryInstallments('552879', 1000.00);

foreach ($installments as $info) {
    echo "{$info->installmentCount} taksit: {$info->installmentAmount} TL/ay "
       . "(Toplam: {$info->totalAmount} TL, Faiz: %{$info->interestRate})\n";
}
```

### �️ Hata Yönetimi

```php
use Arpay\Exceptions\ArpayException;
use Arpay\Exceptions\GatewayNotFoundException;
use Arpay\Exceptions\InvalidParameterException;
use Arpay\Exceptions\AuthenticationException;
use Arpay\Exceptions\NetworkException;
use Arpay\Exceptions\PaymentFailedException;
use Arpay\Exceptions\UnsupportedOperationException;

try {
    $gateway = Arpay::create('paytr', $config);
    $response = $gateway->pay($request);

    if (!$response->isSuccessful()) {
        echo "Reddedildi: " . $response->getErrorMessage();
    }
} catch (GatewayNotFoundException $e) {
    // Geçersiz gateway adı
} catch (InvalidParameterException $e) {
    // Eksik yapılandırma
} catch (AuthenticationException $e) {
    // Yanlış API anahtarları
} catch (NetworkException $e) {
    // Bağlantı hatası / timeout
} catch (PaymentFailedException $e) {
    // Kritik ödeme hatası — $e->getErrorCode(), $e->getRawResponse()
} catch (UnsupportedOperationException $e) {
    // Gateway bu işlemi desteklemiyor
} catch (ArpayException $e) {
    // Tüm Arpay hataları (genel catch)
}
```

> 💡 Detaylı hata yönetimi örnekleri için [Hızlı Başlangıç Rehberi](docs/QUICK_START.md#-hata-yönetimi)'ne bakın.

### �🔄 Abonelik Oluşturma

```php
use Arpay\DTO\SubscriptionRequest;

$subscription = SubscriptionRequest::create()
    ->planName('Premium Aylık')
    ->amount(99.99)
    ->currency('TRY')
    ->period('monthly')
    ->periodInterval(1)
    ->card($card)
    ->customer($customer);

$response = $gateway->createSubscription($subscription);

if ($response->isSuccessful()) {
    echo "✅ Abonelik oluşturuldu: " . $response->getSubscriptionId();
}
```

---

## ⚙️ Gateway Yapılandırmaları

<details>
<summary><strong>PayTR</strong></summary>

```php
$gateway = Arpay::create('paytr', [
    'merchant_id'   => 'YOUR_MERCHANT_ID',
    'merchant_key'  => 'YOUR_MERCHANT_KEY',
    'merchant_salt' => 'YOUR_MERCHANT_SALT',
    'test_mode'     => true,
]);
```
</details>

<details>
<summary><strong>Iyzico</strong></summary>

```php
$gateway = Arpay::create('iyzico', [
    'api_key'    => 'YOUR_API_KEY',
    'secret_key' => 'YOUR_SECRET_KEY',
    'test_mode'  => true,
]);
```
</details>

<details>
<summary><strong>Vepara</strong></summary>

```php
$gateway = Arpay::create('vepara', [
    'api_key'     => 'YOUR_API_KEY',
    'secret_key'  => 'YOUR_SECRET_KEY',
    'merchant_id' => 'YOUR_MERCHANT_ID',
    'test_mode'   => true,
]);
```
</details>

<details>
<summary><strong>ParamPos</strong></summary>

```php
$gateway = Arpay::create('parampos', [
    'client_code'     => 'YOUR_CLIENT_CODE',
    'client_username' => 'YOUR_USERNAME',
    'client_password' => 'YOUR_PASSWORD',
    'guid'            => 'YOUR_GUID',
    'test_mode'       => true,
]);
```
</details>

<details>
<summary><strong>iPara</strong></summary>

```php
$gateway = Arpay::create('ipara', [
    'public_key'  => 'YOUR_PUBLIC_KEY',
    'private_key' => 'YOUR_PRIVATE_KEY',
    'test_mode'   => true,
]);
```
</details>

<details>
<summary><strong>Ödeal</strong></summary>

```php
$gateway = Arpay::create('odeal', [
    'api_key'    => 'YOUR_API_KEY',
    'secret_key' => 'YOUR_SECRET_KEY',
    'test_mode'  => true,
]);
```
</details>

<details>
<summary><strong>Paynet</strong></summary>

```php
$gateway = Arpay::create('paynet', [
    'secret_key'  => 'YOUR_SECRET_KEY',
    'merchant_id' => 'YOUR_MERCHANT_ID',
    'test_mode'   => true,
]);
```
</details>

<details>
<summary><strong>PayU</strong></summary>

```php
$gateway = Arpay::create('payu', [
    'merchant'   => 'YOUR_MERCHANT',
    'secret_key' => 'YOUR_SECRET_KEY',
    'test_mode'  => true,
]);
```
</details>

<details>
<summary><strong>Papara</strong></summary>

```php
$gateway = Arpay::create('papara', [
    'api_key'     => 'YOUR_API_KEY',
    'merchant_id' => 'YOUR_MERCHANT_ID',
    'test_mode'   => true,
]);
```
</details>

---

## 🐳 Demo

Arpay, tüm 9 gateway'i interaktif olarak test edebileceğiniz bir Docker demo ile birlikte gelir.

```bash
# Docker ile başlat
docker compose up --build

# veya PHP built-in server ile
php -S localhost:8043 -t demo
```

🌐 Tarayıcıda aç: **http://localhost:8043**

> ⚠️ Demo ortamı `MockHttpClient` kullanır — gerçek API çağrısı yapılmaz.

---

## 🏗️ Mimari

```
src/
├── Arpay.php                  # Ana facade — Arpay::create()
├── ArpayFactory.php           # Gateway factory
├── Contracts/                 # Interface'ler
│   ├── PayableInterface.php
│   ├── RefundableInterface.php
│   ├── QueryableInterface.php
│   ├── SecurePayableInterface.php
│   ├── SubscribableInterface.php
│   └── InstallmentQueryableInterface.php
├── DTO/                       # Veri transfer nesneleri
│   ├── PaymentRequest.php
│   ├── PaymentResponse.php
│   ├── RefundRequest.php
│   ├── RefundResponse.php
│   ├── QueryRequest.php
│   ├── QueryResponse.php
│   ├── SecurePaymentRequest.php
│   ├── SecureInitResponse.php
│   ├── SubscriptionRequest.php
│   ├── SubscriptionResponse.php
│   ├── CreditCard.php
│   ├── Customer.php
│   ├── CartItem.php
│   ├── Address.php
│   └── InstallmentInfo.php
├── Enums/                     # Sabitler
├── Exceptions/                # Özel hata sınıfları
├── Gateways/                  # 9 gateway implementasyonu
│   ├── AbstractGateway.php
│   ├── PayTR/
│   ├── Iyzico/
│   ├── Vepara/
│   ├── ParamPos/
│   ├── Ipara/
│   ├── Odeal/
│   ├── PayNet/
│   ├── PayU/
│   └── Papara/
├── Http/                      # HTTP katmanı
│   ├── HttpClientInterface.php
│   ├── GuzzleHttpClient.php
│   └── HttpResponse.php
└── Support/                   # Yardımcı sınıflar
    ├── Config.php
    ├── HashGenerator.php
    └── MoneyFormatter.php
```

---

## 🧪 Test

```bash
# Tüm testleri çalıştır
composer test

# PHPStan analizi
composer analyse

# Kod stili kontrolü
composer cs-check

# Kod stili düzeltme
composer cs-fix

# Hepsini bir seferde
composer check
```

---

## 🤝 Katkıda Bulunma

Katkılarınızı memnuniyetle karşılıyoruz! Lütfen [CONTRIBUTING.md](CONTRIBUTING.md) dosyasını inceleyin.

1. 🍴 Fork edin
2. 🌿 Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. ✅ Testleri geçirin (`composer check`)
4. 📝 Commit edin (`git commit -m 'feat: amazing feature'`)
5. 🚀 Push edin (`git push origin feature/amazing-feature`)
6. 📬 Pull Request açın

---

## 📋 Diğer Belgeler

| Belge | Açıklama |
|-------|----------|
| [docs/QUICK_START.md](docs/QUICK_START.md) | 5 dakikada başlangıç rehberi |
| [docs/API_REFERENCE.md](docs/API_REFERENCE.md) | Tüm sınıf ve metot dokümantasyonu |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Katkıda bulunma rehberi |
| [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) | Topluluk davranış kuralları |
| [SECURITY.md](SECURITY.md) | Güvenlik politikası |
| [CHANGELOG.md](CHANGELOG.md) | Değişiklik günlüğü |
| [LICENSE](LICENSE) | MIT Lisansı |

---

## 👨‍💻 Geliştirici

<table>
  <tr>
    <td align="center">
      <a href="https://www.armagangokce.com">
        <img src="https://github.com/armagan-gkc.png" width="100px;" alt="Armağan Gökce" style="border-radius:50%"/><br>
        <sub><b>Armağan Gökce</b></sub>
      </a><br>
      <sub>Full-Stack Developer • 15+ Yıl Deneyim</sub><br><br>
      <a href="https://www.armagangokce.com" title="Website">🌐</a>
      <a href="https://github.com/armagan-gkc" title="GitHub">💻</a>
      <a href="https://www.linkedin.com/in/armağan-gökce-b326432a4" title="LinkedIn">💼</a>
      <a href="https://www.instagram.com/armagan_gkc" title="Instagram">📸</a>
      <a href="mailto:ben@armagangokce.com" title="Email">📧</a>
    </td>
  </tr>
</table>

> **PHP** • **Laravel** • **Node.js** • **Vue.js** • **React** • **Docker** • **AWS**
>
> 50+ proje • Isparta, Türkiye 🇹🇷

---

## 📄 Lisans

Bu proje [MIT Lisansı](LICENSE) altında lisanslanmıştır — detaylar için `LICENSE` dosyasına bakın.

---

<p align="center">
  <sub>⭐ Bu projeyi beğendiyseniz yıldız bırakmayı unutmayın!</sub><br>
  <sub>Made with ❤️ in Türkiye 🇹🇷 by <a href="https://www.armagangokce.com">Armağan Gökce</a></sub>
</p>
