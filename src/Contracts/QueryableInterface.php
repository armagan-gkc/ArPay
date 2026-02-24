<?php

declare(strict_types=1);

namespace Arpay\Contracts;

use Arpay\DTO\QueryRequest;
use Arpay\DTO\QueryResponse;

/**
 * Ödeme sorgulama yeteneği arayüzü.
 *
 * Daha önce yapılmış bir ödeme işleminin durumunu
 * sorgulayabilen gateway'ler bu arayüzü implement eder.
 *
 * @author Armağan Gökce
 */
interface QueryableInterface
{
    /**
     * İşlem durumunu sorgular.
     *
     * @param QueryRequest $request Sorgu istek bilgileri (sipariş no veya işlem no)
     *
     * @return QueryResponse Sorgu sonucu
     */
    public function query(QueryRequest $request): QueryResponse;
}
