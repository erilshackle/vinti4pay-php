<?php

namespace Erilshk\Vinti4Pay\Services;

abstract class Sisp
{

    // -------------------------
    // URLs and Constants
    // -------------------------

    const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment";

    // Transaction types
    const TRANSACTION_TYPE_PURCHASE = '1';
    const TRANSACTION_TYPE_SERVICE_PAYMENT = '2';
    const TRANSACTION_TYPE_RECHARGE = '3';
    const TRANSACTION_TYPE_REFUND = '4';

    /** @var string curency code @link https://www.iban.com/country-codes */
    const CURRENCY_CVE = '132'; // Cape Verde Escudo
    // Message types indicating success
    const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];


    // -------------------------
    // Credentials
    // -------------------------

    protected string $posID;
    protected string $posAuthCode;


    protected function  __construct(string $posID, string $posAuthCode)
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
    }

    protected abstract function generateFingerprint(array $data): string;

}
