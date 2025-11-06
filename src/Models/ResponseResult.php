<?php

namespace Erilshk\Vinti4Pay\Models;

use JsonSerializable;

/**
 * DTO (Data Transfer Object) representing the result of a transaction processed through the Vinti4Net gateway.
 *
 * Provides a convenient and structured way to access the outcome returned by
 * {@see Vinti4Pay::processResponse()} and {@see Vinti4Refund::processRefundResponse()} methods.
 *
 * This class simplifies handling of transaction results, including success, cancellation,
 * fingerprint validation errors, and general error states.
 * Supports array-style access (implements ArrayAccess) and JSON serialization.
 * 
 * @package Erilshk\Vinti4Pay
 */
class ResponseResult  implements JsonSerializable
{
    /** @var string Transaction status (SUCCESS, ERROR, CANCELLED, INVALID_FINGERPRINT) */
    public string $status = 'ERROR';

    /** @var string Human-readable transaction message */
    public string $message = '';

    /** @var bool Indicates whether the transaction was successful */
    public bool $success = false;

    /** @var array<string, mixed> Raw transaction data returned from Vinti4Net */
    public array $data = [];

    /** @var array|null Optional debugging information */
    public ?array $debugInfo = null;

    /** @var array<string,mixed>|null Dynamic Currency Conversion (DCC) data, if available */
    public ?array $dcc = null;

