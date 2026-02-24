# 🚀 Hızlı Başlangıç Rehberi

> **5 dakikada Arpay ile ödeme almaya başlayın!**

---

## 📦 Kurulum

```bash
composer require armagangokce/arpay
```

---

## 1. Gateway Oluşturma

```php
<?php

require 'vendor/autoload.php';

use Arpay\Arpay;

// İstediğiniz gateway'i adıyla oluşturun
$gateway = Arpay::create('paytr', [
    'merchant_id'   => 'YOUR_MERCHANT_ID',
    'merchant_key'  => 'YOUR_MERCHANT_KEY',
    'merchant_salt' => 'YOUR_MERCHANT_SALT',
    'test_mode'     => true, // Sandbox modu
]);

// Gateway değiştirmek? Sadece adı ve config'i değiştirin:
$gateway = Arpay::create('iyzico', [
    'api_key'    => 'YOUR_API_KEY',
    'secret_key' => 'YOUR_SECRET_KEY',
    'test_mode'  => true,
]);
```

---

## 2. Tek Çekim Ödeme

```php
use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\CartItem;

// Kart bilgileri
$card = CreditCard::create(
    cardHolderName: 'Armağan Gökce',
    cardNumber:     '5528790000000008',
    expireMonth:    '12',
    expireYear:     '2030',
    cvv:            '123',
);

// Müşteri bilgileri
$customer = Customer::create(
    firstName: 'Armağan',
    lastName:  'Gökce',
    email:     'ben@armagangokce.com',
    phone:     '05551234567',
    ip:        '127.0.0.1',
);

// Ödeme isteği oluştur (Builder pattern)
$request = PaymentRequest::create()
    ->amount(150.00)
    ->currency('TRY')
    ->orderId('ORDER-' . time())
    ->description('Premium Paket')
    ->installmentCount(1)
    ->card($card)
    ->customer($customer)
    ->addCartItem(CartItem::create('P001', 'Premium Paket', 'Yazılım', 150.00));

// Ödeme yap!
$response = $gateway->pay($request);

if ($response->isSuccessful()) {
    echo "✅ Ödeme başarılı! İşlem No: " . $response->getTransactionId();
    echo "Tutar: " . $response->getAmount() . " TL";
} else {
    echo "❌ Hata: " . $response->getErrorMessage();
    echo "Hata Kodu: " . $response->getErrorCode();
}
```

---

## 3. İade İşlemi

```php
use Arpay\DTO\RefundRequest;

$refund = RefundRequest::create()
    ->transactionId('TXN-12345')
    ->amount(50.00)               // Kısmi iade
    ->reason('Müşteri talebi');

$response = $gateway->refund($refund);

if ($response->isSuccessful()) {
    echo "✅ İade başarılı! Tutar: " . $response->getRefundedAmount() . " TL";
} else {
    echo "❌ İade başarısız: " . $response->getErrorMessage();
}
```

---

## 4. 3D Secure Ödeme

```php
use Arpay\DTO\SecurePaymentRequest;
use Arpay\DTO\SecureCallbackData;

// Adım 1: 3D Secure başlat
$request = SecurePaymentRequest::create()
    ->amount(250.00)
    ->currency('TRY')
    ->orderId('ORDER-3D-001')
    ->card($card)
    ->customer($customer)
    ->callbackUrl('https://sitem.com/odeme/callback')
    ->successUrl('https://sitem.com/odeme/basarili')
    ->failUrl('https://sitem.com/odeme/basarisiz');

$initResponse = $gateway->initSecurePayment($request);

if ($initResponse->isRedirectRequired()) {
    // Müşteriyi banka sayfasına yönlendir
    echo $initResponse->getRedirectForm();
    exit;
}

// Adım 2: Banka dönüşünü yakala (callback URL'nizde)
$callbackData = SecureCallbackData::fromRequest($_POST);
$paymentResponse = $gateway->completeSecurePayment($callbackData);

if ($paymentResponse->isSuccessful()) {
    echo "✅ 3D Secure ödeme başarılı!";
}
```

---

## 5. Ödeme Sorgulama

