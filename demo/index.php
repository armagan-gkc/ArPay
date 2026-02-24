<?php
/**
 * Arpay Demo Arayüzü
 * 
 * Tüm 9 ödeme gateway'ini MockHttpClient ile test edebileceğiniz
 * interaktif web demo. Gerçek API çağrısı yapılmaz.
 * 
 * Çalıştırma:
 *   docker compose up --build       → http://localhost:8043
 *   php -S localhost:8043 -t demo   → http://localhost:8043
 * 
 * @author Armağan Gökce
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Arpay\Arpay;
use Arpay\Contracts\InstallmentQueryableInterface;
use Arpay\Contracts\PayableInterface;
use Arpay\Contracts\QueryableInterface;
use Arpay\Contracts\RefundableInterface;
use Arpay\Contracts\SecurePayableInterface;
use Arpay\Contracts\SubscribableInterface;
use Arpay\DTO\CartItem;
use Arpay\DTO\CreditCard;
use Arpay\DTO\Customer;
use Arpay\DTO\PaymentRequest;
use Arpay\DTO\QueryRequest;
use Arpay\DTO\RefundRequest;
use Arpay\DTO\SecurePaymentRequest;
use Arpay\DTO\SubscriptionRequest;
use Arpay\Gateways\AbstractGateway;
use Arpay\Http\HttpClientInterface;
use Arpay\Http\HttpResponse;

/**
 * Demo için basit MockHttpClient.
 * autoload-dev bağımlılığı olmadan çalışır.
 */
class DemoMockHttpClient implements HttpClientInterface
{
    /** @var HttpResponse[] */
    private array $responses = [];

    public function addResponse(int $statusCode = 200, array|string $body = [], array $headers = []): self
    {
        $bodyStr = is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body;
        $this->responses[] = new HttpResponse($statusCode, $bodyStr, $headers);
        return $this;
    }

    public function post(string $url, array $headers = [], array|string $body = []): HttpResponse
    {
        return array_shift($this->responses) ?? new HttpResponse(500, '{"error":"No mock response"}');
    }

    public function get(string $url, array $headers = [], array $query = []): HttpResponse
    {
        return array_shift($this->responses) ?? new HttpResponse(500, '{"error":"No mock response"}');
    }
}

// ──────────────────────────────────────────────
// GATEWAY TANIMLARI
// ──────────────────────────────────────────────

/** @var array<string, array{name: string, config: array, features: string[]}> */
$gatewayDefs = [
    'paytr' => [
        'name' => 'PayTR',
        'config' => ['merchant_id' => 'DEMO123', 'merchant_key' => 'DEMOKEY123456', 'merchant_salt' => 'DEMOSALT12345', 'test_mode' => true],
        'features' => ['pay', 'refund', 'query', '3dsecure', 'subscription', 'installment'],
    ],
    'iyzico' => [
        'name' => 'Iyzico',
        'config' => ['api_key' => 'sandbox-demo-api-key', 'secret_key' => 'sandbox-demo-secret-key', 'test_mode' => true],
        'features' => ['pay', 'refund', 'query', '3dsecure', 'subscription', 'installment'],
    ],
    'vepara' => [
        'name' => 'Vepara',
        'config' => ['api_key' => 'demo-api-key', 'secret_key' => 'demo-secret-key', 'merchant_id' => 'DEMO001', 'test_mode' => true],
        'features' => ['pay', 'refund', 'query', '3dsecure', 'installment'],
    ],
    'parampos' => [
        'name' => 'ParamPos',
        'config' => ['client_code' => 'DEMO10001', 'client_username' => 'demo_user', 'client_password' => 'demo_pass', 'guid' => 'DEMO-GUID-1234-5678', 'test_mode' => true],
        'features' => ['pay', 'refund', 'query', '3dsecure', 'subscription', 'installment'],
    ],
    'ipara' => [
        'name' => 'iPara',
        'config' => ['public_key' => 'demo-public-key', 'private_key' => 'demo-private-key', 'test_mode' => true],
        'features' => ['pay', 'refund', 'query', '3dsecure', 'installment'],
    ],
    'odeal' => [
        'name' => 'Ödeal',
        'config' => ['api_key' => 'demo-api-key', 'secret_key' => 'demo-secret-key', 'test_mode' => true],
        'features' => ['pay', 'refund', 'query', '3dsecure'],
    ],
    'paynet' => [
        'name' => 'Paynet',
        'config' => ['secret_key' => 'demo-secret-key', 'merchant_id' => 'DEMO_MERCHANT', 'test_mode' => true],
        'features' => ['pay', 'refund', 'query', '3dsecure', 'subscription', 'installment'],
    ],
    'payu' => [
        'name' => 'PayU',
        'config' => ['merchant' => 'DEMO_MERCHANT', 'secret_key' => 'demo-secret-key', 'test_mode' => true],
        'features' => ['pay', 'refund', 'query', '3dsecure', 'subscription'],
    ],
    'papara' => [
        'name' => 'Papara',
        'config' => ['api_key' => 'demo-api-key', 'merchant_id' => 'DEMO001', 'test_mode' => true],
        'features' => ['pay', 'refund', 'query'],
    ],
];

// ──────────────────────────────────────────────
// MOCK RESPONSE HARITASI (Gateway-specific)
// ──────────────────────────────────────────────

/**
 * Her gateway'in kendi API yanıt formatına uygun mock response üretir.
 *
 * Gateway'ler farklı başarı/hata formatları kullanır:
 *   PayTR/Iyzico/Ödeal : status === 'success'
 *   Vepara             : status_code === 100  (int)
 *   ParamPos           : Sonuc === '1'
 *   iPara              : result === '1'
 *   Paynet             : is_successful === true (bool)
 *   PayU               : STATUS === 'SUCCESS' (uppercase keys)
 *   Papara             : succeeded === true (bool, nested data)
 */
