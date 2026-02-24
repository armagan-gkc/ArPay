<?php

declare(strict_types=1);

namespace Arpay\Contracts;

use Arpay\DTO\SubscriptionRequest;
use Arpay\DTO\SubscriptionResponse;

/**
 * Abonelik / tekrarlayan ödeme yeteneği arayüzü.
 *
 * Tekrarlayan ödeme planları oluşturabilen ve yönetebilen
 * gateway'ler bu arayüzü implement eder.
 *
 * @author Armağan Gökce
 */
interface SubscribableInterface
{
    /**
     * Yeni abonelik / tekrarlayan ödeme planı oluşturur.
     *
     * @param SubscriptionRequest $request Abonelik istek bilgileri
     * @return SubscriptionResponse Abonelik sonucu
     */
    public function createSubscription(SubscriptionRequest $request): SubscriptionResponse;

    /**
     * Mevcut bir aboneliği iptal eder.
     *
     * @param string $subscriptionId Abonelik kimlik numarası
     * @return SubscriptionResponse İptal sonucu
     */
    public function cancelSubscription(string $subscriptionId): SubscriptionResponse;
}
