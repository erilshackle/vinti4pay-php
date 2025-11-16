<?php

namespace Erilshk\Vinti4Pay\Services;

use Erilshk\Vinti4Pay\Exceptions\Vinti4Exception;
use Erilshk\Vinti4Pay\Services\Sisp;

/**
 * Class Vinti4PaymentRequest
 *
 * Responsável por preparar, validar e renderizar requisições de pagamento Vinti4Net.
 * Inclui geração de fingerprints, purchaseRequest 3DS e form HTML para submissão.
 *
 * @author Eril
 * @version 1.0.0
 */
class SispPaymentRequest extends Sisp
{
    // protected string $posID;
    // protected string $posAuthCode;
    // protected string $endpoint;

    public string $language = 'pt';

    public function __construct(
        protected string $posID,
        protected string $posAuthCode,
        protected  ?string $endpoint = null
    ) {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->endpoint = $endpoint ?? parent::DEFAULT_BASE_URL;
    }

    // ----------------------------------------------------------
    // PUBLIC METHODS
    // ----------------------------------------------------------

    /**
     * Prepara uma requisição de pagamento completa, pronta para ser renderizada.
     *
     * @param array $params
     * @return array{postUrl:string,fields:array}
     * @throws Vinti4Exception
     */
    public function preparePayment(array $params): array
    {
        if (empty($params['amount'])) {
            throw new Vinti4Exception("preparePayment: 'amount' is required.");
        }
        if (empty($params['responseUrl'])) {
            throw new Vinti4Exception("preparePayment: 'responseUrl' is required.");
        }

        $amount = (float)$params['amount'];
        $responseUrl = $params['responseUrl'];
        $billing = $params['billing'] ?? [];
        $dateTime = date('Y-m-d H:i:s');

        $fields = [
            'transactionCode' => (string)($params['transactionCode'] ?? parent::TRANSACTION_TYPE_PURCHASE),
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession' => $params['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => $amount,
            'currency' => $params['currency'] ?? parent::CURRENCY_CVE,
            'is3DSec' => '1',
            'urlMerchantResponse' => $responseUrl,
            'languageMessages' => $params['languageMessages'] ?? $this->language,
            'timeStamp' => $params['timeStamp'] ?? $dateTime,
            'fingerprintversion' => '1',
            'entityCode' => $params['entityCode'] ?? '',
            'referenceNumber' => $params['referenceNumber'] ?? '',
        ];

        // Se for compra, gera o purchaseRequest
        if ($fields['transactionCode'] === parent::TRANSACTION_TYPE_PURCHASE && !empty($billing)) {
            $billing = array_merge($this->formatBillingUserData($billing['user'] ?? []), $billing);
            $required = ['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'];

            $missing = array_diff($required, array_keys($billing));
            if ($missing) {
                throw new Vinti4Exception("Missing billing fields: " . implode(', ', $missing));
            }

            $fields['purchaseRequest'] = $this->generatePurchaseRequest(
                $billing['billAddrCountry'],
                $billing['billAddrCity'],
                $billing['billAddrLine1'],
                $billing['billAddrPostCode'],
                $billing['email'],
                array_diff_key($billing, array_flip($required))
            );
        }

        $fields['fingerprint'] = $this->generateFingerprint($fields);

        $postUrl = $this->endpoint . '?' . http_build_query([
            'FingerPrint'        => $fields['fingerprint'],
            'TimeStamp'          => $fields['timeStamp'],
            'FingerPrintVersion' => $fields['fingerprintversion'],
        ]);

        return ['postUrl' => $postUrl, 'fields' => $fields];
    }

    /**
     * Renderiza um formulário HTML auto-submissível.
     */
    public function renderPaymentForm(array $paymentData): string
    {
        $htmlFields = '';
        $postUrl = htmlspecialchars($paymentData['postUrl'], ENT_QUOTES, 'UTF-8');

        foreach ($paymentData['fields'] as $key => $value) {
            $htmlFields .= "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>";
        }

        return "
        <html>
            <head><title>Vinti4 Payment</title></head>
            <body onload='document.forms[0].submit()'>
                <form action='{$postUrl}' method='post'>
                    {$htmlFields}
                </form>
                <p>Processando pagamento... Aguarde.</p>
            </body>
        </html>";
    }

    // ----------------------------------------------------------
    // HELPER METHODS
    // ----------------------------------------------------------

    /**
     * Gera fingerprint da requisição.
     */
    protected function generateFingerprint(array $data): string
    {
        $entityCode = (int)($data['entityCode'] ?? 0);
        $referenceNumber = (int)($data['referenceNumber'] ?? 0);
        $amountInMille = (int)((float)$data['amount'] * 1000);

        $toHash = base64_encode(hash('sha512', $this->posAuthCode, true)) .
            $data['timeStamp'] .
            $amountInMille .
            $data['merchantRef'] .
            $data['merchantSession'] .
            $data['posID'] .
            $data['currency'] .
            $data['transactionCode'] .
            $entityCode .
            $referenceNumber;

        return base64_encode(hash('sha512', $toHash, true));
    }