function getMockResponses(string $gateway, string $action, bool $simulateFail): array
{
    $txnId = strtoupper($gateway) . '_TXN_' . substr(md5((string) microtime(true)), 0, 8);
    $subId = strtoupper($gateway) . '_SUB_' . substr(md5((string) microtime(true)), 0, 8);
    $orderId = 'ORD_' . time();

    // ─── Taksit listesi (ortak) ─────
    $installmentList = [
        ['count' => 2, 'per_amount' => 7650, 'total_amount' => 15300, 'interest_rate' => 2.0,
         'per_installment' => 76.50, 'total' => 153.00, 'rate' => 2.0,
         'installmentCount' => 2, 'installmentAmount' => 76.50, 'totalAmount' => 153.00, 'interestRate' => 2.0,
         'installmentNumber' => 2, 'installmentPrice' => 76.50, 'totalPrice' => 153.00],
        ['count' => 3, 'per_amount' => 5200, 'total_amount' => 15600, 'interest_rate' => 4.0,
         'per_installment' => 52.00, 'total' => 156.00, 'rate' => 4.0,
         'installmentCount' => 3, 'installmentAmount' => 52.00, 'totalAmount' => 156.00, 'interestRate' => 4.0,
         'installmentNumber' => 3, 'installmentPrice' => 52.00, 'totalPrice' => 156.00],
        ['count' => 6, 'per_amount' => 2750, 'total_amount' => 16500, 'interest_rate' => 10.0,
         'per_installment' => 27.50, 'total' => 165.00, 'rate' => 10.0,
         'installmentCount' => 6, 'installmentAmount' => 27.50, 'totalAmount' => 165.00, 'interestRate' => 10.0,
         'installmentNumber' => 6, 'installmentPrice' => 27.50, 'totalPrice' => 165.00],
        ['count' => 9, 'per_amount' => 1944, 'total_amount' => 17500, 'interest_rate' => 16.67,
         'per_installment' => 19.44, 'total' => 175.00, 'rate' => 16.67,
         'installmentCount' => 9, 'installmentAmount' => 19.44, 'totalAmount' => 175.00, 'interestRate' => 16.67,
         'installmentNumber' => 9, 'installmentPrice' => 19.44, 'totalPrice' => 175.00],
        ['count' => 12, 'per_amount' => 1583, 'total_amount' => 19000, 'interest_rate' => 26.67,
         'per_installment' => 15.83, 'total' => 190.00, 'rate' => 26.67,
         'installmentCount' => 12, 'installmentAmount' => 15.83, 'totalAmount' => 190.00, 'interestRate' => 26.67,
         'installmentNumber' => 12, 'installmentPrice' => 15.83, 'totalPrice' => 190.00],
    ];

    // ─── Gateway-specific response map ─────

    return match ($gateway) {

        // ━━━━━━━ PayTR: status === 'success' ━━━━━━━
        'paytr' => match (true) {
            $simulateFail => [['status_code' => 200, 'body' => match ($action) {
                'pay', 'refund', 'query' => ['status' => 'error', 'err_no' => 'INSUFFICIENT_FUNDS', 'err_msg' => 'Yetersiz bakiye. Kart limiti aşıldı.'],
                '3dsecure' => ['status' => 'error', 'err_no' => '3D_AUTH_FAILED', 'err_msg' => '3D Secure doğrulama başarısız.'],
                'subscription' => ['status' => 'error', 'err_no' => 'CARD_DECLINED', 'err_msg' => 'Kart reddedildi.'],
                'installment' => ['status' => 'error', 'err_no' => 'BIN_NOT_FOUND', 'err_msg' => 'Geçersiz BIN.'],
                default => ['error' => 'Unknown'],
            }]],
            default => [['status_code' => 200, 'body' => match ($action) {
                'pay' => ['status' => 'success', 'trans_id' => $txnId, 'merchant_oid' => $orderId],
                'refund' => ['status' => 'success'],
                'query' => ['status' => 'success', 'payment_amount' => 15000, 'payment_status' => 'success'],
                '3dsecure' => ['status' => 'success', 'token' => 'DEMO_3D_TOKEN_' . $txnId],
                'subscription' => ['status' => 'success', 'subscription_id' => $subId],
                'installment' => ['status' => 'success', 'installments' => $installmentList],
                default => ['error' => 'Unknown'],
            }]],
        },

        // ━━━━━━━ Iyzico: status === 'success' ━━━━━━━
        'iyzico' => match (true) {
            $simulateFail => [['status_code' => 200, 'body' => match ($action) {
                'pay', 'refund', 'query' => ['status' => 'failure', 'errorCode' => 'INSUFFICIENT_FUNDS', 'errorMessage' => 'Yetersiz bakiye.'],
                '3dsecure' => ['status' => 'failure', 'errorCode' => '3D_AUTH_FAILED', 'errorMessage' => '3D Secure başarısız.'],
                'subscription' => ['status' => 'failure', 'errorCode' => 'CARD_DECLINED', 'errorMessage' => 'Kart reddedildi.'],
                'installment' => ['status' => 'failure', 'errorCode' => 'BIN_NOT_FOUND', 'errorMessage' => 'Geçersiz BIN.'],
                default => ['error' => 'Unknown'],
            }]],
            default => [['status_code' => 200, 'body' => match ($action) {
                'pay' => ['status' => 'success', 'paymentId' => $txnId, 'conversationId' => $orderId, 'price' => '150.00'],
                'refund' => ['status' => 'success', 'paymentId' => $txnId],
                'query' => ['status' => 'success', 'paymentId' => $txnId, 'price' => '150.00', 'paymentStatus' => 'SUCCESS'],
                '3dsecure' => ['status' => 'success', 'threeDSHtmlContent' => base64_encode('<html><body><h1>3D Secure Demo</h1><p>Bu bir simülasyondur.</p></body></html>')],
                'subscription' => ['status' => 'success', 'data' => ['referenceCode' => $subId]],
                'installment' => ['status' => 'success', 'installmentDetails' => [['installmentPrices' => $installmentList]]],
                default => ['error' => 'Unknown'],
            }]],
        },

        // ━━━━━━━ Vepara: status_code === 100 (int) ━━━━━━━
        'vepara' => match (true) {
            $simulateFail => [['status_code' => 200, 'body' => match ($action) {
                'pay', 'refund', 'query' => ['status_code' => 200, 'status_description' => 'Yetersiz bakiye. Kart limiti aşıldı.'],
                '3dsecure' => ['status_code' => 200, 'status_description' => '3D Secure doğrulama başarısız.'],
                'installment' => ['status_code' => 200, 'status_description' => 'Geçersiz BIN.'],
                default => ['error' => 'Unknown'],
            }]],
            default => [['status_code' => 200, 'body' => match ($action) {
                'pay' => ['status_code' => 100, 'status_description' => 'Başarılı', 'transaction_id' => $txnId],
                'refund' => ['status_code' => 100, 'status_description' => 'İade başarılı', 'transaction_id' => $txnId],
                'query' => ['status_code' => 100, 'transaction_id' => $txnId, 'order_id' => $orderId, 'amount' => 150.00],
                '3dsecure' => ['status_code' => 100, 'redirect_url' => 'https://3dsecure.vepara.com.tr/auth?token=' . $txnId],
                'installment' => ['installments' => $installmentList],
                default => ['error' => 'Unknown'],
            }]],
        },

        // ━━━━━━━ ParamPos: Sonuc === '1' ━━━━━━━
        'parampos' => match (true) {
            $simulateFail => [['status_code' => 200, 'body' => match ($action) {
                'pay', 'refund', 'query' => ['Sonuc' => '0', 'Sonuc_Str' => 'INSUFFICIENT_FUNDS', 'Sonuc_Ack' => 'Yetersiz bakiye. Kart limiti aşıldı.'],
                '3dsecure' => ['Sonuc' => '0', 'Sonuc_Str' => '3D_AUTH_FAILED', 'Sonuc_Ack' => '3D Secure başarısız.'],
                'subscription' => ['Sonuc' => '0', 'Sonuc_Str' => 'CARD_DECLINED', 'Sonuc_Ack' => 'Kart reddedildi.'],
                'installment' => ['Sonuc' => '0', 'Sonuc_Str' => 'BIN_NOT_FOUND', 'Sonuc_Ack' => 'Geçersiz BIN.'],
                default => ['error' => 'Unknown'],
            }]],
            default => [['status_code' => 200, 'body' => match ($action) {
                'pay' => ['Sonuc' => '1', 'Sonuc_Str' => '00', 'Dekont_ID' => $txnId],
                'refund' => ['Sonuc' => '1', 'Dekont_ID' => $txnId],
                'query' => ['Sonuc' => '1', 'Dekont_ID' => $txnId, 'Siparis_ID' => $orderId, 'Tutar' => '150.00'],
                '3dsecure' => ['UCD_HTML' => base64_encode('<html><body><h1>ParamPos 3D Secure</h1><p>Simülasyon</p></body></html>')],
                'subscription' => ['Sonuc' => '1', 'subscription_id' => $subId],
                'installment' => ['installments' => $installmentList],
                default => ['error' => 'Unknown'],
            }]],
        },

        // ━━━━━━━ iPara: result === '1' ━━━━━━━
        'ipara' => match (true) {
            $simulateFail => [['status_code' => 200, 'body' => match ($action) {
                'pay', 'refund', 'query' => ['result' => '0', 'errorCode' => 'INSUFFICIENT_FUNDS', 'errorMessage' => 'Yetersiz bakiye. Kart limiti aşıldı.'],
                '3dsecure' => ['result' => '0', 'errorCode' => '3D_AUTH_FAILED', 'errorMessage' => '3D Secure başarısız.'],
                'installment' => ['result' => '0', 'errorCode' => 'BIN_NOT_FOUND', 'errorMessage' => 'Geçersiz BIN.'],
                default => ['error' => 'Unknown'],
            }]],
            default => [['status_code' => 200, 'body' => match ($action) {
                'pay' => ['result' => '1', 'transactionId' => $txnId, 'orderId' => $orderId],
                'refund' => ['result' => '1', 'transactionId' => $txnId],
                'query' => ['result' => '1', 'transactionId' => $txnId, 'orderId' => $orderId, 'amount' => 150.00, 'status' => '1'],
                '3dsecure' => ['threeDSecureHtml' => base64_encode('<html><body><h1>iPara 3D Secure</h1><p>Simülasyon</p></body></html>')],
                'installment' => ['installmentDetails' => $installmentList],
                default => ['error' => 'Unknown'],
            }]],
        },

        // ━━━━━━━ Ödeal: status === 'success' ━━━━━━━
        'odeal' => match (true) {
            $simulateFail => [['status_code' => 200, 'body' => match ($action) {
                'pay', 'refund', 'query' => ['status' => 'error', 'errorCode' => 'INSUFFICIENT_FUNDS', 'errorMessage' => 'Yetersiz bakiye. Kart limiti aşıldı.'],
                '3dsecure' => ['status' => 'error', 'errorCode' => '3D_AUTH_FAILED', 'errorMessage' => '3D Secure başarısız.'],
                default => ['error' => 'Unknown'],
            }]],
            default => [['status_code' => 200, 'body' => match ($action) {
                'pay' => ['status' => 'success', 'transactionId' => $txnId, 'orderId' => $orderId],
                'refund' => ['status' => 'success', 'transactionId' => $txnId],
                'query' => ['status' => 'success', 'transactionId' => $txnId, 'orderId' => $orderId, 'amount' => 150.00, 'paymentStatus' => 'approved'],
                '3dsecure' => ['threeDSecureHtml' => base64_encode('<html><body><h1>Ödeal 3D Secure</h1><p>Simülasyon</p></body></html>')],
                default => ['error' => 'Unknown'],
            }]],
        },

        // ━━━━━━━ Paynet: is_successful === true (bool) ━━━━━━━
        'paynet' => match (true) {
            $simulateFail => [['status_code' => 200, 'body' => match ($action) {
                'pay', 'refund', 'query' => ['is_successful' => false, 'code' => '99', 'message' => 'Yetersiz bakiye. Kart limiti aşıldı.'],
                '3dsecure' => ['is_successful' => false, 'code' => '3D_FAIL', 'message' => '3D Secure başarısız.'],
                'subscription' => ['is_successful' => false, 'code' => 'CARD_DECLINED', 'message' => 'Kart reddedildi.'],
                'installment' => ['is_successful' => false, 'code' => 'BIN_NOT_FOUND', 'message' => 'Geçersiz BIN.'],
                default => ['error' => 'Unknown'],
            }]],
            default => [['status_code' => 200, 'body' => match ($action) {
                'pay' => ['is_successful' => true, 'code' => '0', 'transaction_id' => $txnId, 'order_id' => $orderId],
                'refund' => ['is_successful' => true, 'transaction_id' => $txnId],
                'query' => ['is_successful' => true, 'transaction_id' => $txnId, 'order_id' => $orderId, 'amount' => 15000, 'payment_status' => 'approved'],
                '3dsecure' => ['html_content' => '<html><body><h1>Paynet 3D Secure</h1><p>Simülasyon</p></body></html>'],
                'subscription' => ['is_successful' => true, 'subscription_id' => $subId],
                'installment' => ['installment_list' => $installmentList],
                default => ['error' => 'Unknown'],
            }]],
        },

        // ━━━━━━━ PayU: STATUS === 'SUCCESS' (uppercase) ━━━━━━━
        'payu' => match (true) {
            $simulateFail => [['status_code' => 200, 'body' => match ($action) {
                'pay' => ['STATUS' => 'FAILED', 'RETURN_CODE' => 'INSUFFICIENT_FUNDS', 'RETURN_MESSAGE' => 'Yetersiz bakiye. Kart limiti aşıldı.'],
                'refund' => ['RESPONSE_CODE' => '99', 'STATUS' => 'FAILED', 'RESPONSE_MSG' => 'İade başarısız.'],
                'query' => ['RESPONSE_CODE' => '99', 'RESPONSE_MSG' => 'İşlem bulunamadı.'],
                '3dsecure' => ['STATUS' => 'FAILED', 'RETURN_CODE' => '3D_FAIL', 'RETURN_MESSAGE' => '3D Secure başarısız.'],
                'subscription' => ['STATUS' => 'FAILED', 'RETURN_CODE' => 'CARD_DECLINED', 'RETURN_MESSAGE' => 'Kart reddedildi.'],
                default => ['error' => 'Unknown'],
            }]],
            default => [['status_code' => 200, 'body' => match ($action) {
                'pay' => ['STATUS' => 'SUCCESS', 'RETURN_CODE' => 'AUTHORIZED', 'REFNO' => $txnId, 'ORDER_REF' => $orderId],
                'refund' => ['RESPONSE_CODE' => '0', 'STATUS' => 'SUCCESS', 'IRN_REFNO' => $txnId],
                'query' => ['ORDER_REF' => $orderId, 'REFNO' => $txnId, 'ORDER_AMOUNT' => '150.00', 'ORDER_STATUS' => 'PAYMENT_AUTHORIZED'],
                '3dsecure' => ['URL_3DS' => 'https://3dsecure.payu.com.tr/auth?token=' . $txnId],
                'subscription' => ['STATUS' => 'SUCCESS', 'IPN_CC_TOKEN' => $subId],
                default => ['error' => 'Unknown'],
            }]],
        },

        // ━━━━━━━ Papara: succeeded === true (bool, nested data) ━━━━━━━
        'papara' => match (true) {
            $simulateFail => [['status_code' => 200, 'body' => match ($action) {
                'pay', 'refund', 'query' => ['succeeded' => false, 'error' => ['code' => 'INSUFFICIENT_FUNDS', 'message' => 'Yetersiz bakiye. İşlem reddedildi.']],
                default => ['error' => 'Unknown'],
            }]],
            default => [['status_code' => 200, 'body' => match ($action) {
                'pay' => ['succeeded' => true, 'data' => ['id' => $txnId, 'paymentId' => $txnId, 'referenceId' => $orderId]],
                'refund' => ['succeeded' => true, 'data' => ['id' => $txnId]],
                'query' => ['succeeded' => true, 'data' => ['id' => $txnId, 'referenceId' => $orderId, 'amount' => 150.00, 'status' => 1]],
                default => ['error' => 'Unknown'],
            }]],
        },

        // ━━━━━━━ Fallback (bilinmeyen gateway) ━━━━━━━
        default => [['status_code' => 500, 'body' => ['error' => 'Tanımsız gateway: ' . $gateway]]],
    };
}

