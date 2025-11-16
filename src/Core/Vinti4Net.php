<?php

namespace Erilshk\Vinti4Pay\Core;

use Erilshk\Vinti4Pay\Models\ResponseResult;
use Erilshk\Vinti4Pay\Exceptions\Vinti4Exception;
use Erilshk\Vinti4Pay\Traits\PurchaseRequestTrait;


class Vinti4Net
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
     * Prepares the transaction data for Vinti4Pay.
     *
     * This method builds the complete payment payload, including the fingerprint calculation,
     * and the optional 3DS structure (`purchaseRequest`) when applicable.
     *
     * Returns an array containing the submission URL and the required fields for the HTML form.
     *
     * Example usage:
     * ```php
     * $payment = $vinti4->preparePayment([
     *     'amount' => 1500.00,
     *     'responseUrl' => 'https://mysite.cv/vinti4/callback',
     *     'transactionCode' => 1,
     *     'billing' => [
     *         'billAddrCountry' => 'CV',
     *         'billAddrCity' => 'Praia',
     *         'billAddrLine1' => 'Av. Principal 10',
     *         'billAddrPostCode' => '7600',
     *         'email' => 'customer@email.cv'
     *     ]
     * ]);
     * ```
     *
     * @param string $responseUrl Callback URL
     * @param array $params Transaction parameters:
     *   - `amount` *(float|string)*: Transaction amount (required)
     *   - `transactionCode` *(string)*: Transaction code (`1`, `2`, `3`) [default=`1`]
     *   - `billing` *(array)*: Billing information for purchase transactions
     *     - `merchantRef` *(string)*: Internal merchant reference (recommended)
     *     - `merchantSession` *(string)*: Internal merchant session (recommended)
     *     - `currency` *(string|int, optional)*: Currency code (ISO4217). @link https://www.iban.com/country-codes
     *     - `languageMessages` *(string)*: Language (`pt` or `en`) [default=`pt`]
     *     - `entityCode` *(string)*: Entity code (for service payments)
     *     - `referenceNumber` *(string)*: Reference number (for service payments)
     *
     * @return array{postUrl: string, fields: array}
     * @throws Vinti4Exception If required fields are missing or billing info is incomplete
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
    public function renderForm(array $data): string
    {
        $inputs = '';
        foreach ($data['fields'] as $k => $v) {
            $inputs .= "<input type='hidden' name='" . htmlspecialchars($k) . "' value='" . htmlspecialchars($v) . "'>";
        }
        return "
        <html>
            <body onload='document.forms[0].submit()' style='text-align:center;padding:30px;font-family:Arial,sans-serif;'>
                <h3>Processando o pagamento... por favor aguarde</h3>
                <form action='" . htmlspecialchars($data['postUrl']) . "' method='post'>
                    $inputs
                </form>
            </body>
        </html>";
    }

    // -------------------------
    // 🧾 Process Payment Response
    // -------------------------

    /**
     * Processes the payment response from Vinti4Pay.
     *
     * This method validates the payment response data, checks for user cancellation,
     * parses DCC (Dynamic Currency Conversion) information if present,
     * verifies the fingerprint, and returns a structured result.
     *
     * The returned `ResponseResult` contains:
     *  - `status`: Status code ('SUCCESS', 'ERROR', 'CANCELLED', 'INVALID_FINGERPRINT', etc.)
     *  - `message`: Human-readable message (based on language or server response)
     *  - `success`: Boolean indicating if the payment was successfully processed
     *  - `data`: Original POST data
     *  - `dcc`: Optional DCC information if the transaction used Dynamic Currency Conversion
     *  - `debug`: Optional debug information (e.g., fingerprint comparison)
     *
     * Behavior details:
     *  - User cancelled: `status` = 'CANCELLED', message reflects user cancellation
     *  - DCC detected: `dcc` array is populated with `amount`, `currency`, `markup`, and `rate`
     *  - Success message types (see `self::SUCCESS_MESSAGE_TYPES`):
     *      - Fingerprint matches: `status` = 'SUCCESS', `success` = true
     *      - Fingerprint mismatch: `status` = 'INVALID_FINGERPRINT', includes debug info
     *  - Other cases: Uses `merchantRespErrorDescription` and `merchantRespErrorDetail` if provided
     *
     * @param array $postData The POST data returned by Vinti4Pay for the payment
     *
     * @return ResponseResult Structured result containing status, message, success flag, DCC info, original data, and debug info
     */

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
        if (!empty($postData['dcc']) && $postData['dcc'] == 'Y') {
            $result['dcc'] = [
                'amount' => $postData['dccAmount'] ?? '',
                'currency' => $postData['dccCurrency'] ?? '',
                'markup' => $postData['dccMarkup'] ?? '',
                'rate' => $postData['dccRate'] ?? ''
            ];
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

    /**
     * Processes the refund response from Vinti4Pay.
     *
     * This method validates the refund response data, checks the message type,
     * verifies the fingerprint if applicable, and returns a structured result.
     *
     * The returned `ResponseResult` contains:
     *  - `status`: Status code ('SUCCESS', 'ERROR', 'INVALID_FINGERPRINT', etc.)
     *  - `message`: Human-readable message (based on language or server response)
     *  - `success`: Boolean indicating if the refund was successfully processed
     *  - `data`: Original POST data
     *  - `debug`: Optional debug information (e.g., fingerprint comparison)
     *
     * Behavior by messageType:
     *  - null: No response from server (timeout or network issue)
     *  - '6': Error response from merchant, includes `merchantRespErrorDescription` and `merchantRespErrorDetail`
     *  - '10': Successful refund, fingerprint is validated
     *  - other: Unexpected messageType, treated as an error
     *
     * @param array $postData The POST data returned by Vinti4Pay for the refund
     *
     * @return ResponseResult Structured result containing status, message, success flag, original data, and debug info
     */

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
