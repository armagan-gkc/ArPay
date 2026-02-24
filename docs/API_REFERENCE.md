# 📖 API Referansı

> Arpay kütüphanesinin tüm sınıf, metot ve parametre dokümantasyonu.

---

## İçindekiler

- [Arpay (Facade)](#arpay-facade)
- [DTO Sınıfları](#dto-sınıfları)
  - [PaymentRequest](#paymentrequest)
  - [PaymentResponse](#paymentresponse)
  - [RefundRequest](#refundrequest)
  - [RefundResponse](#refundresponse)
  - [QueryRequest](#queryrequest)
  - [QueryResponse](#queryresponse)
  - [SecurePaymentRequest](#securepaymentrequest)
  - [SecureInitResponse](#secureinitresponse)
  - [SecureCallbackData](#securecallbackdata)
  - [SubscriptionRequest](#subscriptionrequest)
  - [SubscriptionResponse](#subscriptionresponse)
  - [CreditCard](#creditcard)
  - [Customer](#customer)
  - [CartItem](#cartitem)
  - [Address](#address)
  - [InstallmentInfo](#installmentinfo)
- [Interface'ler (Contracts)](#interfaceler-contracts)
- [Enum'lar](#enumlar)
- [Exception'lar](#exceptionlar)
- [Support Sınıfları](#support-sınıfları)

---

## Arpay (Facade)

**Namespace:** `Arpay\Arpay`

Kütüphanenin ana giriş noktası. Tüm gateway işlemleri bu facade üzerinden başlatılır.

### Metotlar

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `create(string $gateway, array $config)` | `GatewayInterface` | Gateway oluşturur ve yapılandırır |
| `getAvailableGateways()` | `string[]` | Desteklenen gateway adlarını döndürür |
| `version()` | `string` | Kütüphane sürümünü döndürür |

```php
use Arpay\Arpay;

// Gateway oluştur
$gateway = Arpay::create('paytr', [
    'merchant_id'   => '123456',
    'merchant_key'  => 'XXXXX',
    'merchant_salt' => 'YYYYY',
    'test_mode'     => true,
]);

// Mevcut gateway'leri listele
$gateways = Arpay::getAvailableGateways();
// ['paytr', 'iyzico', 'vepara', 'parampos', 'ipara', 'odeal', 'paynet', 'payu', 'papara']

// Sürüm
echo Arpay::version(); // "1.0.0"
```

---

## DTO Sınıfları

### PaymentRequest

**Namespace:** `Arpay\DTO\PaymentRequest`

Ödeme talebi — Builder pattern ile zincirleme oluşturulur.

#### Builder Metotları

| Metot | Parametre | Açıklama |
|-------|-----------|----------|
| `create()` | — | Yeni builder başlatır (static) |
| `amount(float)` | `$amount` | Ödeme tutarı (TL) |
| `currency(string\|Currency)` | `$currency` | Para birimi (`'TRY'`, `'USD'`, `'EUR'`, `'GBP'`) |
| `orderId(string)` | `$orderId` | Benzersiz sipariş numarası |
| `description(string)` | `$description` | Ödeme açıklaması |
| `installmentCount(int)` | `$count` | Taksit sayısı (1 = tek çekim) |
| `card(CreditCard)` | `$card` | Kart bilgileri |
| `customer(Customer)` | `$customer` | Müşteri bilgileri |
| `billingAddress(Address)` | `$address` | Fatura adresi |
| `shippingAddress(Address)` | `$address` | Teslimat adresi |
| `addCartItem(CartItem)` | `$item` | Sepete ürün ekler |
| `cartItems(array)` | `$items` | Tüm sepet ürünlerini ayarlar |
| `meta(string, mixed)` | `$key, $value` | Gateway'e özel ek parametre |

#### Getter Metotları

| Metot | Dönüş Tipi |
|-------|-----------|
| `getAmount()` | `float` |
| `getCurrency()` | `string` |
| `getOrderId()` | `string` |
| `getDescription()` | `string` |
| `getInstallmentCount()` | `int` |
| `getCard()` | `?CreditCard` |
| `getCustomer()` | `?Customer` |
| `getBillingAddress()` | `?Address` |
| `getShippingAddress()` | `?Address` |
| `getCartItems()` | `CartItem[]` |
| `getMetadata()` | `array<string, mixed>` |
| `getMeta(string, mixed)` | `mixed` |

---

### PaymentResponse

**Namespace:** `Arpay\DTO\PaymentResponse`

Ödeme sonucu — `JsonSerializable` implementasyonu ile JSON dönüşüm desteği.

#### Factory Metotları

| Metot | Açıklama |
|-------|----------|
| `successful(string $transactionId, string $orderId, float $amount, array $rawResponse)` | Başarılı yanıt oluşturur |
| `failed(string $errorCode, string $errorMessage, array $rawResponse)` | Başarısız yanıt oluşturur |

#### Getter Metotları

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `isSuccessful()` | `bool` | Ödeme başarılı mı? |
| `getTransactionId()` | `string` | Gateway işlem numarası |
| `getOrderId()` | `string` | Sipariş numarası |
| `getAmount()` | `float` | Ödenen tutar |
| `getPaymentStatus()` | `PaymentStatus` | Durum enum'u |
| `getErrorCode()` | `string` | Hata kodu |
| `getErrorMessage()` | `string` | Hata mesajı |
| `getRawResponse()` | `array` | Gateway ham yanıtı |
| `toArray()` | `array` | Dizi dönüşümü |

---

### RefundRequest

**Namespace:** `Arpay\DTO\RefundRequest`

İade talebi — tam veya kısmi iade.

#### Builder Metotları

| Metot | Parametre | Açıklama |
|-------|-----------|----------|
| `create()` | — | Yeni builder başlatır |
| `transactionId(string)` | `$transactionId` | Gateway işlem numarası |
| `orderId(string)` | `$orderId` | Sipariş numarası |
| `amount(float)` | `$amount` | İade tutarı (kısmi iade için düşük tutar) |
| `reason(string)` | `$reason` | İade nedeni |
| `meta(string, mixed)` | `$key, $value` | Ek parametre |

---

### RefundResponse

**Namespace:** `Arpay\DTO\RefundResponse`

İade sonucu.

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `isSuccessful()` | `bool` | İade başarılı mı? |
| `getTransactionId()` | `string` | İade işlem numarası |
| `getRefundedAmount()` | `float` | İade edilen tutar |
| `getErrorCode()` | `string` | Hata kodu |
| `getErrorMessage()` | `string` | Hata mesajı |
| `getRawResponse()` | `array` | Gateway ham yanıtı |

---

### QueryRequest

**Namespace:** `Arpay\DTO\QueryRequest`

Ödeme sorgulama talebi.

| Metot | Parametre | Açıklama |
|-------|-----------|----------|
| `create()` | — | Yeni builder başlatır |
| `transactionId(string)` | `$transactionId` | Gateway işlem numarası ile sorgula |
| `orderId(string)` | `$orderId` | Sipariş numarası ile sorgula |
| `meta(string, mixed)` | `$key, $value` | Ek parametre |

---

### QueryResponse

**Namespace:** `Arpay\DTO\QueryResponse`

Ödeme sorgulama sonucu.

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `isSuccessful()` | `bool` | Sorgu başarılı mı? |
| `getTransactionId()` | `string` | İşlem numarası |
| `getOrderId()` | `string` | Sipariş numarası |
| `getAmount()` | `float` | İşlem tutarı |
| `getPaymentStatus()` | `PaymentStatus` | Ödeme durumu |
| `getErrorCode()` | `string` | Hata kodu |
| `getErrorMessage()` | `string` | Hata mesajı |
| `getRawResponse()` | `array` | Gateway ham yanıtı |

---

### SecurePaymentRequest

**Namespace:** `Arpay\DTO\SecurePaymentRequest`

3D Secure ödeme talebi — `PaymentRequest`'i extend eder.

#### Ek Builder Metotları

| Metot | Parametre | Açıklama |
|-------|-----------|----------|
| `callbackUrl(string)` | `$url` | Banka dönüş URL'si (POST verileri buraya gelir) |
| `successUrl(string)` | `$url` | Başarılı ödeme sonrası yönlendirme |
| `failUrl(string)` | `$url` | Başarısız ödeme sonrası yönlendirme |

#### Ek Getter Metotları

| Metot | Dönüş Tipi |
|-------|-----------|
| `getCallbackUrl()` | `string` |
| `getSuccessUrl()` | `string` |
| `getFailUrl()` | `string` |

> **Not:** `PaymentRequest`'in tüm metotları da kullanılabilir (`amount()`, `card()`, `customer()`, vb.)

---

### SecureInitResponse

**Namespace:** `Arpay\DTO\SecureInitResponse`

3D Secure başlatma sonucu — yönlendirme bilgileri.

#### Factory Metotları

| Metot | Açıklama |
|-------|----------|
| `redirect(string $redirectUrl, array $formData, array $rawResponse)` | Yönlendirme gerektiren yanıt |
| `html(string $htmlContent, array $rawResponse)` | HTML içerikli yanıt (PayTR gibi) |
| `failed(string $errorCode, string $errorMessage, array $rawResponse)` | Başarısız yanıt |

#### Getter Metotları

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `isRedirectRequired()` | `bool` | Yönlendirme gerekli mi? |
| `getRedirectUrl()` | `string` | Yönlendirme URL'si |
| `getRedirectForm()` | `string` | Otomatik gönderimli HTML form |
| `getFormData()` | `array` | Form POST parametreleri |
| `getErrorCode()` | `string` | Hata kodu |
| `getErrorMessage()` | `string` | Hata mesajı |
| `getRawResponse()` | `array` | Gateway ham yanıtı |

---

### SecureCallbackData

**Namespace:** `Arpay\DTO\SecureCallbackData`

Banka 3D dönüş verileri sarmalayıcısı.

| Metot | Açıklama |
|-------|----------|
| `fromRequest(array $postData)` | `$_POST` veya `$request->all()` ile oluştur |
| `toArray()` | Tüm verileri dizi olarak döndür |
| `get(string $key, mixed $default)` | Belirli bir değeri al |
| `has(string $key)` | Anahtarın varlığını kontrol et |

```php
// Laravel
$callback = SecureCallbackData::fromRequest($request->all());

// Vanilla PHP
$callback = SecureCallbackData::fromRequest($_POST);
```

---

### SubscriptionRequest

**Namespace:** `Arpay\DTO\SubscriptionRequest`

Abonelik talebi.

| Metot | Parametre | Açıklama |
|-------|-----------|----------|
| `create()` | — | Yeni builder başlatır |
| `planName(string)` | `$name` | Plan adı |
| `amount(float)` | `$amount` | Periyodik tutar |
| `currency(string)` | `$currency` | Para birimi |
| `period(string)` | `$period` | `'daily'`, `'weekly'`, `'monthly'`, `'yearly'` |
| `periodInterval(int)` | `$interval` | Periyot aralığı (ör: 3 = 3 ayda bir) |
| `card(CreditCard)` | `$card` | Kart bilgileri |
| `customer(Customer)` | `$customer` | Müşteri bilgileri |
| `meta(string, mixed)` | `$key, $value` | Ek parametre |

---

### SubscriptionResponse

**Namespace:** `Arpay\DTO\SubscriptionResponse`

Abonelik sonucu.

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `isSuccessful()` | `bool` | İşlem başarılı mı? |
| `getSubscriptionId()` | `string` | Abonelik kimliği |
| `getStatus()` | `string` | Abonelik durumu (`'active'`, vb.) |
| `getErrorCode()` | `string` | Hata kodu |
| `getErrorMessage()` | `string` | Hata mesajı |
| `getRawResponse()` | `array` | Gateway ham yanıtı |

---

### CreditCard

**Namespace:** `Arpay\DTO\CreditCard`

Kredi kartı bilgileri — Luhn doğrulaması ve BIN algılama dahil.

#### Oluşturma

```php
$card = CreditCard::create(
    cardHolderName: 'Armağan Gökce',
    cardNumber:     '5528790000000008',  // Boşluk/tire otomatik temizlenir
    expireMonth:    '12',                // 2 haneye standartlaşır
    expireYear:     '2030',              // 2 hane verilirse 20XX'e çevrilir
    cvv:            '123',
);
```

#### Metotlar

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `create(...)` | `self` | Named argument ile kart oluşturur (static) |
| `isValid()` | `bool` | Luhn algoritmasıyla doğrulama |
| `getBin()` | `string` | İlk 6 hane (BIN) |
| `getCardType()` | `?CardType` | Visa, MasterCard, Troy, Amex algılama |
| `getMaskedNumber()` | `string` | `552879******0008` formatı |
| `luhnCheck(string)` | `bool` | Statik Luhn doğrulaması |

#### Public Properties

| Property | Tip | Açıklama |
|----------|-----|----------|
| `$cardHolderName` | `string` | Kart sahibi adı |
| `$cardNumber` | `string` | Kart numarası (temizlenmiş) |
| `$expireMonth` | `string` | Son kullanma ayı (01-12) |
| `$expireYear` | `string` | Son kullanma yılı (4 hane) |
| `$cvv` | `string` | Güvenlik kodu |

---

### Customer

**Namespace:** `Arpay\DTO\Customer`

Müşteri bilgileri.

```php
$customer = Customer::create(
    firstName:      'Armağan',
    lastName:       'Gökce',
    email:          'ben@armagangokce.com',
    phone:          '05551234567',
    ip:             '127.0.0.1',          // Boşsa $_SERVER['REMOTE_ADDR'] kullanılır
    identityNumber: '11111111111',         // Opsiyonel (TC kimlik)
);
```

| Property | Tip | Zorunlu | Açıklama |
|----------|-----|:-------:|----------|
| `$firstName` | `string` | ✅ | Ad |
| `$lastName` | `string` | ✅ | Soyad |
| `$email` | `string` | ✅ | E-posta |
| `$phone` | `string` | — | Telefon |
| `$ip` | `string` | — | IP adresi |
| `$identityNumber` | `string` | — | TC kimlik no |

| Metot | Açıklama |
|-------|----------|
| `getFullName()` | `"Armağan Gökce"` |

---

### CartItem

**Namespace:** `Arpay\DTO\CartItem`

Sepet ürünü — Iyzico gibi gateway'ler zorunlu tutar.

```php
$item = CartItem::create(
    id:       'P001',
    name:     'Premium Paket',
    category: 'Yazılım',
    price:    150.00,
    quantity: 1,          // Varsayılan: 1
);
```

| Property | Tip | Açıklama |
|----------|-----|----------|
| `$id` | `string` | Ürün kodu |
| `$name` | `string` | Ürün adı |
| `$category` | `string` | Kategori |
| `$price` | `float` | Birim fiyat |
| `$quantity` | `int` | Adet (varsayılan: 1) |

| Metot | Açıklama |
|-------|----------|
| `getTotalPrice()` | Birim fiyat × adet |

---

### Address

**Namespace:** `Arpay\DTO\Address`

Fatura/teslimat adresi.

```php
$address = Address::create(
    address:  'Atatürk Mah. Cumhuriyet Cad. No:1',
    city:     'Isparta',
    district: 'Merkez',
    zipCode:  '32000',
    country:  'Turkey',   // Varsayılan: 'Turkey'
);
```

| Property | Tip | Zorunlu | Açıklama |
|----------|-----|:-------:|----------|
| `$address` | `string` | ✅ | Adres satırı |
| `$city` | `string` | ✅ | Şehir |
| `$district` | `string` | — | İlçe |
| `$zipCode` | `string` | — | Posta kodu |
| `$country` | `string` | — | Ülke |

---

### InstallmentInfo

**Namespace:** `Arpay\DTO\InstallmentInfo`

Taksit bilgisi — `queryInstallments()` sonucunda döner.

| Property | Tip | Açıklama |
|----------|-----|----------|
| `$installmentCount` | `int` | Taksit sayısı |
| `$installmentAmount` | `float` | Taksit başına tutar |
| `$totalAmount` | `float` | Toplam tutar (faiz dahil) |
| `$interestRate` | `float` | Faiz oranı (%) |

---

## Interface'ler (Contracts)

Gateway'lerin hangi işlemleri desteklediğini belirleyen arayüzler.

### GatewayInterface

Tüm gateway'lerin temel arayüzü.

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `getName()` | `string` | Gateway görünen adı ("PayTR") |
| `getShortName()` | `string` | Gateway kısa kodu ("paytr") |
| `configure(Config)` | `static` | Yapılandırma uygula |
| `getSupportedFeatures()` | `string[]` | Desteklenen özellikler |
| `setTestMode(bool)` | `static` | Test modu aç/kapat |
| `isTestMode()` | `bool` | Test modunda mı? |

### PayableInterface

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `pay(PaymentRequest)` | `PaymentResponse` | Tek çekim ödeme |
| `payInstallment(PaymentRequest)` | `PaymentResponse` | Taksitli ödeme |

### RefundableInterface

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `refund(RefundRequest)` | `RefundResponse` | Tam/kısmi iade |

### QueryableInterface

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `query(QueryRequest)` | `QueryResponse` | İşlem durumu sorgula |

### SecurePayableInterface

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `initSecurePayment(SecurePaymentRequest)` | `SecureInitResponse` | 3D Secure başlat |
| `completeSecurePayment(SecureCallbackData)` | `PaymentResponse` | 3D Secure tamamla |

### SubscribableInterface

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `createSubscription(SubscriptionRequest)` | `SubscriptionResponse` | Abonelik oluştur |
| `cancelSubscription(string)` | `SubscriptionResponse` | Abonelik iptal et |

### InstallmentQueryableInterface

| Metot | Dönüş Tipi | Açıklama |
|-------|-----------|----------|
| `queryInstallments(string $bin, float $amount)` | `InstallmentInfo[]` | Taksit seçeneklerini sorgula |

---

## Gateway Özellik Matrisi

| Gateway | Payable | Refundable | Queryable | SecurePayable | Subscribable | InstallmentQueryable |
|---------|:-------:|:----------:|:---------:|:-------------:|:------------:|:--------------------:|
| PayTR | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Iyzico | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Vepara | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| ParamPos | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| iPara | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| Ödeal | ✅ | ✅ | ✅ | ✅ | — | — |
| Paynet | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| PayU | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| Papara | ✅ | ✅ | ✅ | — | — | — |

---

## Enum'lar

### Gateway

**Namespace:** `Arpay\Enums\Gateway`

```php
Gateway::PayTR      // 'paytr'
Gateway::Iyzico     // 'iyzico'
Gateway::Vepara     // 'vepara'
Gateway::ParamPos   // 'parampos'
Gateway::Ipara      // 'ipara'
Gateway::Odeal      // 'odeal'
Gateway::Paynet     // 'paynet'
Gateway::PayU       // 'payu'
Gateway::Papara     // 'papara'

// Kısa addan enum'a dönüştürme
$gw = Gateway::fromShortName('paytr'); // Gateway::PayTR

// Görünen ad
$gw->displayName(); // "PayTR"
```

### Currency

**Namespace:** `Arpay\Enums\Currency`

```php
Currency::TRY  // 'TRY' — Türk Lirası
Currency::USD  // 'USD' — Amerikan Doları
Currency::EUR  // 'EUR' — Euro
Currency::GBP  // 'GBP' — İngiliz Sterlini
```

### PaymentStatus

**Namespace:** `Arpay\Enums\PaymentStatus`

```php
PaymentStatus::Successful  // 'successful'
PaymentStatus::Failed      // 'failed'
PaymentStatus::Pending     // 'pending'
PaymentStatus::Cancelled   // 'cancelled'
PaymentStatus::Refunded    // 'refunded'
```

### CardType

**Namespace:** `Arpay\Enums\CardType`

```php
CardType::Visa        // 'visa'
CardType::MasterCard  // 'mastercard'
CardType::Troy        // 'troy'
CardType::Amex        // 'amex'

// BIN'den otomatik algılama
CardType::detectFromBin('552879'); // CardType::MasterCard
CardType::detectFromBin('411111'); // CardType::Visa
```

### TransactionType

**Namespace:** `Arpay\Enums\TransactionType`

```php
TransactionType::Single        // 'single'
TransactionType::Installment   // 'installment'
TransactionType::Refund        // 'refund'
TransactionType::Secure3D      // 'secure3d'
TransactionType::Subscription  // 'subscription'
TransactionType::PreAuth       // 'preauth'
```

---

## Exception'lar

Tüm hatalar `ArpayException` soyut sınıfından türer.

```
ArpayException (abstract)
├── GatewayNotFoundException        — Geçersiz gateway adı
├── InvalidParameterException       — Eksik/geçersiz parametre
├── AuthenticationException         — API anahtarları yanlış
├── NetworkException                — Bağlantı/HTTP hatası
├── PaymentFailedException          — Kritik ödeme hatası
└── UnsupportedOperationException   — Desteklenmeyen işlem
```

### ArpayException

Temel hata sınıfı (abstract). Tüm Arpay hatalarını tek catch ile yakalamak için:

```php
try {
    $response = $gateway->pay($request);
} catch (ArpayException $e) {
    log($e->getMessage());
}
```

### GatewayNotFoundException

```php
// "paytrr" gibi yazım hatası
Arpay::create('paytrr', $config);
// throws: "Gateway bulunamadı: 'paytrr'"
```

### InvalidParameterException

```php
// Zorunlu config eksik
Arpay::create('paytr', ['merchant_id' => '123']);
// throws: "Geçersiz parametre: 'merchant_key' — Bu yapılandırma alanı zorunludur."
```

Constructor: `new InvalidParameterException(string $field, string $message = '')`

### AuthenticationException

```php
// Geçersiz API anahtarı
// throws: "Kimlik doğrulama hatası: API key geçersiz"
```

### NetworkException

```php
// API sunucusuna ulaşılamıyor
// throws: "Ağ hatası: Connection timeout"
```

Constructor: `new NetworkException(string $message, int $code, ?Throwable $previous)`

### PaymentFailedException

```php
try {
    $response = $gateway->pay($request);
} catch (PaymentFailedException $e) {
    $e->getErrorCode();    // Gateway hata kodu
    $e->getMessage();      // Hata mesajı
    $e->getRawResponse();  // Gateway ham yanıtı (array)
}
```

### UnsupportedOperationException

```php
// Papara 3D Secure desteklemiyor
$papara->initSecurePayment($request);
// throws: "'Papara' gateway'i '3dsecure' işlemini desteklemiyor."
```

---

## Support Sınıfları

### Config

**Namespace:** `Arpay\Support\Config`

Gateway yapılandırma yönetimi — magic erişim destekli.

```php
$config = new Config([
    'merchant_id'  => '123456',
    'api_key'      => 'XXXXX',
    'test_mode'    => true,
]);

// Standart erişim
$config->get('merchant_id');                // '123456'
$config->get('missing_key', 'default');     // 'default'

// Magic erişim
$config->merchant_id;                       // '123456'

// Kontroller
$config->has('api_key');                    // true
$config->toArray();                         // Tüm değerler

// Zorunlu alan doğrulaması
$config->validateRequired(['merchant_id', 'api_key']);
// Eksik varsa InvalidParameterException fırlatır
```

### HashGenerator

**Namespace:** `Arpay\Support\HashGenerator`

Hash/imza oluşturma yardımcıları — gateway'ler tarafından dahili olarak kullanılır.

| Metot | Açıklama |
|-------|----------|
| `hmacSha256(string $data, string $key)` | HMAC-SHA256 (hex) |
| `hmacSha512(string $data, string $key)` | HMAC-SHA512 (hex) |
| `hmacSha256Base64(string $data, string $key)` | HMAC-SHA256 + Base64 |
| `sha256(string $data)` | SHA256 (anahtarsız, hex) |
| `sha1(string $data)` | SHA1 (anahtarsız, hex) |
| `base64Encode(string $data)` | Base64 kodla |
| `base64Decode(string $data)` | Base64 çöz |

### MoneyFormatter

**Namespace:** `Arpay\Support\MoneyFormatter`

Para birimi dönüşüm yardımcıları — gateway'ler farklı formatlar kullanır.

| Metot | Örnek | Açıklama |
|-------|-------|----------|
| `toPenny(float $amount)` | `150.00 → 15000` | TL'den kuruşa (PayTR) |
| `toDecimal(int $penny)` | `15000 → "150.00"` | Kuruştan TL string'e |
| `toDecimalString(float $amount)` | `150.0 → "150.00"` | Float'u 2 ondalıklı string'e |
| `toFloat(int\|string $amount)` | `15000 → 150.0` | Kuruş veya string'den float'a |

---

## AbstractGateway

**Namespace:** `Arpay\Gateways\AbstractGateway`

Tüm gateway implementasyonlarının temel sınıfı. Özel gateway yazımı için:

```php
use Arpay\Gateways\AbstractGateway;
use Arpay\Contracts\PayableInterface;

class MyGateway extends AbstractGateway implements PayableInterface
{
    public function getName(): string
    {
        return 'My Gateway';
    }

    public function getShortName(): string
    {
        return 'mygateway';
    }

    public function getSupportedFeatures(): array
    {
        return ['pay', 'refund'];
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['api_key', 'secret_key'];
    }

    protected function getBaseUrl(): string
    {
        return 'https://api.mygateway.com';
    }

    protected function getTestBaseUrl(): string
    {
        return 'https://sandbox.mygateway.com';
    }

    public function pay(PaymentRequest $request): PaymentResponse
    {
        $url = $this->getActiveBaseUrl() . '/payment';
        $response = $this->httpClient->post($url, [...]);
        // ...
    }
}
```

### Korumalı Metotlar

| Metot | Açıklama |
|-------|----------|
| `getActiveBaseUrl()` | Test/canlı moduna göre doğru URL |
| `ensureSupports(string $feature)` | Desteklenmeyen özellikte exception fırlatır |

### Public Metotlar

| Metot | Açıklama |
|-------|----------|
| `setHttpClient(HttpClientInterface $client)` | Özel HTTP istemci (test için) |
| `getConfig()` | Mevcut yapılandırmayı döndürür |

---

## Daha Fazla Bilgi

- [Hızlı Başlangıç](QUICK_START.md) — 5 dakikada çalışan örnek
- [README](../README.md) — Proje genel bakış ve gateway yapılandırmaları
- [CONTRIBUTING.md](../CONTRIBUTING.md) — Katkıda bulunma rehberi