// ──────────────────────────────────────────────
// AJAX HANDLER
// ──────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $gwKey = $_POST['gateway'] ?? 'paytr';
        $action = $_POST['action'] ?? 'pay';
        $simulateFail = ($_POST['simulate_fail'] ?? '0') === '1';

        if (!isset($gatewayDefs[$gwKey])) {
            echo json_encode(['error' => true, 'message' => 'Geçersiz gateway: ' . $gwKey]);
            exit;
        }

        $def = $gatewayDefs[$gwKey];

        // Gateway oluştur
        $gateway = Arpay::create($gwKey, $def['config']);

        // MockHttpClient enjekte et
        $mockClient = new DemoMockHttpClient();
        $mockResponses = getMockResponses($gwKey, $action, $simulateFail);
        foreach ($mockResponses as $resp) {
            $mockClient->addResponse($resp['status_code'], $resp['body']);
        }
        if ($gateway instanceof AbstractGateway) {
            $gateway->setHttpClient($mockClient);
        }

        $result = [];

        switch ($action) {
            case 'pay':
                if (!($gateway instanceof PayableInterface)) {
                    throw new \RuntimeException('Bu gateway ödeme desteklemiyor.');
                }
                $card = CreditCard::create(
                    $_POST['card_holder'] ?? 'Test Kullanıcı',
                    $_POST['card_number'] ?? '4111111111111111',
                    $_POST['expire_month'] ?? '12',
                    $_POST['expire_year'] ?? '2028',
                    $_POST['cvv'] ?? '123'
                );
                $customer = Customer::create(
                    $_POST['first_name'] ?? 'Demo',
                    $_POST['last_name'] ?? 'Kullanıcı',
                    $_POST['email'] ?? 'demo@arpay.dev',
                    $_POST['phone'] ?? '05551234567',
                    '127.0.0.1'
                );
                $request = PaymentRequest::create()
                    ->amount((float) ($_POST['amount'] ?? 150.00))
                    ->currency($_POST['currency'] ?? 'TRY')
                    ->orderId('ORD_' . time())
                    ->description($_POST['description'] ?? 'Demo ödeme')
                    ->installmentCount((int) ($_POST['installment_count'] ?? 1))
                    ->card($card)
                    ->customer($customer)
                    ->addCartItem(CartItem::create('ITEM_1', 'Demo Ürün', 'Genel', (float) ($_POST['amount'] ?? 150.00)));

                $response = $gateway->pay($request);
                $result = [
                    'success' => $response->isSuccessful(),
                    'type' => 'PaymentResponse',
                    'data' => [
                        'transaction_id' => $response->getTransactionId(),
                        'order_id' => $response->getOrderId(),
                        'amount' => $response->getAmount(),
                        'status' => $response->getPaymentStatus()->value,
                        'error_code' => $response->getErrorCode(),
                        'error_message' => $response->getErrorMessage(),
                    ],
                    'raw' => $response->getRawResponse(),
                ];
                break;

            case 'refund':
                if (!($gateway instanceof RefundableInterface)) {
                    throw new \RuntimeException('Bu gateway iade desteklemiyor.');
                }
                $request = RefundRequest::create()
                    ->transactionId($_POST['txn_id'] ?? 'TXN_DEMO_123')
                    ->amount((float) ($_POST['refund_amount'] ?? 50.00))
                    ->reason($_POST['reason'] ?? 'Müşteri talebi');

                $response = $gateway->refund($request);
                $result = [
                    'success' => $response->isSuccessful(),
                    'type' => 'RefundResponse',
                    'data' => [
                        'transaction_id' => $response->getTransactionId(),
                        'refunded_amount' => $response->getRefundedAmount(),
                        'error_code' => $response->getErrorCode(),
                        'error_message' => $response->getErrorMessage(),
                    ],
                    'raw' => $response->getRawResponse(),
                ];
                break;

            case 'query':
                if (!($gateway instanceof QueryableInterface)) {
                    throw new \RuntimeException('Bu gateway sorgu desteklemiyor.');
                }
                $request = QueryRequest::create()
                    ->transactionId($_POST['query_txn_id'] ?? 'TXN_DEMO_123');

                $response = $gateway->query($request);
                $result = [
                    'success' => $response->isSuccessful(),
                    'type' => 'QueryResponse',
                    'data' => [
                        'transaction_id' => $response->getTransactionId(),
                        'order_id' => $response->getOrderId(),
                        'amount' => $response->getAmount(),
                        'status' => $response->getPaymentStatus()->value,
                        'error_code' => $response->getErrorCode(),
                        'error_message' => $response->getErrorMessage(),
                    ],
                    'raw' => $response->getRawResponse(),
                ];
                break;

            case '3dsecure':
                if (!($gateway instanceof SecurePayableInterface)) {
                    throw new \RuntimeException('Bu gateway 3D Secure desteklemiyor.');
                }
                $card = CreditCard::create(
                    $_POST['card_holder'] ?? 'Test Kullanıcı',
                    $_POST['card_number'] ?? '4111111111111111',
                    $_POST['expire_month'] ?? '12',
                    $_POST['expire_year'] ?? '2028',
                    $_POST['cvv'] ?? '123'
                );
                $customer = Customer::create(
                    $_POST['first_name'] ?? 'Demo',
                    $_POST['last_name'] ?? 'Kullanıcı',
                    $_POST['email'] ?? 'demo@arpay.dev',
                    $_POST['phone'] ?? '05551234567',
                    '127.0.0.1'
                );
                $request = SecurePaymentRequest::create()
                    ->amount((float) ($_POST['amount'] ?? 150.00))
                    ->currency($_POST['currency'] ?? 'TRY')
                    ->orderId('ORD_3D_' . time())
                    ->description('3D Secure Demo Ödeme')
                    ->card($card)
                    ->customer($customer)
                    ->callbackUrl($_POST['callback_url'] ?? 'https://demo.arpay.dev/callback')
                    ->successUrl($_POST['success_url'] ?? 'https://demo.arpay.dev/success')
                    ->failUrl($_POST['fail_url'] ?? 'https://demo.arpay.dev/fail')
                    ->addCartItem(CartItem::create('ITEM_1', 'Demo Ürün', 'Genel', (float) ($_POST['amount'] ?? 150.00)));

                $response = $gateway->initSecurePayment($request);
                $result = [
                    'success' => $response->isRedirectRequired() || !$response->getErrorCode(),
                    'type' => 'SecureInitResponse',
                    'data' => [
                        'redirect_required' => $response->isRedirectRequired(),
                        'redirect_url' => $response->getRedirectUrl(),
                        'error_code' => $response->getErrorCode(),
                        'error_message' => $response->getErrorMessage(),
                    ],
                    'raw' => $response->getRawResponse(),
                ];
                break;

            case 'subscription':
                if (!($gateway instanceof SubscribableInterface)) {
                    throw new \RuntimeException('Bu gateway abonelik desteklemiyor.');
                }
                $card = CreditCard::create(
                    $_POST['card_holder'] ?? 'Test Kullanıcı',
                    $_POST['card_number'] ?? '4111111111111111',
                    $_POST['expire_month'] ?? '12',
                    $_POST['expire_year'] ?? '2028',
                    $_POST['cvv'] ?? '123'
                );
                $customer = Customer::create(
                    $_POST['first_name'] ?? 'Demo',
                    $_POST['last_name'] ?? 'Kullanıcı',
                    $_POST['email'] ?? 'demo@arpay.dev'
                );
                $request = SubscriptionRequest::create()
                    ->planName($_POST['plan_name'] ?? 'Premium Aylık')
                    ->amount((float) ($_POST['sub_amount'] ?? 99.99))
                    ->currency($_POST['currency'] ?? 'TRY')
                    ->period($_POST['period'] ?? 'monthly')
                    ->periodInterval((int) ($_POST['period_interval'] ?? 1))
                    ->card($card)
                    ->customer($customer);

                $response = $gateway->createSubscription($request);
                $result = [
                    'success' => $response->isSuccessful(),
                    'type' => 'SubscriptionResponse',
                    'data' => [
                        'subscription_id' => $response->getSubscriptionId(),
                        'status' => $response->getStatus(),
                        'error_code' => $response->getErrorCode(),
                        'error_message' => $response->getErrorMessage(),
                    ],
                    'raw' => $response->getRawResponse(),
                ];
                break;

            case 'installment':
                if (!($gateway instanceof InstallmentQueryableInterface)) {
                    throw new \RuntimeException('Bu gateway taksit sorgulama desteklemiyor.');
                }
                $bin = $_POST['bin'] ?? '411111';
                $amount = (float) ($_POST['inst_amount'] ?? 150.00);

                $installments = $gateway->queryInstallments($bin, $amount);
                $instData = [];
                foreach ($installments as $info) {
                    $instData[] = [
                        'count' => $info->installmentCount,
                        'per_installment' => $info->installmentAmount,
                        'total' => $info->totalAmount,
                        'interest_rate' => $info->interestRate,
                    ];
                }
                $result = [
                    'success' => count($installments) > 0,
                    'type' => 'InstallmentInfo[]',
                    'data' => ['installments' => $instData],
                    'raw' => $mockResponses[0]['body'] ?? [],
                ];
                break;

            default:
                throw new \RuntimeException('Bilinmeyen işlem: ' . $action);
        }

        $result['gateway'] = $def['name'];
        $result['action'] = $action;
        $result['timestamp'] = date('Y-m-d H:i:s');

        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'class' => get_class($e),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    exit;
}

