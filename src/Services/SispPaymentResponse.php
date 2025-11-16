<?php

namespace Erilshk\Vinti4Pay\Services;

use Erilshk\Vinti4Pay\Models\ResponseResult;
use Erilshk\Vinti4Pay\Services\Sisp;

/**
 * Handles and validates payment responses from Vinti4Net (SISP Cabo Verde).
 *
 * Follows the official SISP MOP021 specification.
 * Handles messageType logic, fingerprint validation,
 * DCC parsing and error message mapping.
 *
 * @author Eril
 * @package Erilshk\Vinti4Pay
 */
class SispPaymentResponse extends Sisp
{
    // protected string $posID;
    // protected string $posAuthCode;
    protected string $language = 'pt';


    /**
     * Common error codes and messages from SISP Vinti4Net.
     */
    private const ERROR_MESSAGES = [
        'timeout' => [
            'pt' => 'Tempo limite excedido. O sistema não recebeu resposta da SISP (timeout de 5 minutos).',
            'en' => 'Timeout. The system did not receive a response from SISP (5-minute timeout).',
        ],
        '6' => [
            'pt' => 'Erro na transação. Consulte os detalhes da resposta do comerciante.',
            'en' => 'Transaction error. Check merchant response details.',
        ],
        'invalid_fingerprint' => [
            'pt' => 'Transação suspeita de fraude. (fingerprint inválido)',
            'en' => 'Transaction processed but fingerprint invalid.',
        ],
        'cancelled' => [
            'pt' => 'Utilizador cancelou o pagamento.',
            'en' => 'User cancelled the payment.',
        ],
        'unknown' => [
            'pt' => 'Erro desconhecido ou mensagem inválida recebida.',
            'en' => 'Unknown error or invalid response received.',
        ],
    ];

    public function __construct(
        protected string $posID,
        protected string $posAuthCode,
        string $language = 'pt'
    ) {
        $this->language = $language;
    }

    /**
     * Processes and validates the POST response from Vinti4Net.
     *
     * @param array $postData Usually $_POST
     * @return ResponseResult
     */
    public function processResponse(?array $postData = null): ResponseResult
    {
        $postData ??= $_POST;

        $result = [
            'status' => 'ERROR',
            'message' => $this->msg('unknown', $postData),
            'success' => false,
            'data' => $postData,
            'dcc' => []
        ];

        // 1️⃣ User manually cancelled payment
        if ($this->isUserCancelled($postData)) {
            $result['status'] = 'CANCELLED';
            $result['message'] = $this->msg('cancelled', $postData);
            return new ResponseResult($result);
        }

        // 2️⃣ Timeout case (messageType NULL)
        if (empty($postData['messageType'])) {
            $result['status'] = 'TIMEOUT';
            $result['message'] = $this->msg('timeout', $postData);
            return new ResponseResult($result);
        }

        // 3️⃣ messageType == 6 → erro genérico
        if (($postData['messageType'] ?? '') === '6') {
            $result['status'] = 'FAILED';
            $result['message'] = $postData['merchantRespErrorDescription'] ?? $this->msg('6', $postData);
            $result['detail'] = $postData['merchantRespErrorDetail'] ?? '';
            $result['additional'] = $postData['merchantRespAdditionalErrorMessage'] ?? '';
            return new ResponseResult($result);
        }

        // 4️⃣ Parse optional DCC (Dynamic Currency Conversion)
        $result['dcc'] = $this->parseDccData($postData);

        // 5️⃣ Valid messageType + success/fingerprint check
        if ($this->isSuccessfulMessageType($postData)) {

            // Special rule: messageType=8 && merchantResp='C' → success
            if (($postData['messageType'] === '8' && ($postData['merchantResp'] ?? '') !== 'C')) {
                $result['status'] = 'PENDING';
                $result['message'] = $this->getLangMessage(
                    $postData,
                    'Transação pendente de confirmação.',
                    'Transaction pending confirmation.'
                );
                return new ResponseResult($result);
            }

            if ($this->validateFingerprint($postData, $debug)) {
                $result['status'] = 'SUCCESS';
                $result['message'] = $this->getLangMessage(
                    $postData,
                    'Transação válida e efectuada.',
                    'Transaction valid and fingerprint verified.'
                );
                $result['success'] = true;
            } else {
                $result['status'] = 'INVALID_FINGERPRINT';
                $result['message'] = $this->msg('invalid_fingerprint', $postData);
                $result['success'] = false;
                $result['debug'] = $debug;
            }
        } else {
            // messageType not recognized
            $result['message'] = $this->msg('unknown', $postData);
        }

        return new ResponseResult($result);
    }

