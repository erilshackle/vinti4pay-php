<?php

/**
 * Classe Vinti4PayClient
 *
 * Cliente PHP para integração com o gateway de pagamentos Vinti4.
 * Permite preparar, enviar e processar transações de compra, serviço, recarga e reembolso.
 *
 * Funcionalidades principais:
 * - Criação de formulários automáticos de pagamento
 * - Geração de fingerprints (assinaturas) de segurança
 * - Processamento de callbacks (respostas) do gateway Vinti4Net
 * - Validação de respostas com verificação de integridade
 * 
 * @author Eril TS Carvalho
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/erilshackle/vinti4pay-php/blob/main/dist/docs.md
 */
class Vinti4PayClient
{
    /** @var string Identificador do ponto de venda (POS). */
    private string $posID;

    /** @var string Código de autenticação do POS fornecido pelo Vinti4. */
    private string $posAuthCode;

    /** @var string URL do endpoint de pagamento. */
    private string $endpoint;

    /** @var string Idioma padrão das mensagens (ex: 'pt', 'en'). */
    protected string $language = 'pt';

    /** @var array|null Dados da requisição atual. */
    private ?array $request = null;

    /** @var array Parâmetros adicionais para a requisição. */
    private array $params = [];

    /** @var string Modo atual da operação (purchase, refund, etc). */
    private string $mode = '';

    /** @var string Endpoint padrão de produção do gateway. */
    const DEFAULT_ENDPOINT = "https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment";

    /** @var string Código de transação - Compra. */
    const TRANSACTION_TYPE_PURCHASE = '1';

    /** @var string Código de transação - Pagamento de serviços. */
    const TRANSACTION_TYPE_SERVICE_PAYMENT = '2';

    /** @var string Código de transação - Recarga. */
    const TRANSACTION_TYPE_RECHARGE = '3';

    /** @var string Código de transação - Reembolso. */
    const TRANSACTION_TYPE_REFUND = '4';

    /** @var string Código numérico da moeda CVE (Escudo Cabo-verdiano). */
    const CURRENCY_CVE = '132';

    /** @var array Lista de tipos de mensagem que indicam sucesso na transação. */
    const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    /**
     * Construtor.
     *
     * @param string      $posID        ID do ponto de venda.
     * @param string      $posAuthCode  Código de autenticação do POS.
     * @param string|null $endpoint     URL do endpoint Vinti4 (opcional).
     */
    public function __construct(string $posID, string $posAuthCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->endpoint = $endpoint ?? self::DEFAULT_ENDPOINT;
    }

    private function ensureNotPrepared(): void
    {
        if ($this->request !== null) {
            throw new Exception("Já existe uma transação configurada neste objeto.");
        }
    }

    /**
     * Converte um código de moeda (alfabético ou numérico) para código numérico ISO 4217.
     *
     * @param string $currency Código da moeda (ex: "CVE" ou "978").
     * @return int Código numérico da moeda.
     * @throws Exception Caso o código seja inválido.
     */
    private function currencyToCode(string $currency): int
    {
        return match (strtoupper($currency)) {
            'CVE' => 132, 'USD' => 840, 'EUR' => 978,
            'BRL' => 986, 'GBP' => 826, 'JPY' => 392,
            'AUD' => 36,  'CAD' => 124, 'CHF' => 756,
            'CNY' => 156, 'INR' => 356, 'ZAR' => 710,
            'RUB' => 643, 'MXN' => 484, 'KRW' => 410,
            'SGD' => 702,
            default => is_numeric($currency)
                ? (int)$currency
                : throw new Exception("Invalid currency code: $currency")
        };
    }


