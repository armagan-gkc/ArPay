<?php

declare(strict_types=1);

namespace Arpay\DTO;

use Arpay\Enums\PaymentStatus;

/**
 * Ödeme sonucu veri nesnesi.
 *
 * Gateway'den dönen ödeme sonucunu standart bir yapıda sunar.
 * Başarı durumu, işlem numarası, hata bilgileri ve ham yanıta erişim sağlar.
 *
 * @author Armağan Gökce
 */
class PaymentResponse implements \JsonSerializable
{
    /**
     * @param bool          $successful    Ödeme başarılı mı?
     * @param string        $transactionId Gateway'in döndürdüğü işlem numarası
     * @param string        $orderId       Sipariş numarası
     * @param float         $amount        Ödenen tutar
     * @param PaymentStatus $status        Ödeme durumu
     * @param string        $errorCode     Hata kodu (başarısızsa)
     * @param string        $errorMessage  Hata mesajı (başarısızsa)
     * @param array         $rawResponse   Gateway'den gelen ham yanıt
     */
    public function __construct(
        protected readonly bool $successful,
        protected readonly string $transactionId = '',
        protected readonly string $orderId = '',
        protected readonly float $amount = 0.0,
        protected readonly PaymentStatus $status = PaymentStatus::Pending,
        protected readonly string $errorCode = '',
        protected readonly string $errorMessage = '',
        protected readonly array $rawResponse = [],
    ) {
    }

    /**
     * Başarılı ödeme yanıtı oluşturur.
     */
    public static function successful(
        string $transactionId,
        string $orderId = '',
        float $amount = 0.0,
        array $rawResponse = [],
    ): self {
        return new self(
            successful: true,
            transactionId: $transactionId,
            orderId: $orderId,
            amount: $amount,
            status: PaymentStatus::Successful,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Başarısız ödeme yanıtı oluşturur.
     */
    public static function failed(
        string $errorCode = '',
        string $errorMessage = '',
        array $rawResponse = [],
    ): self {
        return new self(
            successful: false,
            status: PaymentStatus::Failed,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Ödemenin başarılı olup olmadığını döndürür.
     */
    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    /**
     * Gateway tarafından atanan işlem numarasını döndürür.
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * Sipariş numarasını döndürür.
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * Ödenen tutarı döndürür.
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Ödeme durumunu döndürür.
     */
    public function getPaymentStatus(): PaymentStatus
    {
        return $this->status;
    }

    /**
     * Hata kodunu döndürür (başarısız ise).
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Hata mesajını döndürür (başarısız ise).
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * Gateway'den gelen ham yanıtı döndürür.
     *
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * Yanıtı dizi olarak döndürür.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'successful'     => $this->successful,
            'transaction_id' => $this->transactionId,
            'order_id'       => $this->orderId,
            'amount'         => $this->amount,
            'status'         => $this->status->value,
            'error_code'     => $this->errorCode,
            'error_message'  => $this->errorMessage,
            'raw_response'   => $this->rawResponse,
        ];
    }

    /**
     * JSON serileştirme desteği.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