    /**
     * Generates the expected fingerprint for a Vinti4Net response.
     */
    protected function generateFingerprint(array $data): string
    {
        $reference = !empty($data['merchantRespReferenceNumber']) ? (int)$data['merchantRespReferenceNumber'] : '';
        $entity = !empty($data['merchantRespEntityCode']) ? (int)$data['merchantRespEntityCode'] : '';
        $reloadCode = $data['merchantRespReloadCode'] ?? '';
        $additionalErrorMessage = trim($data['merchantRespAdditionalErrorMessage'] ?? '');

        $toHash = base64_encode(hash('sha512', $this->posAuthCode, true)) .
            ($data["messageType"] ?? '') .
            ($data["merchantRespCP"] ?? '') .
            ($data["merchantRespTid"] ?? '') .
            ($data["merchantRespMerchantRef"] ?? '') .
            ($data["merchantRespMerchantSession"] ?? '') .
            ((int)((float)($data["merchantRespPurchaseAmount"] ?? 0) * 1000)) .
            ($data["merchantRespMessageID"] ?? '') .
            ($data["merchantRespPan"] ?? '') .
            ($data["merchantResp"] ?? '') .
            ($data["merchantRespTimeStamp"] ?? '') .
            $reference .
            $entity .
            ($data["merchantRespClientReceipt"] ?? '') .
            $additionalErrorMessage .
            $reloadCode;

        return base64_encode(hash('sha512', $toHash, true));
    }

    /**
     * Validates fingerprint and captures debug data.
     */
    private function validateFingerprint(array $data, ?array &$debug = null): bool
    {
        $calcFingerprint = $this->generateFingerprint($data);
        $receivedFingerprint = $data['resultFingerPrint'] ?? '';
        $debug = [
            'received' => $receivedFingerprint,
            'calculated' => $calcFingerprint
        ];
        return $receivedFingerprint === $calcFingerprint;
    }

    /**
     * Detects if user cancelled payment manually.
     */
    private function isUserCancelled(array $data): bool
    {
        return ($data['UserCancelled'] ?? '') === 'true';
    }

    /**
     * Checks if messageType indicates a successful operation.
     */
    private function isSuccessfulMessageType(array $data): bool
    {
        return isset($data['messageType']) && in_array($data['messageType'], self::SUCCESS_MESSAGE_TYPES, true);
    }

    /**
     * Parses optional DCC JSON data, if available.
     */
    private function parseDccData(array $data): array
    {
        if (empty($data['merchantRespDCCData'])) {
            return [];
        }

        $decoded = json_decode($data['merchantRespDCCData'], true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
    }

    /**
     * Returns message in preferred language (PT or EN).
     */
    private function getLangMessage(array $data, string $pt, string $en): string
    {
        $lang = $data['languageMessages'] ?? $this->language;
        return $lang === 'pt' ? $pt : $en;
    }

    /**
     * Shortcut for multilingual static messages.
     */
    private function msg(string $key, array $data = []): string
    {
        $lang = $data['languageMessages'] ?? $this->language;
        $msg = self::ERROR_MESSAGES[$key] ?? self::ERROR_MESSAGES['unknown'];
        return $msg[$lang === 'pt' ? 'pt' : 'en'];
    }
}
