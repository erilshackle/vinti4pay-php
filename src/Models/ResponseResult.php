<?php

namespace Erilshk\Vinti4Pay\Models;

use Erilshk\Vinti4Pay\Traits\ReceiptGeneretorTrait;
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

    use ReceiptGeneretorTrait;

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

    public function isSuspicious(): bool
    {
        return $this->status === self::STATUS_INVALID_FINGERPRINT;
    }

    public function getData(?string $param = null): array
    {
        if($param){
            return $this->data[$param] ?? null;
        }
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
     * Checks if transaction has invalid fingerprint.
     *
     * @param &$result hold the resulted fingerprint came from postData;
     * @return string|null The invalid fingerprint or null otherwise.
     */
    public function hasInvalidFingerprint(&$result = null): ?string
    {
        if ($this->status === self::STATUS_INVALID_FINGERPRINT) {
            $result = $this->data['resultFingerPrint'];
            return true;
        }
        $result = null;
        return false;
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

    /* ------------------------------------------------------------------------
     | JsonSerializable Implementation
     |-----------------------------------------------------------------------*/
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
