<?php

namespace Erilshk\Vinti4Pay;

use Erilshk\Vinti4Pay\Exceptions\Vinti4Exception;
use Erilshk\Vinti4Pay\Models\Receipt;
use Erilshk\Vinti4Pay\Models\ResponseResult;



/**
 * Vinti4Pay (Client)
 *
 * Main entry point for interacting with the Vinti4Net payment system.
 * Provides methods to prepare purchases, service payments, recharges, and refunds,
 * generate HTML payment forms, and process callback responses.
 *
 * Example usage:
 * ```php
 * $client = new Vinti4Client($posID, $authCode);
 * $client->preparePurchase(150.00, $billing);
 * echo $client->createPaymentForm('https://example.com/callback');
 * ```
 *
 * @package Erilshk\Vinti4Pay
 * @author Eril TS Carvalho
 * @version 1.0.0
 * @license MIT
 */
class Vinti4PayClient
{
    protected Vinti4Pay $sdk;
    protected ?array $request = null;
    protected array $params = [];
    protected string $mode = '';

    /**
     * @var array List of allowed keys for setRequestParams()
     */
    protected array $allowedParams = [
        'currency',
        'languageMessages',
        'entityCode',
        'referenceNumber',
        'merchantRef',
        'merchantSession',
        'addrMatch',
        'purchaseRequest',
        'user',
    ];

    /**
     * Vinti4Client constructor.
     *
     * Initializes the POS credentials and creates the default SDK instance.
     *
     * @param string $posID Point-of-Sale ID
     * @param string $posAutCode Authorization code
     * @param string|null $endpoint Optional custom endpoint URL (use for testing)
     */
    public function __construct(protected string $posID, protected string $posAutCode, protected ?string $endpoint = null)
    {
        $this->sdk = new Vinti4Pay($posID, $posAutCode, $endpoint);
    }


    protected function ensureNotPrepared(): void
    {
        if ($this->request !== null) {
            throw new Vinti4Exception("Já existe uma transação configurada neste objeto.");
        }
    }

    /** Converts Currency ISO to country code 
     * @link https://www.iban.com/country-codes 
     */
    private function currencyToCode(string $currency): int
    {
        return match (strtoupper($currency)) {
            'CVE' => 132, // Cape Verdean Escudo
            'USD' => 840, // US Dollar
            'EUR' => 978, // Euro
            'BRL' => 986, // Brazilian Real
            'GBP' => 826, // British Pound
            'JPY' => 392, // Japanese Yen
            'AUD' => 36,  // Australian Dollar
            'CAD' => 124, // Canadian Dollar
            'CHF' => 756, // Swiss Franc
            'CNY' => 156, // Chinese Yuan
            'INR' => 356, // Indian Rupee
            'ZAR' => 710, // South African Rand
            'RUB' => 643, // Russian Ruble
            'MXN' => 484, // Mexican Peso
            'KRW' => 410, // South Korean Won
            'SGD' => 702,  // Singapore Dollar
            default => is_numeric($currency) ? (int)$currency : throw new Vinti4Exception("Invalid currency code: $currency")
        };
    }

    /**
     * Set additional optional parameters for the transaction.
     *
     * Only keys defined in $allowedParams are permitted.
     *
     * @param array<string, mixed> $params Key-value pairs of optional parameters
     * @return static Current instance for chaining
     * @throws Vinti4Exception If a key is not allowed
     */
    public function setRequestParams(array $params): static
    {
        foreach ($params as $k => $v) {
            if (!in_array($k, $this->allowedParams, true)) {
                throw new Vinti4Exception("requestParams: parameter key '{$k}' is not allowed.");
            }

            if ($k == 'currency') $v = $this->currencyToCode($v);

            $this->params[$k] = $v;
        }
        return $this;
    }

    /**
     * Prepares a purchase transaction (TransactionCode=1).
     *
     * Initializes the required data for a purchase operation, including amount and billing information.
     * Can be chained with `createPaymentForm()` to generate the final HTML form.
     *
     * @param float|string $amount Transaction amount
     * @param array $billing Required billing data:
     *   - 'billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'
     *   - Optional: 'addrMatch', 'billAddrLine2', etc.
     * @param string|null $merchantRef Merchant reference (optional)
     * @param string|null $merchantSession Merchant session (optional)
     * @return static Current instance for chaining
     * @throws Vinti4Exception If a transaction has already been prepared
     */
    public function preparePurchase(float|string $amount, array $billing, ?string $merchantRef = null, ?string $merchantSession = null): static
    {
        $this->ensureNotPrepared();
        $this->mode = 'purchase';

        $this->request = [
            'transactionCode' => Vinti4Pay::TRANSACTION_TYPE_PURCHASE,
            'amount'          => $amount,
            'billing'         => $billing,
            'merchantRef'     => $merchantRef ?: null,
            'merchantSession' => $merchantSession ?: null,
        ];

        return $this;
    }