    /**
     * Define parâmetros opcionais da requisição.
     *
     * Permite definir parâmetros adicionais de configuração que complementam 
     * a transação, como moeda, idioma, e dados de referência.
     * 
     * Os parâmetros são usados internamente por {@see preparePayment()} 
     * e enviados ao gateway junto com o formulário de pagamento.
     *
     * ---
     * Exemplo:
     * ```php
     * $client->setRequestParams([
     *     'currency' => 'CVE',
     *     'languageMessages' => 'pt',
     *     'entityCode' => '12345',
     *     'referenceNumber' => '2024110001',
     *     'addrMatch' => 'Y'
     * ]);
     * ```
     * ---
     *
     * @param array{
     * currency:string, languageMessages:string, entityCode:string,referenceNumber:string,
     * merchantRef:string, merchantSession:string, addrMatch:string, purchaseRequest:string, 
     * } $params Lista de parâmetros permitidos:
     *
     * #### Parâmetros disponíveis:
     * - **currency** `string|int` — Código da moeda (alfabético `"CVE"` ou numérico `132`).  
     *   Valores comuns: `"CVE"`, `"EUR"`, `"USD"`.  
     *   (Converte automaticamente para código ISO numérico.)
     *
     * - **languageMessages** `string` — Idioma das mensagens de retorno (`"pt"`, `"en"`, `"fr"`...).
     *
     * - **entityCode** `string` — Código da entidade (usado em pagamentos de serviços e recargas).
     *
     * - **referenceNumber** `string` — Referência do pagamento (entidade + referência, ex: `"123456789"`).
     *
     * - **merchantRef** `string` — Referência do comerciante (identificador do pedido).
     *
     * - **merchantSession** `string` — Sessão de pagamento única.
     *
     * - **addrMatch** `string` — `"Y"` para usar o mesmo endereço de cobrança e entrega.
     *
     * - **purchaseRequest** `string` — Conteúdo JSON base64 do pedido (geralmente gerado internamente).
     *
     * @return static Retorna a instância atual.
     * @throws Exception Se algum parâmetro não for reconhecido ou permitido.
     */
    public function setRequestParams(array $params): static
    {
        $allowedParams = [
            'currency', 'languageMessages', 'entityCode',
            'referenceNumber', 'merchantRef', 'merchantSession',
            'addrMatch', 'purchaseRequest'
        ];
        foreach ($params as $k => $v) {
            if (!in_array($k, $allowedParams, true)) {
                throw new Exception("requestParams: parameter key '{$k}' is not allowed.");
            }
            if ($k === 'currency') $v = $this->currencyToCode($v);
            $this->params[$k] = $v;
        }
        return $this;
    }

    /**
     * Define e valida os parâmetros de faturamento (Billing) e informações do cliente.
     *
     * Este método configura todos os dados necessários para o processamento de uma compra (PurchaseRequest),
     * incluindo as informações de faturamento, entrega, conta do cliente e telefones.
     * 
     * Os campos obrigatórios são passados diretamente como parâmetros formais,
     * enquanto campos opcionais são fornecidos via o array `$aditional`.
     *
     * ---
     * ### Campos obrigatórios
     * | Parâmetro | Tipo | Descrição |
     * |------------|------|------------|
     * | `$billAddrCountry` | string | Código ISO numérico ou alfabético do país (ex: `CV` ou `132`) |
     * | `$billAddrCity` | string | Cidade de faturamento |
     * | `$billAddrLine1` | string | Endereço principal |
     * | `$billAddrPostCode` | string | Código postal |
     * | `$email` | string | E-mail do cliente |
     *
     * ---
     * ### Campos opcionais suportados
     * | Chave | Tipo | Descrição |
     * |-------|------|------------|
     * | `billAddrLine2`, `billAddrLine3` | string | Linhas adicionais do endereço |
     * | `billAddrState` | string | Estado, província ou ilha |
     * | `addrMatch` | string (`Y`/`N`) | Se o endereço de entrega é o mesmo do faturamento |
     * | `shipAddrCountry`, `shipAddrCity`, `shipAddrLine1`, `shipAddrPostCode`, `shipAddrState` | string | Dados do endereço de entrega |
     * | `acctID` | string | Identificador interno da conta do cliente |
     * | `acctInfo` | array | Dados de contexto da conta para 3DS (exemplo abaixo) |
     * | `workPhone`, `mobilePhone` | array | Telefones no formato `['cc' => '238', 'subscriber' => '9112233']` |
     *
     * ---
     * #### Estrutura esperada de `acctInfo` (opcional)
     * ```php
     * [
     *   'chAccAgeInd' => '05',
     *   'chAccChange' => '20220328',
     *   'chAccDate' => '20220328',
     *   'chAccPwChange' => '20220328',
     *   'chAccPwChangeInd' => '05',
     *   'suspiciousAccActivity' => '01'
     * ]
     * ```
     *
     * ---
     * ### Exemplo de uso
     * ```php
     * $client->setBillingParams(
     *     billAddrCountry: '132',
     *     billAddrCity: 'Praia',
     *     billAddrLine1: 'Palmarejo',
     *     billAddrPostCode: '7600',
     *     email: 'cliente@exemplo.cv',
     *     aditional: [
     *         'addrMatch' => 'Y',
     *         'mobilePhone' => ['cc' => '238', 'subscriber' => '9112233']
     *     ]
     * );
     * ```
     *
     * ---
     * @param string $email     - email: E-mail do cliente.
     * @param string $country   - billAddrCountry: Código do país de faturamento.
     * @param string $city      - billAddrCity  - Cidade de faturamento.
     * @param string $address   - billAddrLine1 Endereço principal.
     * @param string $postCode  - billAddrPostCode Código postal.
     * @param array $aditional  - Campos adicionais conforme citados acima.
     *
     * @return static
     *
     * @throws Exception Se algum campo obrigatório for inválido ou malformado.
     */
    public function setBillingParams(
        string $email,
        string $country,
        string $city,
        string $address,
        string $postCode,
        array $aditional = []
    ): static {
        // Validação básica
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("setBillingParams: e-mail inválido fornecido.");
        }

