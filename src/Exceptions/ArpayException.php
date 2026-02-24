<?php

declare(strict_types=1);

namespace Arpay\Exceptions;

/**
 * Arpay kütüphanesinin temel hata sınıfı.
 *
 * Tüm Arpay hataları bu sınıftan türer. Genel bir
 * catch bloğu ile tüm Arpay hatalarını yakalayabilirsiniz:
 *
 * ```php
 * try {
 *     $response = $gateway->pay($request);
 * } catch (ArpayException $e) {
 *     // Tüm Arpay hatalarını yakala
 * }
 * ```
 *
 * @author Armağan Gökce
 */
abstract class ArpayException extends \Exception {}