    /** @var string|null Additional error or diagnostic detail */
    public ?string $detail = null;

    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_INVALID_FINGERPRINT = 'INVALID_FINGERPRINT';
    private const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    /**
     * Construct result DTO from the array/object returned by processResponse-like methods.
     *
     * @param array<string,mixed> $result
     */
    public function __construct(array $result)
    {
        $messageType = $result['data']['messageType'] ?? null;
        $isSuccessByType = in_array($messageType, self::SUCCESS_MESSAGE_TYPES, true);

        $data = $result['data'] ?? [];
        $this->data = $data;
        $this->debugInfo = $result['debug'] ?? [];
        $this->dcc = $result['dcc'] ?? [];
        $this->detail = $result['detail'] ?? '';
        $this->message = $result['message'] ?? '';

        // Determina sucesso
        $this->success = $result['success'] ?? $isSuccessByType;

        // Usa o status fornecido, se existir; senão calcula baseado no success
        $this->status = $result['status'] ?? ($this->success ? self::STATUS_SUCCESS : self::STATUS_ERROR);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDebugInfo(): ?array
    {
        return $this->debugInfo;
    }
    public function hasDcc(): bool
    {
        return !empty($this->dcc) && isset($this->dcc['currency']);
    }

    public function getDcc(): ?array
    {
        return $this->dcc;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }


    /**
     * Returns a human-friendly label for the current transaction status.
     *
     * @return string Human-readable status label.
     */
    public function getStatusLabel($lang = 'en'): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => $lang == 'pt' ? 'Transação bem-sucedida' : 'Successful Transaction',
            self::STATUS_CANCELLED => $lang == 'pt' ? 'Transação cancelada pelo utilizador' : 'Transaction Cancelled by User',
            self::STATUS_INVALID_FINGERPRINT => $lang == 'pt' ? 'Assinatura digital inválida (Fingerprint incorreta)' : 'Invalid Fingerprint',
            default => $lang == 'pt' ? 'Erro ao processar a transação' : 'Transaction Error',
        };
    }


    /**
     * Gets the invalid fingerprint when fingerprint is invalid or false.
     *
     * @return string|null The invalid fingerprint or null otherwise.
     */
    public function getInvalidFingerprint(): ?string
    {
        return $this->status === self::STATUS_INVALID_FINGERPRINT  ? $this->data['resultFingerPrint'] : null;
    }


    /**
     * Gets the transaction receipt ()
     */
    public function getMerchantReceipt()
    {
        return $this->data['merchantRespClientReceipt'] ?? '';
    }

    /**
     * Converts the result object into an associative array.
     *
     * @return array{
     *     status: string,
     *     message: string,
     *     success: bool,
     *     data: array,
     *     debug: array|null,
     *     dcc: array|null,
     *     detail: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'success' => $this->success,
            'data' => $this->data,
            'debug' => $this->debugInfo,
            'dcc' => $this->dcc,
            'detail' => $this->detail,
        ];
    }

    /**
     * JSON-encoded transaction data (raw).
     *
     * @return string
     */
    public function jsonData(): string
    {
        return json_encode($this->data);
    }

    /**
     * Executes a callback if the transaction was successful.
     *
     * @param callable(self $r): void $callback Callback function executed on success.
     * @return self
     */
    public function onSuccess(callable $callback): self
    {
        if ($this->success && $this->status === self::STATUS_SUCCESS) {
            $callback($this);
        }
        return $this;
    }

    /**
     * Executes a callback if the transaction failed.
     *
     * @param callable(self $r): void $callback Callback function executed on failure.
     * @return self
     */
    public function onError(callable $callback): self
    {
        if (!$this->success && $this->status === self::STATUS_ERROR) {
            $callback($this);
        }
        return $this;
    }

    /**
     * Executes a callback if the transaction was cancelled by the user.
     *
     * @param callable(self $r): void $callback Callback function executed when cancelled.
     * @return self
     */
    public function onCancel(callable $callback): self
    {
        if ($this->status === self::STATUS_CANCELLED) {
            $callback($this);
        }
        return $this;
    }


    /**
     * Prepara dados comuns para qualquer tipo de recibo
     *
     * @return array Dados formatados e escapados prontos para render
     */
    private function prepareReceiptData(): array
    {
        $d = $this->data;
        $dcc = $this->dcc ?? [];
        $escape = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE);

        $recordType = 1; // só Transaction/Serviço/Recarga no momento
        $typeLabel = 'Transaction';

        // Campos reais do retorno
        $merchantRef = $escape($d['merchantRespMerchantRef'] ?? '');
        $messageId = $escape($d['merchantRespMessageID'] ?? '');
        $clearingPeriod = $escape($d['merchantRespCP'] ?? '');
        $responseCode = $escape($d['merchantResp'] ?? '');
        $transactionTimestamp = $escape($d['merchantRespTimeStamp'] ?? date('Y-m-d H:i:s'));
        $transactionId = $escape($d['merchantRespTid'] ?? '');
        $pan = $escape($d['merchantRespPan'] ?? '');
        $purchaseAmount = isset($d['merchantRespPurchaseAmount']) ? number_format((float)$d['merchantRespPurchaseAmount'], 2, ',', '.') : '0,00';
        $clientReceipt = $escape($d['merchantRespClientReceipt'] ?? '');

        $amountEUR = isset($dcc['amount']) ? number_format((float)$dcc['amount'], 2, ',', '.') : '';
        $dccCurrency = $escape($dcc['currency'] ?? 'EUR');
        $dccRate = $escape($dcc['rate'] ?? '');
        $dccMarkup = isset($dcc['markup']) ? number_format((float)$dcc['markup'], 2, ',', '.') : '';


        $statusColor = $this->success ? '#0a8a00' : '#dc3545';
        $statusText = $this->success ? 'Aprovada' : 'Falhou';

        return compact(
            'recordType',
            'typeLabel',
            'merchantRef',
            'messageId',
            'responseCode',
            'clearingPeriod',
            'transactionTimestamp',
            'transactionId',
            'pan',
            'purchaseAmount',
            'clientReceipt',
            'amountEUR',
            'dccCurrency',
            'dccRate',
            'dccMarkup',
            'statusColor',
            'statusText',
            'dcc'
        );
    }



    public function generateReceipt(): string
    {
        $d = $this->prepareReceiptData();
        // render técnico (todos os campos)
        return $this->renderReceiptHtml($d, true);
    }

    public function generateClientReceipt(): string
    {
        $d = $this->prepareReceiptData();
        // render cliente (campos resumidos)
        return $this->renderReceiptHtml($d, false);
    }

    /**
     * Renderiza HTML do recibo
     *
     * @param array $d Dados preparados
     * @param bool $technical True = mostrar campos técnicos, False = cliente
     * @return string
     */
    private function renderReceiptHtml(array $d, bool $technical): string
    {
        extract($d);

        $html = "<div style=\"font-family:'Segoe UI',Roboto,Arial,sans-serif;background:#f9fafc;color:#333;max-width:640px;margin:40px auto;padding:24px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.08);\">";

        // Header
        $html .= "<div style=\"text-align:center;margin-bottom:20px;\">";
        $html .= "<h2 style=\"margin:0;font-size:1.6em;color:#004aad;\">{$typeLabel} Receipt</h2>";
        $html .= "<p style=\"margin:5px 0;color:#666;font-size:0.9em;\">{$transactionTimestamp}</p>";
        $html .= "</div>";

        // Transaction Details
        $html .= "<div style=\"background:#fff;border-radius:10px;padding:18px;border:1px solid #e0e0e0;\">";
        $html .= "<h3 style=\"margin-top:0;color:#004aad;font-size:1.1em;\">" . ($technical ? "Transaction Details" : "Resumo") . "</h3>";
        $html .= "<table style=\"width:100%;border-collapse:collapse;font-size:0.95em;\">";

        $fields = $technical ? [
            'Record Type' => $recordType,
            'Merchant Reference' => $merchantRef,
            'Message ID' => $messageId,
            'RT Response Code' => "<span style=\"color:{$statusColor};font-weight:600;\">{$responseCode}</span>",
            'Transaction Timestamp' => $transactionTimestamp,
            'Transaction ID' => $transactionId,
            'PAN' => $pan,
        ] : [
            'Status' => "<span style=\"color:{$statusColor};font-weight:600;\">{$statusText}</span>",
            'Valor (CVE)' => "{$purchaseAmount} CVE",
            'Referência' => $merchantRef
        ];

        if (!$technical && $amountEUR) {
            $fields["Valor ({$dccCurrency})"] = "{$amountEUR} {$dccCurrency}";
        }

        foreach ($fields as $label => $value) {
            $html .= "<tr><td style=\"padding:6px 0;color:#555;\">{$label}:</td><td style=\"text-align:right;\">{$value}</td></tr>";
        }

        $html .= "</table></div>";

        // DCC section
        if ($amountEUR) {
            $html .= "<div style=\"margin-top:20px;background:#f0f7ff;border-radius:10px;padding:15px;border-left:4px solid #004aad;\">";
            $html .= "<p style=\"margin:0;font-size:0.95em;\"><strong>Taxa de conversão / Currency Conversion Rate:</strong> 1 {$dccCurrency} = {$dccRate} CVE</p>";
            $html .= "<p style=\"margin:5px 0 0 0;font-size:0.95em;\"><strong>Taxa do serviço DCC / DCC Markup:</strong> {$dccMarkup} {$dccCurrency}</p>";
            $html .= "<p style=\"margin:5px 0 0 0;font-size:0.95em;\"><strong>Moeda de Transação / Transaction Currency:</strong> {$dccCurrency}</p>";
            $html .= "<p style=\"margin:5px 0 0 0;font-size:0.95em;\"><strong>TOTAL:</strong> {$purchaseAmount} CVE</p>";
            $html .= "<p style=\"margin:5px 0 0 0;font-size:0.95em;\"><strong>TOTAL:</strong> {$amountEUR} {$dccCurrency}</p>";
            $html .= "<p style=\"margin:10px 0 0 0;font-size:0.9em;\">I have been offered choice of currencies and agreed to pay in <strong>{$dccCurrency}</strong>.</p>";
            $html .= "<p style=\"margin:2px 0 0 0;font-size:0.9em;\">Dynamic Currency Conversion (DCC) offered by rede Vinti4.</p>";
            $html .= "<p style=\"margin:2px 0 0 0;font-size:0.9em;\">Exchange rate provided by Banco de Cabo Verde.</p>";
            $html .= "</div>";
        }

        $html .= "<p style=\"text-align:center;margin-top:25px;color:#777;font-size:0.8em;\">© 2025 SISP — Cabo Verde Payment System</p>";
        $html .= "</div>";

        return $html;
    }




    /* ------------------------------------------------------------------------
     | JsonSerializable Implementation
     |-----------------------------------------------------------------------*/
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