        // Normaliza addrMatch
        $addrMatch = strtoupper($aditional['addrMatch'] ?? 'N');
        $addrMatch = in_array($addrMatch, ['Y', 'N'], true) ? $addrMatch : 'N';

        // Monta o payload base
        $billing = [
            'billAddrCountry' => $country,
            'billAddrCity' => $city,
            'billAddrLine1' => $address,
            'billAddrPostCode' => $postCode,
            'email' => $email,
            'addrMatch' => $addrMatch,
        ];

        // Campos opcionais simples
        $simpleAditional = [
            'billAddrLine2', 'billAddrLine3', 'billAddrState',
            'shipAddrCountry', 'shipAddrCity', 'shipAddrLine1',
            'shipAddrPostCode', 'shipAddrState',
            'acctID'
        ];

        foreach ($simpleAditional as $key) {
            if (!empty($aditional[$key])) {
                $billing[$key] = $aditional[$key];
            }
        }

        // Campos complexos — acctInfo, workPhone, mobilePhone
        if (!empty($aditional['acctInfo']) && is_array($aditional['acctInfo'])) {
            $billing['acctInfo'] = array_filter($aditional['acctInfo'], fn($v) => $v !== '');
        }

        foreach (['workPhone', 'mobilePhone'] as $phoneType) {
            if (!empty($aditional[$phoneType]) && is_array($aditional[$phoneType])) {
                $phone = $aditional[$phoneType];
                if (!isset($phone['cc'], $phone['subscriber'])) {
                    throw new Exception("setBillingParams: campo '$phoneType' deve conter 'cc' e 'subscriber'.");
                }
                $billing[$phoneType] = [
                    'cc' => trim($phone['cc']),
                    'subscriber' => trim($phone['subscriber'])
                ];
            }
        }

        // Copia endereço de entrega se addrMatch = Y
        if ($addrMatch === 'Y') {
            $billing['shipAddrCountry']  = $country;
            $billing['shipAddrCity']     = $city;
            $billing['shipAddrLine1']    = $address;
            $billing['shipAddrPostCode'] = $postCode;
            if (!empty($billing['billAddrState'])) {
                $billing['shipAddrState'] = $billing['billAddrState'];
            }
        }

        // Armazena internamente
        $this->params['billing'] = $billing;

