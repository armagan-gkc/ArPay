<?php

declare(strict_types=1);

namespace Arpay\DTO;

/**
 * 3D Secure başlatma sonucu veri nesnesi.
 *
 * 3D Secure ödeme başlatıldığında gateway'den dönen
 * yönlendirme bilgilerini içerir. Müşteriyi banka
 * sayfasına yönlendirmek için gerekli HTML formu veya
 * URL'yi sağlar.
 *
 * @author Armağan Gökce
 */
class SecureInitResponse implements \JsonSerializable
{
    /**
     * @param bool $redirectRequired Yönlendirme gerekli mi?
     * @param string $redirectUrl Yönlendirme URL'si
     * @param string $htmlForm Otomatik gönderimli HTML form kodu
     * @param array $formData Form POST parametreleri
     * @param string $errorCode Hata kodu (başarısızsa)
     * @param string $errorMessage Hata mesajı (başarısızsa)
     * @param array $rawResponse Gateway ham yanıtı
     */
    public function __construct(
        protected readonly bool $redirectRequired,
        protected readonly string $redirectUrl = '',
        protected readonly string $htmlForm = '',
        protected readonly array $formData = [],
        protected readonly string $errorCode = '',
        protected readonly string $errorMessage = '',
        protected readonly array $rawResponse = [],
    ) {}

    /**
     * Yönlendirme gerektiren başarılı yanıt oluşturur.
     *
     * @param string $redirectUrl Banka yönlendirme URL'si
     * @param array $formData POST form verileri
     * @param array $rawResponse Gateway ham yanıtı
     */
    public static function redirect(
        string $redirectUrl,
        array $formData = [],
        array $rawResponse = [],
    ): self {
        // Otomatik gönderimli HTML formu oluştur
        $html = self::buildAutoSubmitForm($redirectUrl, $formData);

        return new self(
            redirectRequired: true,
            redirectUrl: $redirectUrl,
            htmlForm: $html,
            formData: $formData,
            rawResponse: $rawResponse,
        );
    }

    /**
     * HTML içeriği olarak dönen yanıt oluşturur.
     *
     * Bazı gateway'ler (PayTR gibi) doğrudan HTML içerik döndürür.
     */
    public static function html(string $htmlContent, array $rawResponse = []): self
    {
        return new self(
            redirectRequired: true,
            htmlForm: $htmlContent,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Başarısız 3D başlatma yanıtı oluşturur.
     */
    public static function failed(
        string $errorCode = '',
        string $errorMessage = '',
        array $rawResponse = [],
    ): self {
        return new self(
            redirectRequired: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Yönlendirme gerekli mi?
     */
    public function isRedirectRequired(): bool
    {
        return $this->redirectRequired;
    }

    /**
     * Yönlendirme URL'sini döndürür.
     */
    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    /**
     * Otomatik gönderimli HTML form kodunu döndürür.
     *
     * Bu değer doğrudan echo edilebilir:
     * ```php
     * echo $response->getRedirectForm();
     * ```
     */
    public function getRedirectForm(): string
    {
        return $this->htmlForm;
    }

    /**
     * POST form verilerini döndürür.
     *
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        return $this->formData;
    }

    /**
     * Hata kodunu döndürür (başarısızsa).
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Hata mesajını döndürür (başarısızsa).
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
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
            'redirect_required' => $this->redirectRequired,
            'redirect_url' => $this->redirectUrl,
            'html_form' => $this->htmlForm,
            'form_data' => $this->formData,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'raw_response' => $this->rawResponse,
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

    /**
     * Otomatik gönderimli HTML form kodu oluşturur.
     *
     * JavaScript ile form otomatik olarak submit edilir.
     * Müşteri bir süre "Yönlendiriliyor..." mesajı görür.
     *
     * @param string $url Form action URL
     * @param array<string, mixed> $formData POST alanları
     *
     * @return string HTML form kodu
     */
    protected static function buildAutoSubmitForm(string $url, array $formData): string
    {
        $inputs = '';
        foreach ($formData as $name => $value) {
            $safeName = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
            $safeValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $inputs .= "<input type=\"hidden\" name=\"{$safeName}\" value=\"{$safeValue}\">\n";
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <title>3D Secure Yönlendirme</title>
        </head>
        <body>
            <p style="text-align:center; margin-top:50px; font-family:sans-serif;">
                Banka sayfasına yönlendiriliyorsunuz, lütfen bekleyin...
            </p>
            <form id="arpay_3d_form" method="POST" action="{$url}">
                {$inputs}
            </form>
            <script>document.getElementById('arpay_3d_form').submit();</script>
        </body>
        </html>
        HTML;
    }
}
