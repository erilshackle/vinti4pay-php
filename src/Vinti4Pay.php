<?php

namespace Erilshk\Vinti4Pay;

use Erilshk\Vinti4Pay\Models\ResponseResult;
use Erilshk\Vinti4Pay\Exceptions\Vinti4Exception;
use Erilshk\Vinti4Pay\Traits\PurchaseRequestTrait;


class Vinti4Pay
{
    use PurchaseRequestTrait;

    private string $posID;
    private string $posAuthCode;
    private string $baseUrl;
    protected string $language = 'pt';

    const DEFAULT_ENDPOINT = "https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment";

    const TRANSACTION_TYPE_PURCHASE = '1';
    const TRANSACTION_TYPE_SERVICE_PAYMENT = '2';
    const TRANSACTION_TYPE_RECHARGE = '3';
    const TRANSACTION_TYPE_REFUND = '4';

    const CURRENCY_CVE = '132';
    const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];
    private string $rederectingMessage;

    public function __construct(string $posID, string $posAutCode, ?string $baseUrl = null)
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAutCode;
        $this->baseUrl = $baseUrl ?? self::DEFAULT_ENDPOINT;
    }

    // -------------------------
    // 🔐 Fingerprints
    // -------------------------
    private function generateRequestFingerprint(array $data, string $type = 'payment'): string
    {
        $encodedPOSAuthCode = base64_encode(hash('sha512', $this->posAuthCode, true));

        if ($type === 'payment') {
            // Pagamentos / Serviços / Recharge
            $amount = (float)($data['amount'] ?? 0);
            $amountLong = (int) bcmul($amount, '1000', 0);

            $entity = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
            $reference = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';

            $toHash = $encodedPOSAuthCode .
                ($data['timeStamp'] ?? '') .
                $amountLong .
                ($data['merchantRef'] ?? '') .
                ($data['merchantSession'] ?? '') .
                ($data['posID'] ?? '') .
                ($data['currency'] ?? '') .
                ($data['transactionCode'] ?? '') .
                $entity .
                $reference;
        } else {
            // Refund
            $amount = (float)($data['amount'] ?? 0);
            $amountLong = (int) bcmul($amount, '1000', 0);

            $toHash = $encodedPOSAuthCode .
                ($data['transactionCode'] ?? '') .
                ($data['posID'] ?? '') .
                ($data['merchantRef'] ?? '') .
                ($data['merchantSession'] ?? '') .
                $amountLong .
                ($data['currency'] ?? '') .
                ($data['clearingPeriod'] ?? '') .
                ($data['transactionID'] ?? '') .
                ($data['reversal'] ?? '') .
                ($data['urlMerchantResponse'] ?? '') .
                ($data['languageMessages'] ?? '') .
                ($data['fingerPrintVersion'] ?? '') .
                ($data['timeStamp'] ?? '');
        }

        return base64_encode(hash('sha512', $toHash, true));
    }


    private function generateResponseFingerprint(array $data, string $type = 'payment'): string
    {
        $posAuthCode = $this->posAuthCode;

        // 🔐 Chave base
        $encodedPOSAuthCode = base64_encode(hash('sha512', $posAuthCode, true));

        if ($type === 'payment') {
            // Campos de pagamento / serviço / recharge
            $amount = (float)($data["merchantRespPurchaseAmount"] ?? 0);
            $amountLong = (int) bcmul($amount, '1000', 0);

            $toHash =
                $encodedPOSAuthCode .
                ($data["messageType"] ?? '') .
                ($data["merchantRespCP"] ?? '') .
                ($data["merchantRespTid"] ?? '') .
                ($data["merchantRespMerchantRef"] ?? '') .
                ($data["merchantRespMerchantSession"] ?? '') .
                $amountLong .
                ($data["merchantRespMessageID"] ?? '') .
                ($data["merchantRespPan"] ?? '') .
                ($data["merchantResp"] ?? '') .
                ($data["merchantRespTimeStamp"] ?? '') .
                (!empty($data['merchantRespReferenceNumber']) ? (int)$data['merchantRespReferenceNumber'] : '') .
                (!empty($data['merchantRespEntityCode']) ? (int)$data['merchantRespEntityCode'] : '') .
                ($data["merchantRespClientReceipt"] ?? '') .
                trim($data["merchantRespAdditionalErrorMessage"] ?? '') .
                ($data["merchantRespReloadCode"] ?? '');
        } else {
            // Campos de refund
            $amount = (float)($data["merchantRespPurchaseAmount"] ?? 0);
            $amountLong = (int) bcmul($amount, '1000', 0);

            $toHash =
                $encodedPOSAuthCode .
                ($data["messageType"] ?? '') .
                ($data["merchantRespCP"] ?? '') .
                ($data["merchantRespTid"] ?? '') .
                ($data["merchantRespMerchantRef"] ?? '') .
                ($data["merchantRespMerchantSession"] ?? '') .
                $amountLong .
                ($data["merchantRespMessageID"] ?? '') .
                ($data["merchantRespPan"] ?? '') .
                ($data["merchantResp"] ?? '') .
                ($data["merchantRespTimeStamp"] ?? '') .
                (!empty($data['merchantRespReferenceNumber']) ? (int)$data['merchantRespReferenceNumber'] : '') .
                (!empty($data['merchantRespEntityCode']) ? (int)$data['merchantRespEntityCode'] : '') .
                ($data["merchantRespClientReceipt"] ?? '') .
                trim($data["merchantRespAdditionalErrorMessage"] ?? '') .
                ($data["merchantRespReloadCode"] ?? '');
        }

        return base64_encode(hash('sha512', $toHash, true));
    }




    // -------------------------
    // 🧾 Prepare Payment
    // -------------------------

    /**
     * Prepara os dados da transação para o Vinti4Pay.
     *
     * Este método monta o payload completo de pagamento, incluindo o cálculo de fingerprint,
     * e a estrutura opcional de 3DS (`purchaseRequest`) quando aplicável.
     *
     * Retorna um array contendo a URL de submissão e os campos necessários para o formulário HTML.
     *
     * Exemplo de uso:
     * ```php
     * $payment = $vinti4->preparePayment([
     *     'amount' => 1500.00,
     *     'responseUrl' => 'https://meusite.cv/vinti4/callback',
     *     'transactionCode' => 1,
     *     'billing' => [
     *         'billAddrCountry' => 'CV',
     *         'billAddrCity' => 'Praia',
     *         'billAddrLine1' => 'Av. Principal 10',
     *         'billAddrPostCode' => '7600',
     *         'email' => 'cliente@email.cv'
     *     ]
     * ]);
     * ```
     *
     * @param string $responseUrl URL de callback
     * @param array $params Parâmetros da transação:
     **  - `amount` *(float|string)*: Valor da transação (obrigatório)
     **  - `transactionCode` *(string)*: Código da transação (`1`, `2`, `3`) [default=`1`]
     **  - `billing` *(array)*: Dados de faturação para transações de compra
     *   - `merchantRef` *(string)*: Referência interna do comerciante (recomendado)
     *   - `merchantSession` *(string)*: Sessão interna do comerciante (recomendado)
     *   - currency (string|int, opcional): Código da moeda (ISO4217). @link https://www.iban.com/country-codes
     *   - `languageMessages` *(string)*: Idioma (`pt` ou `en`) [default=`pt`]
     *   - `entityCode` *(string)*: Código da entidade (para pagamentos de serviços)
     *   - `referenceNumber` *(string)*: Número de referência (para pagamentos de serviços)
     * @return array{postUrl:string, fields:array}
     * @throws Vinti4Exception Se campos obrigatórios estiverem ausentes ou billing estiver incompleto
     */
    public function preparePayment(string $responseUrl, array $params): array
    {
        if (empty($params['amount'])) {
            throw new Vinti4Exception("preparePayment requires 'amount'");
        }

        $fields = [
            'transactionCode' => $params['transactionCode'] ?? self::TRANSACTION_TYPE_PURCHASE,
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession' => $params['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => (int)(float)$params['amount'],
            'currency' => $params['currency'] ?? self::CURRENCY_CVE,
            'is3DSec' => '1',
            'urlMerchantResponse' => $responseUrl,
            'languageMessages' => $params['languageMessages'] ?? $this->language,
            'timeStamp' => $params['timeStamp'] ?? date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'entityCode' => $params['entityCode'] ?? '',
            'referenceNumber' => $params['referenceNumber'] ?? '',
        ];

        if ($fields['transactionCode'] === self::TRANSACTION_TYPE_PURCHASE) {
            $billing = $params['billing'] ?? $params;
            $fields['purchaseRequest'] = $this->buildPurchaseRequest($billing);
        }

        $fields['fingerprint'] = $this->generateRequestFingerprint($fields, 'payment');
        $postUrl = $this->baseUrl . '?' . http_build_query([
            'FingerPrint' => $fields['fingerprint'],
            'TimeStamp' => $fields['timeStamp'],
            'FingerPrintVersion' => $fields['fingerprintversion']
        ]);

        return ['postUrl' => $postUrl, 'fields' => $fields];
    }

    // -------------------------
    // 🧾 Prepare Refund
    // -------------------------
    public function prepareRefund(array $params): array
    {
        $fields = [
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'],
            'merchantSession' => $params['merchantSession'],
            'amount' => (int)$params['amount'],
            'currency' => $params['currency'] ?? self::CURRENCY_CVE,
            'clearingPeriod' => $params['clearingPeriod'],
            'transactionID' => $params['transactionID'],
            'reversal' => 'R',
            'urlMerchantResponse' => $params['responseUrl'],
            'languageMessages' => $params['language'] ?? $this->language,
            'fingerPrintVersion' => '1',
            'timeStamp' => date('Y-m-d H:i:s')
        ];

        $fields['fingerprint'] = $this->generateRequestFingerprint($fields, 'refund');

        $postUrl = $this->baseUrl . '?' . http_build_query([
            'FingerPrint' => $fields['fingerprint'],
            'TimeStamp' => $fields['timeStamp'],
            'FingerPrintVersion' => $fields['fingerPrintVersion']
        ]);

        return ['postUrl' => $postUrl, 'fields' => $fields, 'redirectMessage' => 'Pagamento do estrono em processo... Por favor aguarde'];
    }

    // -------------------------
    // 🖥 Render Form
    // -------------------------
    public function renderForm(array $data, string $redirectMessage = "Processando o pagamento, por favor aguarde"): string
    {
        $inputs = '';
        foreach ($data['fields'] as $k => $v) {
            $inputs .= "<input type='hidden' name='" . htmlspecialchars($k) . "' value='" . htmlspecialchars($v) . "'>";
        }
        return "
        <html>
            <body onload='document.forms[0].submit()' style='text-align:center;padding:30px;font-family:Arial,sans-serif;'>
                <h3>$redirectMessage</h3>
                <form action='" . htmlspecialchars($data['postUrl']) . "' method='post'>
                    $inputs
                </form>
            </body>
        </html>";
    }

    // -------------------------
    // 🧾 Process Payment Response
    // -------------------------
    public function processPaymentResponse(array $postData): ResponseResult
    {
        $result = [
            'status' => 'ERROR',
            'message' => 'Erro.',
            'success' => false,
            'data' => $postData,
            'dcc' => []
        ];

        // Usuário cancelou
        if (($postData['UserCancelled'] ?? '') === 'true') {
            $result['status'] = 'CANCELLED';
            $result['message'] = $this->getLangMessage($postData, 'Utilizador cancelou o pagamento.', 'User cancelled the payment.');
            return new ResponseResult($result);
        }

        // Parse DCC se existir
        if (!empty($postData['merchantRespDCCData'])) {
            $decoded = json_decode($postData['merchantRespDCCData'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $result['dcc'] = $decoded;
            }
        }

        // Mensagem de sucesso
        if (isset($postData['messageType']) && in_array($postData['messageType'], self::SUCCESS_MESSAGE_TYPES)) {
            $calcFingerprint = $this->generateResponseFingerprint($postData, 'payment');
            $receivedFingerprint = $postData['resultFingerPrint'] ?? '';

            if ($receivedFingerprint === $calcFingerprint) {
                $result['success'] = true;
                $result['status'] = 'SUCCESS';
                $result['message'] = $this->getLangMessage($postData, 'Transação válida e efectuada.', 'Transaction valid and fingerprint verified.');
            } else {
                $result['success'] = false;
                $result['status'] = 'INVALID_FINGERPRINT';
                $result['message'] = $this->getLangMessage($postData, 'Transação suspeita de fraude. (fingerprint inválido)', 'Transaction processed but fingerprint invalid.');
                $result['debug'] = ['received' => $receivedFingerprint, 'calculated' => $calcFingerprint];
            }
        } else {
            $result['message'] = $postData['merchantRespErrorDescription'] ?? $result['message'];
            $result['detail'] = $postData['merchantRespErrorDetail'] ?? '';
        }

        return new ResponseResult($result);
    }

    // -------------------------
    // 🧾 Process Refund Response
    // -------------------------
    public function processRefundResponse(array $postData): ResponseResult
    {
        $result = [
            'status' => 'ERROR',
            'message' => 'Error.',
            'success' => false,
            'data' => $postData,
            'debug' => []
        ];

        if (!isset($postData['messageType'])) {
            return new ResponseResult($result);
        }

        $messageType = $postData['messageType'];

        if ($messageType == null) {
            $result['message'] = $this->getLangMessage($postData, 'Nenhuma resposta do servidor (timeout ou problema de rede).', 'No response from SISP (timeout or network issue).');
            return new ResponseResult($result);
        }

        if ($messageType === '6') {
            $result['message'] = $postData['merchantRespErrorDescription'] ?? $this->getLangMessage($postData, 'Erro desconhecido', 'Unknown error');
            $result['detail'] = $postData['merchantRespErrorDetail'] ?? '';
            return new ResponseResult($result);
        }

        if ($messageType === '10') {
            $calcFingerprint = $this->generateResponseFingerprint($postData, 'payment');
            $receivedFingerprint = $postData['resultFingerPrint'] ?? '';

            if ($receivedFingerprint === $calcFingerprint) {
                $result['success'] = true;
                $result['status'] = 'SUCCESS';
                $result['message'] = $this->getLangMessage($postData, 'Transação válida e efectuada.', 'Transaction valid and fingerprint verified.');
            } else {
                $result['status'] = 'INVALID_FINGERPRINT';
                $result['message'] = $this->getLangMessage($postData, 'Transação suspeita de fraude. (fingerprint inválido)', 'Transaction processed but fingerprint invalid.');
                $result['debug'] = ['received' => $receivedFingerprint, 'calculated' => $calcFingerprint];
            }
        } else {
            $result['message'] = $postData['merchantRespErrorDescription'] ?? $this->getLangMessage($postData, 'Erro desconhecido.', 'Unexpected messageType in refund response.');
            $result['detail'] = $postData['merchantRespErrorDetail'] ?? '';
        }

        return new ResponseResult($result);
    }

    // -------------------------
    // 📝 Auxiliares
    // -------------------------
    private function getLangMessage(array $data, string $pt, string $en): string
    {
        $lang = $data['languageMessages'] ?? $this->language;
        return $lang === 'pt' ? $pt : $en;
    }
}