// ──────────────────────────────────────────────
// HTML ARAYÜZ
// ──────────────────────────────────────────────

$features = json_encode(array_map(fn($d) => $d['features'], $gatewayDefs));
$gatewayNames = json_encode(array_map(fn($d) => $d['name'], $gatewayDefs));
?>
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arpay Demo — Türk Ödeme Altyapıları Test Arayüzü</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        .gateway-card { transition: all 0.2s ease; }
        .gateway-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -5px rgba(0,0,0,0.15); }
        .gateway-card.active { outline: 2px solid #6366f1; outline-offset: 2px; }
        .tab-btn.active { border-bottom: 3px solid #6366f1; color: #6366f1; font-weight: 600; }
        .tab-btn:disabled { opacity: 0.35; cursor: not-allowed; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        pre code { font-size: 0.8rem; line-height: 1.6; }
        .result-card { backdrop-filter: blur(8px); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 3px; }
        ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 3px; }
    </style>
    <script>
    tailwind.config = {
        theme: { extend: { colors: {
            primary: { 50:'#eef2ff',100:'#e0e7ff',200:'#c7d2fe',300:'#a5b4fc',400:'#818cf8',500:'#6366f1',600:'#4f46e5',700:'#4338ca',800:'#3730a3',900:'#312e81' }
        }}}
    }
    </script>
</head>
<body class="h-full bg-gray-50 text-gray-800">

<!-- HEADER -->
<header class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-primary-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
            </div>
            <div>
                <h1 class="text-lg font-bold text-gray-900">Arpay <span class="text-primary-600">Demo</span></h1>
                <p class="text-xs text-gray-500">v<?= Arpay::VERSION ?> — Birleşik Ödeme Altyapısı</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="badge bg-amber-100 text-amber-800 text-xs px-3 py-1">
                ⚠ Demo Ortamı — Gerçek İşlem Yapılmaz
            </span>
            <a href="https://github.com/armagangokce/arpay" target="_blank" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
            </a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6">
<div class="grid grid-cols-12 gap-6">

<!-- SOL PANEL: GATEWAY SEÇİCİ -->
<aside class="col-span-12 lg:col-span-3">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Gateway Seç</h2>
        <div class="space-y-2" id="gateway-list">
            <?php foreach ($gatewayDefs as $key => $def): ?>
            <button
                onclick="selectGateway('<?= $key ?>')"
                id="gw-<?= $key ?>"
                class="gateway-card w-full text-left px-3 py-2.5 rounded-lg border-2 transition
                       <?= $key === 'paytr' ? 'border-primary-500 bg-primary-50' : 'border-gray-100 bg-white hover:border-gray-200' ?>"
            >
                <div class="flex items-center justify-between">
                    <span class="font-semibold text-sm"><?= $def['name'] ?></span>
                    <span class="text-[10px] text-gray-400 font-mono"><?= $key ?></span>
                </div>
                <div class="flex flex-wrap gap-1 mt-1.5">
                    <?php
                    $featureColors = [
                        'pay' => 'bg-green-100 text-green-700',
                        'refund' => 'bg-blue-100 text-blue-700',
                        'query' => 'bg-cyan-100 text-cyan-700',
                        '3dsecure' => 'bg-purple-100 text-purple-700',
                        'subscription' => 'bg-orange-100 text-orange-700',
                        'installment' => 'bg-pink-100 text-pink-700',
                    ];
                    $featureLabels = [
                        'pay' => 'Ödeme', 'refund' => 'İade', 'query' => 'Sorgu',
                        '3dsecure' => '3D', 'subscription' => 'Abone', 'installment' => 'Taksit',
                    ];
                    foreach ($def['features'] as $f):
                    ?>
                    <span class="badge <?= $featureColors[$f] ?? 'bg-gray-100 text-gray-600' ?>"><?= $featureLabels[$f] ?? $f ?></span>
                    <?php endforeach; ?>
                </div>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- BİLGİ KUTUSU -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mt-4">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Nasıl Çalışır?</h3>
        <ul class="text-xs text-gray-600 space-y-1.5">
            <li>• Gateway seç, formu doldur, gönder</li>
            <li>• <strong>MockHttpClient</strong> sahte API yanıtı döner</li>
            <li>• Gerçek API çağrısı yapılmaz</li>
            <li>• Hata senaryosu: "Başarısız" toggle'ını aç</li>
            <li>• Kod örneğini projenize kopyalayın</li>
        </ul>
    </div>
</aside>

<!-- SAĞ PANEL: İŞLEM ALANI -->
<section class="col-span-12 lg:col-span-9">

    <!-- TAB BAR -->
    <div class="bg-white rounded-t-xl border border-gray-200 border-b-0 flex overflow-x-auto">
        <button onclick="selectTab('pay')" data-tab="pay" class="tab-btn px-5 py-3 text-sm font-medium text-gray-600 hover:text-primary-600 border-b-3 border-transparent whitespace-nowrap active">
            💳 Ödeme
        </button>
        <button onclick="selectTab('refund')" data-tab="refund" class="tab-btn px-5 py-3 text-sm font-medium text-gray-600 hover:text-primary-600 border-b-3 border-transparent whitespace-nowrap">
            ↩ İade
        </button>
        <button onclick="selectTab('query')" data-tab="query" class="tab-btn px-5 py-3 text-sm font-medium text-gray-600 hover:text-primary-600 border-b-3 border-transparent whitespace-nowrap">
            🔍 Sorgu
        </button>
        <button onclick="selectTab('3dsecure')" data-tab="3dsecure" class="tab-btn px-5 py-3 text-sm font-medium text-gray-600 hover:text-primary-600 border-b-3 border-transparent whitespace-nowrap">
            🔒 3D Secure
        </button>
        <button onclick="selectTab('subscription')" data-tab="subscription" class="tab-btn px-5 py-3 text-sm font-medium text-gray-600 hover:text-primary-600 border-b-3 border-transparent whitespace-nowrap">
            🔄 Abonelik
        </button>
        <button onclick="selectTab('installment')" data-tab="installment" class="tab-btn px-5 py-3 text-sm font-medium text-gray-600 hover:text-primary-600 border-b-3 border-transparent whitespace-nowrap">
            📊 Taksit
        </button>
    </div>

    <!-- FORM ALANI -->
    <div class="bg-white rounded-b-xl shadow-sm border border-gray-200 p-6">

        <!-- BAŞARISIZ TOGGLE -->
        <div class="flex items-center justify-between mb-5 pb-4 border-b border-gray-100">
            <div>
                <span class="text-sm font-semibold text-gray-700" id="tab-title">Ödeme İşlemi</span>
                <span class="text-xs text-gray-400 ml-2" id="gateway-label">PayTR</span>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <span class="text-xs text-gray-500">Başarısız Senaryo</span>
                <div class="relative">
                    <input type="checkbox" id="simulate-fail" class="sr-only peer">
                    <div class="w-9 h-5 bg-gray-200 peer-checked:bg-red-500 rounded-full transition"></div>
                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-4 shadow"></div>
                </div>
            </label>
        </div>

        <!-- ÖDEME FORMU -->
        <div id="form-pay" class="tab-form">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Kart Bilgileri</h3>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kart Sahibi</label>
                    <input type="text" name="card_holder" value="Test Kullanıcı" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kart Numarası</label>
                    <input type="text" name="card_number" value="4111 1111 1111 1111" maxlength="19" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Ay</label>
                        <input type="text" name="expire_month" value="12" maxlength="2" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-center font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Yıl</label>
                        <input type="text" name="expire_year" value="2028" maxlength="4" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-center font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">CVV</label>
                        <input type="text" name="cvv" value="123" maxlength="4" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-center font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tutar (₺)</label>
                    <input type="number" name="amount" value="150.00" step="0.01" min="1" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                </div>

                <div class="md:col-span-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Müşteri Bilgileri</h3>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Ad</label>
                    <input type="text" name="first_name" value="Demo" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Soyad</label>
                    <input type="text" name="last_name" value="Kullanıcı" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">E-posta</label>
                    <input type="email" name="email" value="demo@arpay.dev" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Telefon</label>
                    <input type="tel" name="phone" value="05551234567" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Taksit Sayısı</label>
                    <select name="installment_count" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                        <option value="1">Tek Çekim</option>
                        <option value="2">2 Taksit</option>
                        <option value="3">3 Taksit</option>
                        <option value="6">6 Taksit</option>
                        <option value="9">9 Taksit</option>
                        <option value="12">12 Taksit</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Açıklama</label>
                    <input type="text" name="description" value="Demo ödeme" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
        </div>

        <!-- İADE FORMU -->
        <div id="form-refund" class="tab-form hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">İşlem ID</label>
                    <input type="text" name="txn_id" value="TXN_DEMO_123" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">İade Tutarı (₺)</label>
                    <input type="number" name="refund_amount" value="50.00" step="0.01" min="0.01" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">İade Sebebi</label>
                    <input type="text" name="reason" value="Müşteri talebi" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
        </div>

        <!-- SORGU FORMU -->
        <div id="form-query" class="tab-form hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">İşlem ID</label>
                    <input type="text" name="query_txn_id" value="TXN_DEMO_123" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
        </div>

        <!-- 3D SECURE FORMU -->
        <div id="form-3dsecure" class="tab-form hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Kart Bilgileri</h3>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kart Sahibi</label>
                    <input type="text" name="card_holder" value="Test Kullanıcı" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kart Numarası</label>
                    <input type="text" name="card_number" value="4111 1111 1111 1111" maxlength="19" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Ay</label>
                        <input type="text" name="expire_month" value="12" maxlength="2" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-center font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Yıl</label>
                        <input type="text" name="expire_year" value="2028" maxlength="4" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-center font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">CVV</label>
                        <input type="text" name="cvv" value="123" maxlength="4" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-center font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tutar (₺)</label>
                    <input type="number" name="amount" value="150.00" step="0.01" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div class="md:col-span-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Callback URL'leri</h3>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Callback URL</label>
                    <input type="url" name="callback_url" value="https://demo.arpay.dev/callback" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Başarılı URL</label>
                    <input type="url" name="success_url" value="https://demo.arpay.dev/success" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Başarısız URL</label>
                    <input type="url" name="fail_url" value="https://demo.arpay.dev/fail" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>

                <div class="md:col-span-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Müşteri Bilgileri</h3>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Ad</label>
                    <input type="text" name="first_name" value="Demo" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Soyad</label>
                    <input type="text" name="last_name" value="Kullanıcı" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">E-posta</label>
                    <input type="email" name="email" value="demo@arpay.dev" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
        </div>

        <!-- ABONELİK FORMU -->
        <div id="form-subscription" class="tab-form hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Plan Bilgileri</h3>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Plan Adı</label>
                    <input type="text" name="plan_name" value="Premium Aylık" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tutar (₺)</label>
                    <input type="number" name="sub_amount" value="99.99" step="0.01" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Periyot</label>
                    <select name="period" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                        <option value="daily">Günlük</option>
                        <option value="weekly">Haftalık</option>
                        <option value="monthly" selected>Aylık</option>
                        <option value="yearly">Yıllık</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Periyot Aralığı</label>
                    <input type="number" name="period_interval" value="1" min="1" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div class="md:col-span-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Kart Bilgileri</h3>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kart Sahibi</label>
                    <input type="text" name="card_holder" value="Test Kullanıcı" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kart Numarası</label>
                    <input type="text" name="card_number" value="4111 1111 1111 1111" maxlength="19" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Ay</label>
                        <input type="text" name="expire_month" value="12" maxlength="2" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-center font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Yıl</label>
                        <input type="text" name="expire_year" value="2028" maxlength="4" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-center font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">CVV</label>
                        <input type="text" name="cvv" value="123" maxlength="4" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm text-center font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                </div>
                <div class="md:col-span-2 mt-2">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Müşteri Bilgileri</h3>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Ad</label>
                    <input type="text" name="first_name" value="Demo" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Soyad</label>
                    <input type="text" name="last_name" value="Kullanıcı" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">E-posta</label>
                    <input type="email" name="email" value="demo@arpay.dev" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
        </div>

        <!-- TAKSİT FORMU -->
        <div id="form-installment" class="tab-form hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">BIN Numarası (ilk 6 hane)</label>
                    <input type="text" name="bin" value="411111" maxlength="6" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tutar (₺)</label>
                    <input type="number" name="inst_amount" value="150.00" step="0.01" min="1" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
        </div>

        <!-- GÖNDER BUTONU -->
        <div class="mt-6 flex items-center gap-3">
            <button onclick="submitForm()" id="submit-btn" class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-lg shadow-sm transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span id="submit-label">Ödeme Yap</span>
            </button>
            <div id="loading" class="hidden flex items-center gap-2 text-sm text-gray-500">
                <svg class="animate-spin w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                İşlem simüle ediliyor...
            </div>
        </div>
    </div>

    <!-- SONUÇ PANELİ -->
    <div id="result-panel" class="hidden mt-4 fade-in">
        <!-- Status Banner -->
        <div id="result-status" class="rounded-t-xl px-5 py-3 flex items-center gap-3">
            <div id="status-icon" class="w-8 h-8 rounded-full flex items-center justify-center"></div>
            <div>
                <div id="status-text" class="font-semibold text-sm"></div>
                <div id="status-time" class="text-xs opacity-75"></div>
            </div>
        </div>

        <!-- Result Data -->
        <div class="bg-white border border-gray-200 border-t-0 p-5">
            <div id="result-cards" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4"></div>

            <!-- Raw JSON -->
            <details class="mt-4">
                <summary class="text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700">
                    Ham JSON Yanıt
                </summary>
                <pre class="mt-2 bg-gray-900 text-green-400 rounded-lg p-4 overflow-x-auto text-xs"><code id="raw-json"></code></pre>
            </details>
        </div>

        <!-- KOD ÖRNEĞİ -->
        <div class="bg-gray-900 rounded-b-xl p-5 border border-gray-200 border-t-0">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">PHP Kod Örneği</span>
                <button onclick="copyCode()" class="text-xs text-primary-400 hover:text-primary-300 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"></path></svg>
                    Kopyala
                </button>
            </div>
            <pre class="overflow-x-auto text-xs"><code id="code-example" class="text-gray-300"></code></pre>
        </div>
    </div>

</section>
</div>
</main>

<!-- FOOTER -->
<footer class="border-t border-gray-200 mt-8 py-4 text-center text-xs text-gray-400">
    Arpay v<?= Arpay::VERSION ?> — Demo Arayüzü &copy; <?= date('Y') ?> Armağan Gökce
    &middot; Bu arayüz yalnızca test amaçlıdır, gerçek ödeme işlemi yapılmaz.
</footer>

<script>
const features = <?= $features ?>;
const gatewayNames = <?= $gatewayNames ?>;
</script>
<script src="app.js"></script>
</body>
</html>