    /**
     * Prepares a service payment (TransactionCode=2).
     *
     * Initializes the required data for a service payment, including entity code and reference number.
     * Can be chained with `createPaymentForm()` to generate the final HTML form.
     *
     * @param float|string $amount Transaction amount
     * @param string $entity Numeric entity code
     * @param string $reference Numeric reference number
     * @param string|null $merchantRef Merchant reference (optional)
     * @param string|null $merchantSession Merchant session (optional)
     * @return static Current instance for chaining
     * @throws Vinti4Exception If a transaction has already been prepared
     */
    public function prepareServicePayment(float|string $amount, string $entity, string $reference, ?string $merchantRef = null, ?string $merchantSession = null): static
    {
        $this->ensureNotPrepared();
        $this->mode = 'service';

        $this->request = [
            'transactionCode'  => Vinti4Pay::TRANSACTION_TYPE_SERVICE_PAYMENT,
            'amount'           => $amount,
            'entityCode'       => $entity,
            'referenceNumber'  => $reference,
            'merchantRef'      => $merchantRef,
            'merchantSession'  => $merchantSession,
        ];

        return $this;
    }

    /**
     * Prepares a recharge transaction (TransactionCode=3).
     *
     * Initializes the required data for a recharge operation, including entity code and reference number.
     * Can be chained with `createPaymentForm()` to generate the final HTML form.
     *
     * @param float|string $amount Transaction amount
     * @param string $entity Numeric entity code
     * @param string $number Numeric reference number
     * @param string|null $merchantRef Merchant reference (optional)
     * @param string|null $merchantSession Merchant session (optional)
     * @return static Current instance for chaining
     * @throws Vinti4Exception If a transaction has already been prepared
     */
    public function prepareRecharge(float|string $amount, string $entity, string $number, ?string $merchantRef = null, ?string $merchantSession = null): static
    {
        $this->ensureNotPrepared();
        $this->mode = 'recharge';

        $this->request = [
            'transactionCode'  => Vinti4Pay::TRANSACTION_TYPE_RECHARGE,
            'amount'           => $amount,
            'entityCode'       => $entity,
            'referenceNumber'  => $number,
            'merchantRef'      => $merchantRef,
            'merchantSession'  => $merchantSession,
        ];

        return $this;
    }

    /**
     * Prepares a refund transaction (TransactionCode=refund).
     *
     * Initializes the required data for a refund using the SispRefund wrapper.
     *
     * @param float|string $amount Refund amount
     * @param string $merchantRef Merchant reference
     * @param string $merchantSession Merchant session
     * @param string $transactionID Original transaction ID
     * @param string $clearingPeriod Clearing period
     * @return static Current instance for chaining
     * @throws Vinti4Exception If a transaction has already been prepared
     */
    public function prepareRefund(float|string $amount, string $merchantRef, string $merchantSession, string $transactionID, string $clearingPeriod): static
    {
        $this->ensureNotPrepared();
        $this->mode = 'refund';

        $this->request = [
            'transactionCode'   => Vinti4Pay::TRANSACTION_TYPE_REFUND,
            'amount'            => $amount,
            'clearingPeriod'    => $clearingPeriod,
            'transactionID'     => $transactionID,
            'merchantRef'       => $merchantRef,
            'merchantSession'   => $merchantSession,
        ];

        return $this;
    }

    /**
     * Generates the HTML form for the previously prepared transaction.
     *
     * Must be called **after** one of the `prepare*` methods (`preparePurchase`, `prepareServicePayment`,
     * `prepareRecharge`, `prepareRefund`). Returns an HTML form ready for automatic submission to Vinti4Net,
     * including fingerprint and timestamp.
     *
     * @param string $responseUrl Merchant callback URL
     * @param string $language Language for messages ('pt' or 'en')
     * @return string HTML form ready to submit
     * @throws Vinti4Exception If no transaction has been prepared
     */
    public function createPaymentForm(string $responseUrl, $redirectMessage = 'processing...'): string
    {
        if ($this->request === null) {
            throw new Vinti4Exception("CreatePaymentForm Error: No transaction has been prepared.");
        }

        $prepared = $this->sdk->preparePayment($responseUrl, array_merge(
            $this->params,
            $this->request
        ));

        return $this->sdk->renderForm($prepared, $redirectMessage);
    }


    /**
     * Processes and validates the callback response from Vinti4Net, bing wither Payment or refund.
     *
     * Delegates the processing to the underlying SDK instance.
     * Validates the fingerprint, checks for user cancellation, success status,
     * and parses DCC data if available.
     *
     * @param array $postData The POST data received from Vinti4Net callback (usually $_POST)
     * @return ResponseResult An object containing the status, success flag, message, original data, and any DCC info
     * @throws Vinti4Exception If the response is invalid or cannot be processed
     */
    public function processResponse(array $postData): ResponseResult
    {
        // Determina o SDK correto
        if (
            (isset($postData['transactionCode']) && $postData['transactionCode'] === Vinti4Pay::TRANSACTION_TYPE_REFUND) ||
            (isset($postData['messageType']) && $postData['messageType'] === '10') ||
            (isset($postData['reversal']) && $postData['reversal'] === 'R')
        ) {
            return $this->sdk->processRefundResponse($postData);
        }
        return $this->sdk->processPaymentResponse($postData);
    }

    public function receipt($data)
    {
        return new Receipt($data);
    }
}