```php
use Arpay\DTO\QueryRequest;

$query = QueryRequest::create()
    ->transactionId('TXN-12345')
    ->orderId('ORDER-001');

$response = $gateway->query($query);

if ($response->isSuccessful()) {
    echo "Durum: " . $response->getPaymentStatus()->value; // "successful", "pending", vb.
    echo "Tutar: " . $response->getAmount() . " TL";
}
```

---

## 6. Taksit Sorgulama

```php
// BIN numarası ile taksit seçeneklerini sorgula
$installments = $gateway->queryInstallments('552879', 1000.00);

foreach ($installments as $info) {
    echo "{$info->installmentCount} taksit: {$info->installmentAmount} TL/ay "
       . "(Toplam: {$info->totalAmount} TL, Faiz: %{$info->interestRate})\n";
}
```

---

## 7. Abonelik / Tekrarlayan Ödeme

```php
use Arpay\DTO\SubscriptionRequest;

$subscription = SubscriptionRequest::create()
    ->planName('Premium Aylık')
    ->amount(99.99)
    ->currency('TRY')
    ->period('monthly')      // daily, weekly, monthly, yearly
    ->periodInterval(1)      // Her 1 ayda bir
    ->card($card)
    ->customer($customer);

$response = $gateway->createSubscription($subscription);

if ($response->isSuccessful()) {
    echo "✅ Abonelik oluşturuldu: " . $response->getSubscriptionId();
}

// İptal
$cancelResponse = $gateway->cancelSubscription('SUB-12345');
```

---

## 🛡️ Hata Yönetimi

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
        // Gateway "başarısız" döndü ama exception fırlatmadı
        log("Ödeme reddedildi: {$response->getErrorCode()} - {$response->getErrorMessage()}");
    }
} catch (GatewayNotFoundException $e) {
    // Geçersiz gateway adı: "paytrr" gibi yazım hatası
    log("Gateway bulunamadı: " . $e->getMessage());

} catch (InvalidParameterException $e) {
    // Eksik veya geçersiz yapılandırma
    log("Parametre hatası: " . $e->getMessage());

} catch (AuthenticationException $e) {
    // API anahtarları yanlış
    log("Kimlik doğrulama: " . $e->getMessage());

} catch (NetworkException $e) {
    // Bağlantı hatası, timeout
    log("Ağ hatası: " . $e->getMessage());

} catch (PaymentFailedException $e) {
    // Kritik ödeme hatası
    log("Ödeme hatası [{$e->getErrorCode()}]: " . $e->getMessage());
    $rawResponse = $e->getRawResponse(); // Gateway ham yanıtı

} catch (UnsupportedOperationException $e) {
    // Gateway bu işlemi desteklemiyor
    log("Desteklenmeyen işlem: " . $e->getMessage());

} catch (ArpayException $e) {
    // Tüm Arpay hatalarını yakala (genel catch)
    log("Arpay hatası: " . $e->getMessage());
}
```

---

## 🔍 Gateway Özellik Kontrolü

```php
use Arpay\Contracts\PayableInterface;
use Arpay\Contracts\RefundableInterface;
use Arpay\Contracts\QueryableInterface;
use Arpay\Contracts\SecurePayableInterface;
use Arpay\Contracts\SubscribableInterface;
use Arpay\Contracts\InstallmentQueryableInterface;

$gateway = Arpay::create('papara', $config);

// Interface kontrolü ile özellik tespiti
if ($gateway instanceof PayableInterface) {
    $response = $gateway->pay($request);
}

if ($gateway instanceof SecurePayableInterface) {
    // Papara 3D Secure desteklemiyor — bu bloğa girmez
    $response = $gateway->initSecurePayment($secureRequest);
}

// Desteklenen özellikleri listele
$features = $gateway->getSupportedFeatures();
// ['pay', 'refund', 'query'] — Papara için
```

---

## 🐳 Demo ile Hızlı Test

```bash
# Docker ile
docker compose up --build
# http://localhost:8043 adresini açın

# veya PHP built-in server ile
php -S localhost:8043 -t demo
```

> Demo ortamı `MockHttpClient` kullanır — gerçek API çağrısı yapılmaz.

---

## 📖 Sonraki Adımlar

- [API Referansı](API_REFERENCE.md) — Tüm sınıflar, metotlar ve parametreler
- [README](../README.md) — 9 Gateway yapılandırma detayları
- [CONTRIBUTING.md](../CONTRIBUTING.md) — Katkıda bulunma rehberi
