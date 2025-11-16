<?php

namespace Erilshk\Vinti4Pay\Services;

use Erilshk\Vinti4Pay\Models\ResponseResult;
use Erilshk\Vinti4Pay\Services\SispPaymentResponse;

/**
 * Class SispRefund
 *
 * Handles and validates refund (reversal) responses from Vinti4Net SISP.
 * Follows the official SISP MOP021 specification.
 *
 * @package Erilshk\Vinti4Pay\Sisp
 */
class SispRefundResponse extends SispPaymentResponse
{
    protected string $language = 'pt';

    private const ERROR_MESSAGES = [
        'timeout' => [
            'pt' => 'Tempo limite excedido. O sistema não recebeu resposta da SISP.',
            'en' => 'Timeout. No response received from SISP.',
        ],
        '6' => [
            'pt' => 'Erro na transação de estorno.',
            'en' => 'Refund transaction error.',
        ],
        'invalid_fingerprint' => [
            'pt' => 'Estorno processado com suspeita de fraude (fingerprint inválido).',
            'en' => 'Refund processed but fingerprint invalid.',
        ],
        'unknown' => [
            'pt' => 'Erro desconhecido ou mensagem inválida recebida.',
            'en' => 'Unknown error or invalid response received.',
        ],
    ];

    public function __construct(string $posID, string $posAuthCode, string $language = 'pt')
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->language = $language;
    }

    /**
     * Processes and validates the refund POST response from Vinti4Net.
     */
    public function processResponse(?array $postData = null): ResponseResult
    {
        $postData ??= $_POST;

        $result = [
            'status' => 'ERROR',
            'message' => $this->msg('unknown', $postData),
            'success' => false,
            'data' => $postData,
            'debug' => []
        ];

        if (empty($postData['messageType'])) {
            $result['status'] = 'TIMEOUT';
            $result['message'] = $this->msg('timeout', $postData);
            return new ResponseResult($result);
        }

        if (($postData['messageType'] ?? '') === '6') {
            $result['status'] = 'FAILED';
            $result['message'] = $postData['merchantRespErrorDescription'] ?? $this->msg('6', $postData);
            $result['detail'] = $postData['merchantRespErrorDetail'] ?? '';
            return new ResponseResult($result);
        }

        $debug = [];
        if ($this->validateRefundFingerprint($postData, $debug)) {
            $result['status'] = 'SUCCESS';
            $result['message'] = $this->getLangMessage(
                $postData,
                'Estorno validado e realizado com sucesso.',
                'Refund processed successfully and fingerprint valid.'
            );
            $result['success'] = true;
        } else {
            $result['status'] = 'INVALID_FINGERPRINT';
            $result['message'] = $this->msg('invalid_fingerprint', $postData);
            $result['debug'] = $debug;
        }

        return new ResponseResult($result);
    }

    /**
     * Generate expected fingerprint for refund response.
     */
    protected function generateFingerprint(array $data): string
    {
        $reference = isset($data['merchantRespReferenceNumber']) ? (int) $data['merchantRespReferenceNumber'] : '';
        $entity = isset($data['merchantRespEntityCode']) ? (int) $data['merchantRespEntityCode'] : '';

        $toHash = base64_encode(hash('sha512', $this->posAuthCode, true))
            . ($data['messageType'] ?? '')
            . ($data['merchantRespCP'] ?? '')
            . ($data['merchantRespTid'] ?? '')
            . ($data['merchantRespMerchantRef'] ?? '')
            . ($data['merchantRespMerchantSession'] ?? '')
            . ((int)((float)($data['merchantRespPurchaseAmount'] ?? 0) * 1000))
            . ($data['merchantRespMessageID'] ?? '')
            . ($data['merchantRespPan'] ?? '')
            . ($data['merchantResp'] ?? '')
            . ($data['merchantRespTimeStamp'] ?? '')
            . $reference
            . $entity
            . ($data['merchantRespClientReceipt'] ?? '')
            . trim($data['merchantRespAdditionalErrorMessage'] ?? '')
            . ($data['merchantRespReloadCode'] ?? '');

        return base64_encode(hash('sha512', $toHash, true));
    }


    private function validateRefundFingerprint(array $data, ?array &$debug = null): bool
    {
        $calcFingerprint = $this->generateFingerprint($data);
        $receivedFingerprint = $data['resultFingerPrint'] ?? '';
        $debug = [
            'received' => $receivedFingerprint,
            'calculated' => $calcFingerprint
        ];
        return $receivedFingerprint === $calcFingerprint;
    }

    private function getLangMessage(array $data, string $pt, string $en): string
    {
        $lang = $data['languageMessages'] ?? $this->language;
        return $lang === 'pt' ? $pt : $en;
    }

    private function msg(string $key, array $data = []): string
    {
        $lang = $data['languageMessages'] ?? $this->language;
        $msg = self::ERROR_MESSAGES[$key] ?? self::ERROR_MESSAGES['unknown'];
        return $msg[$lang === 'pt' ? 'pt' : 'en'];
    }
}
