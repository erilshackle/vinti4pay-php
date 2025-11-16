<?php

namespace Erilshk\Vinti4Pay\Traits;

trait FingerprintSignatures
{
    // -------------------------
    // 🔐 Specific Fingerprints
    // -------------------------

    // Payment / Service / Recharge Request
    protected function generatePaymentRequestFingerprint(
        string $posAuthCode,
        string $timeStamp,
        float $amount,
        string $merchantRef,
        string $merchantSession,
        string $posID,
        string $currency,
        string $transactionCode,
        $entityCode = '',
        $referenceNumber = ''
    ): string {
        $entityCode = !empty($entityCode) ? (int)$entityCode : '';
        $referenceNumber = !empty($referenceNumber) ? (int)$referenceNumber : '';
        $amountLong = (int) bcmul($amount, '1000', 0);

        $toHash = base64_encode(hash('sha512', $posAuthCode, true)) .
            $timeStamp .
            $amountLong .
            $merchantRef .
            $merchantSession .
            $posID .
            $currency .
            $transactionCode .
            $entityCode .
            $referenceNumber;

        return base64_encode(hash('sha512', $toHash, true));
    }

    // Refund Request
    protected function generateRefundRequestFingerprint(
        string $posAuthCode,
        string $transactionCode,
        string $posID,
        string $merchantRef,
        string $merchantSession,
        float $amount,
        string $currency,
        string $clearingPeriod,
        string $transactionID,
        string $reversal,
        string $urlMerchantResponse,
        string $languageMessages,
        string $fingerPrintVersion,
        string $timeStamp
    ): string {
        $amountLong = (int) bcmul($amount, '1000', 0);

        $toHash = base64_encode(hash('sha512', $posAuthCode, true)) .
            $transactionCode .
            $posID .
            $merchantRef .
            $merchantSession .
            $amountLong .
            $currency .
            $clearingPeriod .
            $transactionID .
            $reversal .
            $urlMerchantResponse .
            $languageMessages .
            $fingerPrintVersion .
            $timeStamp;

        return base64_encode(hash('sha512', $toHash, true));
    }

    // Payment Response
    protected function generatePaymentResponseFingerprint(
        string $posAuthCode,
        string $messageType,
        string $clearingPeriod,
        string $transactionID,
        string $merchantReference,
        string $merchantSession,
        float $amount,
        string $messageID,
        string $pan,
        string $merchantResponse,
        string $timestamp,
        $reference = '',
        $entity = '',
        $clientReceipt = '',
        $additionalErrorMessage = '',
        $reloadCode = ''
    ): string {
        $reference = !empty($reference) ? (int)$reference : '';
        $entity = !empty($entity) ? (int)$entity : '';
        $amountLong = (int) bcmul($amount, '1000', 0);

        $toHash = base64_encode(hash('sha512', $posAuthCode, true)) .
            $messageType .
            $clearingPeriod .
            $transactionID .
            $merchantReference .
            $merchantSession .
            $amountLong .
            $messageID .
            $pan .
            $merchantResponse .
            $timestamp .
            $reference .
            $entity .
            $clientReceipt .
            $additionalErrorMessage .
            $reloadCode;

        return base64_encode(hash('sha512', $toHash, true));
    }

    // Refund Response
    protected function generateRefundResponseFingerprint(
        string $posAuthCode,
        string $messageType,
        string $clearingPeriod,
        string $transactionID,
        string $merchantReference,
        string $merchantSession,
        float $amount,
        string $messageID,
        string $pan,
        string $merchantResponse,
        string $timestamp,
        $reference = '',
        $entity = '',
        $clientReceipt = '',
        $additionalErrorMessage = '',
        $reloadCode = ''
    ): string {
        // Refund response follows the same logic as payment response for SISP
        return $this->generatePaymentResponseFingerprint(
            $posAuthCode,
            $messageType,
            $clearingPeriod,
            $transactionID,
            $merchantReference,
            $merchantSession,
            $amount,
            $messageID,
            $pan,
            $merchantResponse,
            $timestamp,
            $reference,
            $entity,
            $clientReceipt,
            $additionalErrorMessage,
            $reloadCode
        );
    }

    // -------------------------
    // 🔁 Generic Methods (array)
    // -------------------------
    protected function generateRequestFingerprint(array $data, string $type = 'payment'): string
    {
        if ($type === 'payment') {
            return $this->generatePaymentRequestFingerprint(
                $this->posAuthCode,
                $data['timeStamp'] ?? '',
                $data['amount'] ?? 0,
                $data['merchantRef'] ?? '',
                $data['merchantSession'] ?? '',
                $data['posID'] ?? '',
                $data['currency'] ?? '',
                $data['transactionCode'] ?? '',
                $data['entityCode'] ?? '',
                $data['referenceNumber'] ?? ''
            );
        } elseif ($type === 'payment') {
            return $this->generateRefundRequestFingerprint(
                $this->posAuthCode,
                $data['transactionCode'] ?? '',
                $data['posID'] ?? '',
                $data['merchantRef'] ?? '',
                $data['merchantSession'] ?? '',
                $data['amount'] ?? 0,
                $data['currency'] ?? '',
                $data['clearingPeriod'] ?? '',
                $data['transactionID'] ?? '',
                $data['reversal'] ?? '',
                $data['urlMerchantResponse'] ?? '',
                $data['languageMessages'] ?? '',
                $data['fingerPrintVersion'] ?? '',
                $data['timeStamp'] ?? ''
            );
        }

        return '';
    }

    protected function generateResponseFingerprint(array $data, string $type = 'payment'): string
    {
        if ($type === 'payment') {
            return $this->generatePaymentResponseFingerprint(
                $this->posAuthCode,
                $data['messageType'] ?? '',
                $data['merchantRespClearingPeriod'] ?? '',
                $data['merchantRespTransactionID'] ?? '',
                $data['merchantRespMerchantRef'] ?? '',
                $data['merchantRespMerchantSession'] ?? '',
                $data['merchantRespPurchaseAmount'] ?? 0,
                $data['merchantRespMessageID'] ?? '',
                $data['merchantRespPan'] ?? '',
                $data['merchantResp'] ?? '',
                $data['merchantRespTimeStamp'] ?? '',
                $data['merchantRespReferenceNumber'] ?? '',
                $data['merchantRespEntityCode'] ?? '',
                $data['merchantRespClientReceipt'] ?? '',
                $data['merchantRespAdditionalErrorMessage'] ?? '',
                $data['merchantRespReloadCode'] ?? ''
            );
        } elseif ($type == 'refund') {
            return $this->generateRefundResponseFingerprint(
                $this->posAuthCode,
                $data['messageType'] ?? '',
                $data['merchantRespClearingPeriod'] ?? '',
                $data['merchantRespTransactionID'] ?? '',
                $data['merchantRespMerchantRef'] ?? '',
                $data['merchantRespMerchantSession'] ?? '',
                $data['merchantRespPurchaseAmount'] ?? 0,
                $data['merchantRespMessageID'] ?? '',
                $data['merchantRespPan'] ?? '',
                $data['merchantResp'] ?? '',
                $data['merchantRespTimeStamp'] ?? '',
                $data['merchantRespReferenceNumber'] ?? '',
                $data['merchantRespEntityCode'] ?? '',
                $data['merchantRespClientReceipt'] ?? '',
                $data['merchantRespAdditionalErrorMessage'] ?? '',
                $data['merchantRespReloadCode'] ?? ''
            );
        }

        return '';
    }
}
