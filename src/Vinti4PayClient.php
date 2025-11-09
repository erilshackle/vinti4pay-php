<?php

namespace Erilshk\Vinti4Pay;

use Erilshk\Vinti4Pay\Vinti4Pay;
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
    protected array $results = [];
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
     * Sets and validates customer billing details.
     * 
     * **Required fields**
     * | Param | Type | Description |
     * |--------|------|-------------|
     * | `$email` | string | Customer email |
     * | `$country` | string | Billing country code (ISO alpha or numeric) |
     * | `$city` | string | Billing city |
     * | `$address` | string | Billing address line 1 |
     * | `$postCode` | string | Postal code |
     *
     * **Optional fields (in $aditional)**
     * - `billAddrLine2`, `billAddrLine3`, `billAddrState`
     * - `shipAddrCountry`, `shipAddrCity`, `shipAddrLine1`, `shipAddrPostCode`, `shipAddrState`
     * - `addrMatch` = 'Y' or 'N' (copy billing to shipping if 'Y')
     * - `acctID`, `acctInfo` (array), `workPhone`, `mobilePhone` (`['cc'=>'238','subscriber'=>'9112233']`)
     *
     * @param string $email
     * @param string $country
     * @param string $city
     * @param string $address
     * @param string $postCode
     * @param array  $aditional
     * @return static
     * @throws Vinti4Exception If required fields are invalid.
     */
    public function setBillingParams(
        string $email,
        string $country,
        string $city,
        string $address,
        string $postCode,
        array $aditional = []
    ): static {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Vinti4Exception("Invalid email.");
        }

        $addrMatch = strtoupper($aditional['addrMatch'] ?? '');
        $addrMatch = in_array($addrMatch, ['Y', 'N']) ? $addrMatch : 'N';

        $billing = [
            'billAddrCountry' => $country,
            'billAddrCity'    => $city,
            'billAddrLine1'   => $address,
            'billAddrPostCode' => $postCode,
            'email'           => $email,
            'addrMatch'       => $addrMatch,
        ];

        foreach (
            [
                'billAddrLine2',
                'billAddrLine3',
                'billAddrState',
                'shipAddrCountry',
                'shipAddrCity',
                'shipAddrLine1',
                'shipAddrPostCode',
                'shipAddrState',
                'acctID'
            ] as $k
        ) {
            if (!empty($aditional[$k])) $billing[$k] = $aditional[$k];
        }

        if (!empty($aditional['acctInfo']) && is_array($aditional['acctInfo'])) {
            $billing['acctInfo'] = array_filter($aditional['acctInfo'], fn($v) => $v !== '');
        }

        foreach (['workPhone', 'mobilePhone'] as $p) {
            if (!empty($aditional[$p])) {
                $phone = $aditional[$p];
                if (!isset($phone['cc'], $phone['subscriber'])) {
                    throw new Vinti4Exception("Phone '$p' must have 'cc' and 'subscriber'.");
                }
                $billing[$p] = ['cc' => trim($phone['cc']), 'subscriber' => trim($phone['subscriber'])];
            }
        }

        if ($addrMatch === 'Y') {
            $billing += [
                'shipAddrCountry' => $country,
                'shipAddrCity' => $city,
                'shipAddrLine1' => $address,
                'shipAddrPostCode' => $postCode,
            ];
            if (!empty($billing['billAddrState'])) $billing['shipAddrState'] = $billing['billAddrState'];
        }

        $this->params['billing'] = array_merge($this->params['billing'] ?? [], $billing);
        return $this;
    }


    /**
     * Set additional optional parameters for the transaction.
     *
     * Only keys defined in `$allowedParams` are permitted.
     *
     * Allowed parameters:
     *  - `currency` *(string|int)*: ISO4217 currency code or internal code (converted via `currencyToCode`)
     *  - `languageMessages` *(string)*: Language for messages (`pt` or `en`)
     *  - `entityCode` *(string)*: Entity code for service payments
     *  - `referenceNumber` *(string)*: Reference number for service payments
     *  - `merchantRef` *(string)*: Internal merchant reference
     *  - `merchantSession` *(string)*: Internal merchant session identifier
     *  - `addrMatch` *(bool|string)*: Optional address matching flag
     *  - `purchaseRequest` *(array)*: Optional 3DS purchase request data
     *  - `user` *(array|string)*: Optional user information
     *
     * @param array<string, mixed> $params Key-value pairs of optional parameters
     * @return static Current instance for method chaining
     * @throws Vinti4Exception If a parameter key is not allowed
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
     * @param array{
     * billAddrCountry:string, billAddrCity:string, billAddrLine1:string, billAddrPostCode:string, email:string
     * } $billing Required billing data:
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
     *
     * Example usage:
     *
     * Example usage:
     *
     * ```php
     * # Create client
     * $vinti4Pay = new Vinti4PayClient($posID, $authCode);
     *
     * # Prepare a purchase
     * $billing = [
     *     'billAddrCountry' => 'CV',
     *     'billAddrCity' => 'Praia',
     *     'billAddrLine1' => 'Av. Principal 10',
     *     'billAddrPostCode' => '7600',
     *     'email' => 'customer@email.cv'
     * ];
     *
     * $vinti4Pay->preparePurchase(1500.00, $billing);
     *
     * # Generate HTML form for submission
     * $htmlForm = $vinti4Pay->createPaymentForm('https://mysite.cv/vinti4/callback');
     * echo $htmlForm;
     * ```
     * @param string $responseUrl Merchant callback URL
     * @param string $language Language for messages ('pt' or 'en')
     * @return string HTML form ready to submit
     * @throws Vinti4Exception If no transaction has been prepared
     */
    public function createPaymentForm(string $responseUrl): string
    {
        if ($this->request === null) {
            throw new Vinti4Exception("CreatePaymentForm Error: No transaction has been prepared.");
        }

        $prepared = $this->sdk->preparePayment($responseUrl, array_merge(
            $this->request,
            $this->params,
        ));

        return $this->sdk->renderForm($prepared);
    }


    /**
     * Processes and validates the callback response from Vinti4Net (either payment or refund).
     *
     * This method delegates the actual processing to the underlying SDK instance.
     * It automatically determines whether the response is a refund or a standard payment
     * and calls the appropriate handler (`processRefundResponse` or `processPaymentResponse`).
     *
     * The response is validated for:
     *  - Fingerprint correctness
     *  - User cancellation
     *  - Success status
     *  - Optional DCC (Dynamic Currency Conversion) data
     *
     * @param array $postData The POST data received from Vinti4Net callback (usually `$_POST`)
     *
     * @return ResponseResult An object containing:
     *  - `status`: Status code ('SUCCESS', 'ERROR', 'CANCELLED', 'INVALID_FINGERPRINT', etc.)
     *  - `message`: Human-readable message
     *  - `success`: Boolean indicating if the transaction was successful
     *  - `data`: Original POST data
     *  - `dcc` (optional): DCC information if available
     *  - `debug` (optional): Debug information (e.g., fingerprint comparison)
     *
     * @throws Vinti4Exception If the response is invalid or cannot be processed
     *
     * Example usage:
     *
     * ```php
     * $postData = $_POST; // Data from Vinti4Net callback
     *
     * $responseResult = $vinti4Pay->processResponse($postData);
     *
     * if ($responseResult->isSuccessful()) {
     *     echo "Transaction successful: " . $responseResult->status;
     * } else {
     *     echo "Transaction failed: " . $responseResult->message;
     * }
     * ```
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

    public function receipt(ResponseResult|array $res)
    {
        $data = is_array($res) ? $res : $res->getData();
        return new Receipt($data);
    }
}
