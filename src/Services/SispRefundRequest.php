<?php

namespace Erilshk\Vinti4Pay\Services;

use Erilshk\Vinti4Pay\Exceptions\Vinti4Exception;
use Erilshk\Vinti4Pay\Services\SispPaymentRequest;

/**
 * Class Vinti4RefundRequest
 *
 * Responsável por preparar, validar e renderizar requisições de estorno (refund/reversal) Vinti4Net.
 *
 * @author Eril
 * @version 1.0.0
 */
class SispRefundRequest extends SispPaymentRequest
{
    // protected string $posID;
    // protected string $posAuthCode;
    // protected string $endpoint;

    public string $language = 'pt';

    public const REQUEST_FINGERPRINT_FIELD = 'fingerprint';


    public function __construct(string $posID, string $posAuthCode, ?string $endpoint = null)
    {
        parent::__construct($posID, $posAuthCode, $endpoint);
    }

    /**
     * Prepara uma requisição de estorno completa, pronta para renderização.
     *
     * @param string $merchantRef
     * @param string $merchantSession
     * @param float|string $amount
     * @param string $clearingPeriod
     * @param string $transactionID
     * @param string $responseUrl
     * @param string $language
     * @return array{postUrl:string,fields:array}
     * @throws Vinti4Exception
     */
    public function prepareRefund(
        string $merchantRef,
        string $merchantSession,
        float|string $amount,
        string $clearingPeriod,
        string $transactionID,
        string $responseUrl,
        string $language = 'pt'
    ): array {
        if (empty($merchantRef) || empty($merchantSession) || empty($amount) || empty($transactionID) || empty($responseUrl)) {
            throw new Vinti4Exception("Missing required refund parameters.");
        }

        $fields = [
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'posID' => $this->posID,
            'merchantRef' => $merchantRef,
            'merchantSession' => $merchantSession,
            'amount' => (float)$amount,
            'currency' => self::CURRENCY_CVE,
            'is3DSec' => 1,
            'urlMerchantResponse' => $responseUrl,
            'languageMessages' => $language,
            'timeStamp' => date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'entityCode' => '',
            'referenceNumber' => '',
            'reversal' => 'R',
            'clearingPeriod' => $clearingPeriod,
            'transactionID' => $transactionID,
        ];

        // Gera fingerprint conforme SISP oficial
        $fields[self::REQUEST_FINGERPRINT_FIELD] = $this->generateFingerprint($fields);

        $postUrl = $this->endpoint . '?' . http_build_query([
            'FingerPrint' => $fields[self::REQUEST_FINGERPRINT_FIELD],
            'TimeStamp' => $fields['timeStamp'],
            'FingerPrintVersion' => $fields['fingerprintversion'],
        ]);

        return ['postUrl' => $postUrl, 'fields' => $fields];
    }

    /**
     * Renderiza um formulário HTML auto-submissível para estorno.
     */
    public function renderRefundForm(array $refundData): string
    {
        $htmlFields = '';
        $postUrl = htmlspecialchars($refundData['postUrl'], ENT_QUOTES, 'UTF-8');

        foreach ($refundData['fields'] as $key => $value) {
            $htmlFields .= "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>";
        }

        return "
        <html>
            <head><title>Vinti4 Refund</title></head>
            <body onload='document.forms[0].submit()'>
                <form action='{$postUrl}' method='post'>
                    {$htmlFields}
                </form>
                <p>Processando estorno... Aguarde.</p>
            </body>
        </html>";
    }


    /**
     * Gera fingerprint da requisição de estorno.
     */
    protected function generateFingerprint(array $data): string
    {
        // Baseado no método GerarFingerPrintEnvio() da implementação oficial
        $posAuthHash = base64_encode(hash('sha512', $this->posAuthCode, true));

        // amount precisa ser convertido em inteiro * 1000
        $amount = (int)((float)$data['amount'] * 1000);

        $entityCode = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
        $referenceNumber = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';

        $toHash = $posAuthHash
            . $data['timeStamp']
            . $amount
            . $data['merchantRef']
            . $data['merchantSession']
            . $data['posID']
            . $data['currency']
            . $data['transactionCode']
            . $entityCode
            . $referenceNumber;

        return base64_encode(hash('sha512', $toHash, true));
    }

}