    /**
     * Cria payload 3DS purchaseRequest.
     */
    protected function generatePurchaseRequest(
        string $country,
        string $city,
        string $line1,
        string $postcode,
        string $email,
        array $additional = []
    ): string {
        // Campos base de faturação
        $payload = array_merge([
            'billAddrCountry' => $country,
            'billAddrCity'    => $city,
            'billAddrLine1'   => $line1,
            'billAddrPostCode' => $postcode,
            'email'           => $email,
        ], $additional);

        // Duplicar endereço de envio se addrMatch = Y
        if (($payload['addrMatch'] ?? 'N') === 'Y') {
            $payload['shipAddrCountry']  = $payload['billAddrCountry'];
            $payload['shipAddrCity']     = $payload['billAddrCity'];
            $payload['shipAddrLine1']    = $payload['billAddrLine1'];
            $payload['shipAddrLine2']    = $payload['billAddrLine2'] ?? null;
            $payload['shipAddrPostCode'] = $payload['billAddrPostCode'] ?? null;
            $payload['shipAddrState']    = $payload['billAddrState'] ?? null;
        }

        // Campos permitidos (documentados)
        $allowed = [
            'acctID',
            'acctInfo',
            'email',
            'addrMatch',
            'billAddrCity',
            'billAddrCountry',
            'billAddrLine1',
            'billAddrLine2',
            'billAddrLine3',
            'billAddrPostCode',
            'billAddrState',
            'shipAddrCity',
            'shipAddrCountry',
            'shipAddrLine1',
            'shipAddrLine2',
            'shipAddrLine3',
            'shipAddrPostCode',
            'shipAddrState',
            'workPhone',
            'mobilePhone'
        ];

        // Subcampos permitidos para objetos aninhados
        $allowedNested = [
            'acctInfo' => [
                'chAccAgeInd',
                'chAccChange',
                'chAccDate',
                'chAccPwChange',
                'chAccPwChangeInd',
                'suspiciousAccActivity'
            ],
            'workPhone' => ['cc', 'subscriber'],
            'mobilePhone' => ['cc', 'subscriber'],
        ];

        // Limpeza recursiva
        $clean = [];
        foreach ($payload as $key => $value) {
            if (in_array($key, $allowed, true)) {
                if (is_array($value) && isset($allowedNested[$key])) {
                    $clean[$key] = array_intersect_key(
                        $value,
                        array_flip($allowedNested[$key])
                    );
                } elseif (!is_array($value)) {
                    $clean[$key] = $value;
                }
            }
        }

        // Remover vazios
        $clean = array_filter($clean, fn($v) => $v !== null && $v !== '' && $v !== []);

        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new Vinti4Exception("Failed to encode purchaseRequest to JSON.");
        }

        return base64_encode($json);
    }

    /**
     * Formata dados do utilizador para faturação.
     */
    private function formatBillingUserData(array|object $user): array
    {
        $billing = [];

        // Função interna para pegar valor de array ou objeto
        $get = fn($key, $default = null) =>
        is_array($user) ? ($user[$key] ?? $default) : ($user->$key ?? $default);

        // Campos básicos
        if ($email = $get('email')) $billing['email'] = $email;
        if ($country = $get('country')) $billing['billAddrCountry'] = $country;
        if ($city = $get('city')) $billing['billAddrCity'] = $city;
        if ($line1 = $get('address', $get('address1'))) $billing['billAddrLine1'] = $line1;
        if ($line2 = $get('address2')) $billing['billAddrLine2'] = $line2;
        if ($line3 = $get('address3')) $billing['billAddrLine3'] = $line3;
        if ($postcode = $get('postCode')) $billing['billAddrPostCode'] = $postcode;
        if ($state = $get('state')) $billing['billAddrState'] = $state;

        // Telefones
        if ($mobile = $get('mobilePhone', $get('phone'))) {
            $billing['mobilePhone'] = [
                'cc' => $get('mobilePhoneCC', '238'),
                'subscriber' => $mobile
            ];
        }

        if ($work = $get('workPhone')) {
            $billing['workPhone'] = [
                'cc' => $get('workPhoneCC', '238'),
                'subscriber' => $work
            ];
        }

        // Campos acctInfo para 3DS
        $acctID = $get('id');
        $createdAt = $get('created_at');
        $updatedAt = $get('updated_at');
        $suspicious = $get('suspicious');

        if ($acctID || $createdAt || $updatedAt || isset($suspicious)) {
            $billing['acctID'] = $acctID ?? '';
            $billing['acctInfo'] = [
                'chAccAgeInd' => $get('chAccAgeInd', '01'), // opcional, default 01
                'chAccChange' => $updatedAt ? date('Ymd', strtotime($updatedAt)) : '',
                'chAccDate' => $createdAt ? date('Ymd', strtotime($createdAt)) : '',
                'chAccPwChange' => $updatedAt ? date('Ymd', strtotime($updatedAt)) : '',
                'chAccPwChangeInd' => $get('chAccPwChangeInd', '01'),
                'suspiciousAccActivity' => isset($suspicious) ? ($suspicious ? '02' : '01') : ''
            ];

            // Remove campos vazios dentro de acctInfo
            $billing['acctInfo'] = array_filter($billing['acctInfo'], fn($v) => $v !== null && $v !== '');
            if (empty($billing['acctInfo'])) unset($billing['acctInfo']);
            if ($billing['acctID'] === '') unset($billing['acctID']);
        }

        // Remove campos vazios do nível superior
        $billing = array_filter($billing, fn($v) => $v !== null && $v !== '');

        return $billing;
    }
}