        return $this;
    }


    // -------------------------
    // Métodos públicos para preparar transações
    // -------------------------
    
    /**
     * Prepara uma transação de compra (Purchase).
     *
     * Este método configura uma operação de **compra** (transação do tipo `1`), 
     * que normalmente requer os dados de faturamento do cliente (billing) para 
     * envio ao gateway Vinti4.
     *
     * Após preparar a compra, utilize o método {@see createPaymentForm()} para 
     * gerar o formulário HTML de redirecionamento do cliente ao ambiente de pagamento.
     *
     * @see setBillingParams() - obrigatório para perchase Request.
     *
     * @param float|string  $amount          Valor total da compra (em escudos CVE, sem formatação).
     * @param array{billAddrCountry:string,billAddrCity:string,billAddrLine1:string,billAddrPostCode:string,email:string,addrMatch:string
     * }         $billing         Dados de faturamento do cliente:
     * - **billAddrCountry** `string` — Código ISO do país (ex: `"CV"`, `"PT"`):
     * - **billAddrCity** `string` — Cidade do cliente.
     * - **billAddrLine1** `string` — Endereço principal do cliente.
     * - **billAddrPostCode** `string` — Código postal do cliente.
     * - **email** `string` — E-mail de contato do cliente.
     *
     * @param string|null   $merchantRef     Referência única do comerciante (ex: número do pedido).
     * @param string|null   $merchantSession Sessão ou identificador temporário da transação.
     *
     * @return static Retorna a instância atual para encadeamento de chamadas (fluent interface).
     * @throws Exception Se já existir uma transação configurada.
     * @todo remover parametro $billing e deixar que setBillingParams() seja exclusivamente responsavel por isso.
     * @todo remover parametro merchantSession pois nao é um parametro que deve chamar a antenção logo no prepare. mover para setRequestParams
     */
    public function preparePurchase(float|string $amount, array $billing, ?string $merchantRef = null, ?string $merchantSession = null): static
    {
        $this->ensureNotPrepared();
        $this->mode = 'purchase';
        $this->request = [
            'transactionCode' => self::TRANSACTION_TYPE_PURCHASE,
            'amount' => $amount,
            'billing' => $billing,
            'merchantRef' => $merchantRef,
            'merchantSession' => $merchantSession
        ];
        return $this;
    }

    /**
     * Prepara uma transação de pagamento de serviços (Service Payment).
     *
     * Esta operação (código de transação `2`) é usada para efetuar o pagamento de faturas 
     * ou serviços de entidades conveniadas com o Vinti4, utilizando o código da entidade 
     * e o número de referência do documento a pagar.
     *
     * ---
     * Exemplo:
     * ```php
     * $client = new Vinti4PayClient($posID, $posAuthCode);
     *
     * $client->prepareServicePayment(
     *     2500.00,
     *     '201',          // Código da entidade (ex: empresa de água/luz)
     *     '987654321',    // Referência da fatura
     * );
     * ```
     * ---
     *
     * @param float|string $amount          Valor a pagar (numérico, sem separadores).
     * @param string       $entity          Código da entidade prestadora do serviço.
     * @param string       $reference       Número de referência da fatura/serviço.
     * @param string|null  $merchantRef     Referência interna do comerciante.
     * @param string|null  $merchantSession Sessão única da transação.
     *
     * @return static Retorna a instância atual para encadeamento.
     * @throws Exception Se já existir uma transação configurada.
     * @todo remover parametro merchantSession pois nao é um parametro que deve chamar a antenção logo no prepare. mover para setRequestParams
     */
    public function prepareServicePayment(float|string $amount, string $entity, string $reference, ?string $merchantRef = null, ?string $merchantSession = null): static
    {
        $this->ensureNotPrepared();
        $this->mode = 'service';
        $this->request = [
            'transactionCode' => self::TRANSACTION_TYPE_SERVICE_PAYMENT,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $reference,
            'merchantRef' => $merchantRef,
            'merchantSession' => $merchantSession
        ];
        return $this;
    }

     /**
     * Prepara uma transação de recarga (Recharge).
     *
     * Esta operação (código `3`) é usada para efetuar recargas de telemóvel 
     * ou outros serviços pré-pagos através do gateway Vinti4.
     *
     * ---
     * Exemplo:
     * ```php
     * $client = new Vinti4PayClient($posID, $posAuthCode);
     *
     * $client->prepareRecharge(
     *     500.00,
     *     '301',          // Código da entidade (ex: operadora móvel)
     *     '981234567',    // Número a recarregar
     * );
     * ```
     * ---
     *
     * @param float|string $amount          Valor da recarga.
     * @param string       $entity          Código da entidade de recarga (operadora).
     * @param string       $number          Número de telefone ou identificador do cliente.
     * @param string|null  $merchantRef     Referência interna do comerciante.
     * @param string|null  $merchantSession Sessão única da transação.
     *
     * @return static Retorna a instância atual.
     * @throws Exception Se já existir uma transação configurada.
     * @todo remover parametro merchantSession pois nao é um parametro que deve chamar a antenção logo no prepare. mover para setRequestParams
     */
    public function prepareRecharge(float|string $amount, string $entity, string $number, ?string $merchantRef = null, ?string $merchantSession = null): static
    {
        $this->ensureNotPrepared();
        $this->mode = 'recharge';
        $this->request = [
            'transactionCode' => self::TRANSACTION_TYPE_RECHARGE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
            'merchantRef' => $merchantRef,
            'merchantSession' => $merchantSession
        ];
        return $this;
    }

    /**
     * Prepara uma transação de reembolso (Refund).
     *
     * Esta operação (código `4`) permite reverter uma transação previamente 
     * autorizada e liquidada. É utilizada quando é necessário devolver o valor 
     * total ou parcial ao titular do cartão.
     *
     * ---
     * Exemplo:
     * ```php
     * $client = new Vinti4PayClient($posID, $posAuthCode);
     *
     * $client->prepareRefund(
     *     1250.00,
     *     'PEDIDO-12345',
     *     'SESSION-67890',
     *     'TX1234567890',   // ID da transação original
     *     '20241015'        // Período de liquidação (clearingPeriod)
     * );
     * ```
     * ---
     *
     * @param float|string $amount          Valor a ser reembolsado.
     * @param string       $merchantRef     Referência original do comerciante (pedido).
     * @param string       $merchantSession Sessão original da transação.
     * @param string       $transactionID   Identificador da transação original (retornado pelo Vinti4).
     * @param string       $clearingPeriod  Data do período de liquidação no formato `YYYYMMDD`.
     *
     * @return static Retorna a instância atual.
     * @throws Exception Se já existir uma transação configurada.
     */
    public function prepareRefund(float|string $amount, string $merchantRef, string $merchantSession, string $transactionID, string $clearingPeriod): static
    {
        $this->ensureNotPrepared();
        $this->mode = 'refund';
        $this->request = [
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'amount' => $amount,
            'merchantRef' => $merchantRef,
            'merchantSession' => $merchantSession,
            'transactionID' => $transactionID,
            'clearingPeriod' => $clearingPeriod
        ];
        return $this;
    }

    // -------------------------
    // Método protegido central para preparar pagamento/refund
    // -------------------------

    /**
     * Prepara os dados e gera o fingerprint da transação.
     *
     * @param array $data Dados da transação.
     * @return array Estrutura contendo 'postUrl' e 'fields'.
     * @throws Exception Se campos obrigatórios estiverem ausentes.
     */
    protected function preparePayment(array $data): array
    {
        if (empty($data['amount'])) {
            throw new Exception("preparePayment requires 'amount'");
        }

        // Campos básicos
        $fields = [
            'transactionCode'   => $data['transactionCode'] ?? self::TRANSACTION_TYPE_PURCHASE,
            'posID'             => $this->posID,
            'merchantRef'       => $data['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession'   => $data['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount'            => (int)(float)$data['amount'],
            'currency'          => $data['currency'] ?? self::CURRENCY_CVE,
            'is3DSec'           => '1',
            'urlMerchantResponse' => $data['urlMerchantResponse'] ?? '',
            'languageMessages'  => $data['languageMessages'] ?? $this->language,
            'timeStamp'         => $data['timeStamp'] ?? date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'entityCode'        => $data['entityCode'] ?? '',
            'referenceNumber'   => $data['referenceNumber'] ?? '',
        ];

        // Adiciona purchaseRequest para compras
        if ($fields['transactionCode'] === self::TRANSACTION_TYPE_PURCHASE && !empty($data['billing'])) {
            $billing = $data['billing'];
            $required = ['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'];
            $missing = array_diff($required, array_keys($billing));
            if (!empty($missing)) {
                throw new Exception("preparePayment Error: Missing fields [" . implode(', ', $missing) . "] for PurchaseRequest");
            }
            $fields['purchaseRequest'] = $this->buildPurchaseRequest($billing);
        }

        // Gera fingerprint
        $type = ($fields['transactionCode'] === self::TRANSACTION_TYPE_REFUND) ? 'refund' : 'payment';
        $fields['fingerprint'] = $this->generateRequestFingerprint($fields, $type);

        // Monta postUrl com query string
        $postUrl = $this->endpoint . '?' . http_build_query([
            'FingerPrint'       => $fields['fingerprint'],
            'TimeStamp'         => $fields['timeStamp'],
            'FingerPrintVersion' => $fields['fingerprintversion'],
        ]);

        return ['postUrl' => $postUrl, 'fields' => $fields];
    }

    /**
     * Monta o campo `purchaseRequest` com os dados de faturamento codificados.
     *
     * @param array $billing Dados do cliente.
     * @return string Base64 JSON codificado.
     * @throws Exception Caso a codificação JSON falhe.
     * @todo adicionar tratamento de mais parametros adicionais.
     */
    private function buildPurchaseRequest(array $billing = []): string
    {
        $payload = array_merge([
            'billAddrCountry' => $billing['billAddrCountry'],
            'billAddrCity' => $billing['billAddrCity'],
            'billAddrLine1' => $billing['billAddrLine1'],
            'billAddrPostCode' => $billing['billAddrPostCode'],
            'email' => $billing['email']
        ], $billing);

        if (($payload['addrMatch'] ?? 'N') === 'Y') {
            $payload['shipAddrCountry'] = $payload['billAddrCountry'];
            $payload['shipAddrCity'] = $payload['billAddrCity'];
            $payload['shipAddrLine1'] = $payload['billAddrLine1'];
            $payload['shipAddrPostCode'] = $payload['billAddrPostCode'];
        }

        $json = json_encode(array_filter($payload, fn($v) => !empty($v) || is_numeric($v)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new \Exception("Erro ao codificar PurchaseRequest");
        return base64_encode($json);
    }

     /**
     * Gera o fingerprint da requisição.
     *
     * @param array  $data Dados a serem incluídos na hash.
     * @param string $type Tipo da transação ('payment' ou 'refund').
     * @return string Fingerprint codificado em Base64.
     */
    private function generateRequestFingerprint(array $data, string $type = 'payment'): string
    {
        $encoded = base64_encode(hash('sha512', $this->posAuthCode, true));
        $amount = (float)($data['amount'] ?? 0);
        $amountLong = (int) bcmul($amount, '1000', 0);

        if ($type === 'payment') {
            $toHash = $encoded .
                ($data['timeStamp'] ?? '') .
                $amountLong .
                ($data['merchantRef'] ?? '') .
                ($data['merchantSession'] ?? '') .
                ($data['posID'] ?? '') .
                ($data['currency'] ?? '') .
                ($data['transactionCode'] ?? '') .
                ($data['entityCode'] ?? '') .
                ($data['referenceNumber'] ?? '');
        } else { // refund
            $toHash = $encoded .
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

    /**
     * Gera o fingerprint de resposta para validação.
     *
     * @param array  $data Dados retornados pelo gateway.
     * @param string $type Tipo da transação.
     * @return string Fingerprint codificado em Base64.
     */
    private function generateResponseFingerprint(array $data, string $type = 'payment'): string
    {
        $encoded = base64_encode(hash('sha512', $this->posAuthCode, true));
        $amount = (float)($data['merchantRespPurchaseAmount'] ?? 0);
        $amountLong = (int) bcmul($amount, '1000', 0);

        if ($type === 'payment') {
            $toHash =
                $encoded .
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
        } else { // refund
            $toHash =
                $encoded .
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
    // Renderiza formulário HTML
    // -------------------------

    /**
     * Gera o formulário HTML para submissão automática da transação ao gateway Vinti4.
     *
     * Após preparar uma transação (`purchase`, `service`, `recharge` ou `refund`), 
     * este método cria um formulário HTML com todos os campos ocultos exigidos pelo Vinti4 
     * e redireciona automaticamente o cliente para a página de pagamento segura.
     *
     *
     * @param string $responseUrl     URL de callback do comerciante (onde o Vinti4 enviará a resposta POST).
     * @param string $redirectMessage Mensagem exibida ao usuário enquanto é redirecionado.
     *
     * @return string HTML completo do formulário auto-submetido.
     *
     * @throws Exception Se nenhuma transação tiver sido preparada.
     */
    public function createPaymentForm(string $responseUrl): string
    {
        if ($this->request === null) {
            throw new Exception("No transaction prepared.");
        }

        // Chama preparePayment para montar fields + postUrl
        $prepared = $this->preparePayment(array_merge($this->params, $this->request, [
            'urlMerchantResponse' => $responseUrl
        ]));

        $inputs = '';
        foreach ($prepared['fields'] as $k => $v) {
            $inputs .= "<input type='hidden' name='" . htmlspecialchars($k) . "' value='" . htmlspecialchars($v) . "'>";
        }

        return "
    <html>
        <body onload='document.forms[0].submit()' style='text-align:center;padding:30px;font-family:Arial,sans-serif;'>
            <form action='" . htmlspecialchars($prepared['postUrl']) . "' method='post'>
                $inputs
            </form>
        </body>
    </html>";
    }


    // -------------------------
    // Processa resposta do servidor
    // -------------------------

     /**
     * Processa e valida a resposta retornada pelo gateway Vinti4 após o pagamento.
     *
     * Este método deve ser chamado na **página de callback (URL de resposta)** definida em
     * {@see createPaymentForm()}.  
     * Ele recebe o array `$_POST` enviado pelo Vinti4, valida a assinatura digital (*fingerprint*)
     * e retorna uma estrutura de resultado interpretável pela aplicação.
     *
     * ---
     * Exemplo:
     * ```php
     * // callback.php
     * $client = new Vinti4PayClient($posID, $posAuthCode);
     * $result = $client->processResponse($_POST);
     *
     * if ($result['success']) {
     *     // Pagamento confirmado e fingerprint válido
     *     echo "✅ Pagamento aprovado: " . $result['data']['merchantRespMerchantRef'];
     * } else {
     *     echo "❌ Falha no pagamento: " . $result['message'];
     * }
     * ```
     * ---
     *
     * ### Estrutura de retorno
     *
     * O método retorna um array associativo com a seguinte estrutura:
     *
     * | Chave       | Tipo     | Descrição |
     * |--------------|----------|------------|
     * | **status**   | string   | Código interno do resultado. Valores possíveis:<br>`SUCCESS`, `CANCELLED`, `INVALID_FINGERPRINT`, `ERROR`. |
     * | **message**  | string   | Mensagem legível sobre o estado da transação. |
     * | **success**  | bool     | `true` se a transação foi concluída com sucesso e fingerprint válido. |
     * | **data**     | array    | Dados completos recebidos do POST do Vinti4. |
     * | **dcc**      | array    | (Opcional) Dados de conversão de moeda (DCC) se aplicável. |
     * | **debug**    | array    | Informações adicionais em caso de erro de fingerprint. |
     * | **detail**   | string   | Mensagem detalhada de erro retornada pelo gateway, se disponível. |
     *
     * ---
     * ### Campos principais esperados em `$postData`
     * 
     * - **messageType** — Tipo de mensagem retornada pelo Vinti4 (ex: `"8"`, `"10"`, `"P"`, `"M"`).  
     *   Indica o estado da operação.
     * - **resultFingerPrint** — Assinatura digital do retorno.
     * - **merchantRespPurchaseAmount** — Valor da transação confirmada.
     * - **merchantRespMerchantRef** — Referência do comerciante.
     * - **merchantRespMerchantSession** — Sessão da transação.
     * - **merchantRespErrorDescription** — Descrição de erro (se houver falha).
     * - **UserCancelled** — `"true"` se o utilizador cancelou o pagamento.
     *
     * ---
     * ### Validação do Fingerprint
     *
     * Quando a transação retorna com um tipo de mensagem de sucesso
     * (`8`, `10`, `P` ou `M` — conforme {@see self::SUCCESS_MESSAGE_TYPES}),
     * o método recalcula o *fingerprint* localmente e o compara com o recebido.
     * - Se coincidem → a transação é considerada **válida e confirmada**.
     * - Se divergem → retorna `INVALID_FINGERPRINT` com os valores calculados e recebidos em `debug`.
     *
     * ---
     * ### Cancelamento pelo utilizador
     *
     * Caso o utilizador cancele o pagamento antes da confirmação,
     * o Vinti4 envia `UserCancelled=true`.  
     * O método retorna:
     * ```php
     * [
     *     'status' => 'CANCELLED',
     *     'message' => 'Pagamento cancelado pelo usuário.',
     *     'success' => false
     * ]
     * ```
     *
     * ---
     * @param array $postData Dados enviados via POST pelo Vinti4 (geralmente `$_POST`).
     *
     * @return array{
     *  status:string, message:string, success:bool,
     *  data:array,dcc?:array,debug?:array,detail?:string
     * } Estrutura contendo:
     *  - `status` (string)
     *  - `message` (string)
     *  - `success` (bool)
     *  - `data` (array)
     *  - `dcc` (array)
     *  - `debug` (array)
     *  - `detail` (string opcional)
     *
     * @throws Exception Nunca lança exceção diretamente; retorna status de erro padronizado.
     * 
     * @todo adiocionar um parametro ou chave que recebe o recido simples pre-renderizado.
     */
    public function processResponse(array $postData): array
    {
        $result = [
            'status' => 'ERROR',
            'message' => 'Erro.',
            'success' => false,
            'data' => $postData,
            'dcc' => [],
            'debug' => []
        ];

        // -------------------------
        // Usuário cancelou a transação
        // -------------------------
        if (($postData['UserCancelled'] ?? '') === 'true') {
            $result['status'] = 'CANCELLED';
            $result['message'] = $this->language === 'pt' ? 'Pagamento cancelado pelo usuário.' : 'Payment cancelled by user.';
            return $result;
        }

        // -------------------------
        // Detecta tipo de transação
        // -------------------------
        $type = ($postData['transactionCode'] ?? '') === self::TRANSACTION_TYPE_REFUND ||
            ($postData['reversal'] ?? '') === 'R' ? 'refund' : 'payment';

        // -------------------------
        // Parse DCC se disponível
        // -------------------------
        if (!empty($postData['dcc']) && strtoupper($postData['dcc']) === 'Y') {
            $result['dcc'] = [
                'amount' => $postData['dccAmount'] ?? '',
                'currency' => $postData['dccCurrency'] ?? '',
                'markup' => $postData['dccMarkup'] ?? '',
                'rate' => $postData['dccRate'] ?? ''
            ];
        }

        // -------------------------
        // Sucesso por messageType
        // -------------------------
        $messageType = $postData['messageType'] ?? null;

        if ($messageType !== null && in_array($messageType, self::SUCCESS_MESSAGE_TYPES)) {
            $calcFingerprint = $this->generateResponseFingerprint($postData, $type);
            $receivedFingerprint = $postData['resultFingerPrint'] ?? '';

            if ($receivedFingerprint === $calcFingerprint) {
                $result['success'] = true;
                $result['status'] = 'SUCCESS';
                $result['message'] = $this->language === 'pt'
                    ? 'Transação válida e efetuada.'
                    : 'Transaction valid and fingerprint verified.';
            } else {
                $result['status'] = 'INVALID_FINGERPRINT';
                $result['message'] = $this->language === 'pt'
                    ? 'Transação suspeita de fraude. (fingerprint inválido)'
                    : 'Transaction processed but fingerprint invalid.';
                $result['debug'] = [
                    'received' => $receivedFingerprint,
                    'calculated' => $calcFingerprint
                ];
            }
        } else {
            // Mensagem de erro detalhada
            $result['message'] = $postData['merchantRespErrorDescription'] ??
                ($this->language === 'pt' ? 'Erro desconhecido' : 'Unknown error');
            $result['detail'] = $postData['merchantRespErrorDetail'] ?? '';
        }

        return $result;
    }
}
