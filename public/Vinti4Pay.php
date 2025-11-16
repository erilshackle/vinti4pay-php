<?php

/**
 * Classe Vinti4Net
 *
 * SDK PHP para integração com o sistema de pagamentos **Vinti4Net** (SISP Cabo Verde, serviço MOP021).
 * Esta classe permite criar, enviar e validar transações de pagamento online, incluindo suporte a 3D Secure (3DS).
 *
 * Funcionalidades principais:
 * - Criação de formulários automáticos de pagamento
 * - Geração de fingerprints (assinaturas) de segurança
 * - Processamento de callbacks (respostas) do gateway Vinti4Net
 * - Validação de respostas com verificação de integridade
 *
 * @package App\Services
 * @version 1.0.0
 * @author Eril TS Carvalho
 * @license MIT
 * @link https://www.vinti4.cv/documentation.aspx?id=585
 */
class Vinti4Pay
{
    /** @var string URL padrão do endpoint de pagamento */
    const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment";

    /** @var string Código de transação para compras */
    const TRANSACTION_TYPE_COMPRA = '1';

    /** @var string Código de transação para pagamento de serviços */
    const TRANSACTION_TYPE_PAGAMENTO_SERVICO = '2';

    /** @var string Código de transação para recargas */
    const TRANSACTION_TYPE_RECARGA = '3';

    /** @var string Código da moeda Cabo-Verdiana (CVE) */
    const CURRENCY_CVE = '132';

    /** @var array Tipos de mensagens que indicam sucesso na transação */
    const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    /** @var string Identificador do POS */
    private string $posID;

    /** @var string Código de autorização do POS */
    private string $posAutCode;

    /** @var string URL base do endpoint */
    private string $baseUrl;

    /**
     * Construtor da classe Vinti4Net.
     *
     * @param string $posID Identificador do terminal POS.
     * @param string $posAutCode Código de autenticação do POS.
     * @param string|null $endpoint URL opcional de endpoint (caso queira usar ambiente de testes).
     */
    public function __construct(string $posID, string $posAutCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAutCode = $posAutCode;
        $this->baseUrl = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    // ----------------------------------------------------------
    // INTERFACE PÚBLICA
    // ----------------------------------------------------------

    /**
     * Cria um formulário completo de pagamento para **serviços**.
     *
     * Exemplo: pagamento de faturas, propinas, taxas, etc.
     *
     * @param float|string $amount Valor do pagamento.
     * @param string $responseUrl URL de retorno do Vinti4Net.
     * @param string $entityCode Código da entidade (numérico).
     * @param string $referenceNumber Número de referência (numérico).
     * @param array $extras Dados adicionais opcionais.
     *
     * @throws \InvalidArgumentException Caso o valor seja inválido ou campos não numéricos.
     * @return string HTML do formulário para submissão automática.
     */
    public function createPurchaseForm(
        float|string $amount,
        string $responseUrl,
        array $billing,
        array $extras = []
    ): string {
        $required = ['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'];
        $missing = array_diff($required, array_keys($billing));

        if (!empty($missing)) {
            throw new \InvalidArgumentException("Campos obrigatórios de billing ausentes: " . implode(', ', $missing));
        }

        // Valida e-mail
        if (!filter_var($billing['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("O campo 'email' contém um formato inválido.");
        }

        // Monta os dados finais
        $dados = array_merge($extras, [
            'billing' => $billing,
            'transactionCode' => self::TRANSACTION_TYPE_COMPRA
        ]);

        return $this->createGenericForm($amount, $responseUrl, $dados, self::TRANSACTION_TYPE_COMPRA);
    }

    /**
     * Cria um formulário completo de pagamento para **serviços**.
     *
     * Exemplo: pagamento de faturas, propinas, taxas, etc.
     *
     * @param float|string $amount Valor do pagamento.
     * @param string $responseUrl URL de retorno do Vinti4Net.
     * @param string $entityCode Código da entidade (numérico).
     * @param string $referenceNumber Número de referência (numérico).
     * @param array $extras Dados adicionais opcionais.
     *
     * @throws \InvalidArgumentException Caso o valor seja inválido ou campos não numéricos.
     * @return string HTML do formulário para submissão automática.
     */
    public function createServicePaymentForm(
        float|string $amount,
        string $responseUrl,
        string $entityCode,
        string $referenceNumber,
        array $extras = []
    ): string {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("O valor da transação deve ser maior que zero.");
        }

        if (!ctype_digit($entityCode) || !ctype_digit($referenceNumber)) {
            throw new \InvalidArgumentException("Os campos 'entityCode' e 'referenceNumber' devem conter apenas números.");
        }

        $dados = array_merge($extras, [
            'entityCode' => $entityCode,
            'referenceNumber' => $referenceNumber,
            'transactionCode' => self::TRANSACTION_TYPE_PAGAMENTO_SERVICO
        ]);

        return $this->createGenericForm($amount, $responseUrl, $dados, self::TRANSACTION_TYPE_PAGAMENTO_SERVICO);
    }

    /**
     * Cria um formulário completo de **recarga (telecomunicações, etc.)**.
     *
     * @param float|string $amount Valor da recarga.
     * @param string $responseUrl URL de retorno após o pagamento.
     * @param string $entityCode Código da entidade.
     * @param string $referenceNumber Número de referência.
     * @param array $extras Dados adicionais opcionais.
     *
     * @throws \InvalidArgumentException Caso o valor ou códigos sejam inválidos.
     * @return string HTML do formulário pronto para submissão.
     */
    public function createRechargeForm(
        float|string $amount,
        string $responseUrl,
        string $entityCode,
        string $referenceNumber,
        array $extras = []
    ): string {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("O valor da transação deve ser maior que zero.");
        }

        if (!ctype_digit($entityCode) || !ctype_digit($referenceNumber)) {
            throw new \InvalidArgumentException("Os campos 'entityCode' e 'referenceNumber' devem conter apenas números.");
        }

        $dados = array_merge($extras, [
            'entityCode' => $entityCode,
            'referenceNumber' => $referenceNumber,
            'transactionCode' => self::TRANSACTION_TYPE_RECARGA
        ]);

        return $this->createGenericForm($amount, $responseUrl, $dados, self::TRANSACTION_TYPE_RECARGA);
    }


    /**
     * Método privado central que prepara e gera o HTML do formulário de pagamento.
     */
    private function createGenericForm(float|string $amount, string $responseUrl, array $data, string $transactionCode): string
    {
        $data['transactionCode'] = $transactionCode;
        $paymentData = $this->preparePayment($responseUrl, $data);
        return $this->buildHtmlForm($paymentData);
    }

    // ----------------------------------------------------------
    // Implementação dos métodos internos
    // ----------------------------------------------------------

    private function GerarFingerPrintEnvio(array $data): string
    {
        $entityCode = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
        $referenceNumber = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';
        $amountInMille = (int)((float)$data['amount'] * 1000);

        $toHash = base64_encode(hash('sha512', $this->posAutCode, true)) .
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

    private function GerarFingerPrintRespostaBemSucedida(array $data): string
    {
        $reference = !empty($data['merchantRespReferenceNumber']) ? (int)$data['merchantRespReferenceNumber'] : '';
        $entity = !empty($data['merchantRespEntityCode']) ? (int)$data['merchantRespEntityCode'] : '';
        $reloadCode = $data['merchantRespReloadCode'] ?? '';
        $additionalErrorMessage = trim($data['merchantRespAdditionalErrorMessage'] ?? '');

        $toHash = base64_encode(hash('sha512', $this->posAutCode, true)) .
            $data["messageType"] . $data["merchantRespCP"] . $data["merchantRespTid"] .
            $data["merchantRespMerchantRef"] . $data["merchantRespMerchantSession"] .
            ((int)((float)$data["merchantRespPurchaseAmount"] * 1000)) .
            $data["merchantRespMessageID"] . $data["merchantRespPan"] . $data["merchantResp"] .
            $data["merchantRespTimeStamp"] . $reference . $entity .
            $data["merchantRespClientReceipt"] . $additionalErrorMessage . $reloadCode;

        return base64_encode(hash('sha512', $toHash, true));
    }

    private function buildPurchaseRequest(string $billAddrCountry, string $billAddrCity, string $billAddrLine1, string $billAddrPostCode, string $email, array $additionalData = []): string
    {
        $payload = [
            'billAddrCountry' => $billAddrCountry,
            'billAddrCity' => $billAddrCity,
            'billAddrLine1' => $billAddrLine1,
            'billAddrPostCode' => $billAddrPostCode,
            'email' => $email,
        ];
        $payload = array_merge($payload, $additionalData);

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
     * Prepara os dados de pagamento (valores, merchant, fingerprint etc.).
     *
     * @param string $responseUrl URL de resposta/callback.
     * @param array $requestParams Campos adicionais (entityCode, referenceNumber, billing...).
     *
     * @return array Estrutura com dados e URL de envio (postUrl e fields).
     */
    public function preparePayment(string $responseUrl, array $requestParams = []): array
    {
        if (empty($requestParams['amount'])) {
            throw new Exception("Vinti4Pay::preparePayment Error: 'amount' param is required.");
        } elseif (is_numeric($requestParams['amount'])) {
            throw new Exception("Vinti4Pay::preparePayment Error: 'amount' param is required.");
        }

        $billingData = $requestParams['billing'] ?? [];
        $dateTime = date('Y-m-d H:i:s');

        $fields = [
            'transactionCode' => $requestParams['transactionCode'] ?? self::TRANSACTION_TYPE_COMPRA,
            'posID' => $this->posID,
            'merchantRef' => $requestParams['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession' => $requestParams['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => (int)(float)$requestParams['amount'],
            'currency' => $requestParams['currency'] ?? self::CURRENCY_CVE,
            'is3DSec' => '1',
            'urlMerchantResponse' => $responseUrl,
            'languageMessages' => $requestParams['languageMessages'] ?? 'pt',
            'timeStamp' => $dateTime,
            'fingerprintversion' => '1',
            'entityCode' => $requestParams['entityCode'] ?? '',
            'referenceNumber' => $requestParams['referenceNumber'] ?? '',
        ];

        // Se for COMPRA, processa o purchaseRequest
        if ($fields['transactionCode'] === self::TRANSACTION_TYPE_COMPRA && !empty($billingData)) {
            $fields['purchaseRequest'] = $this->buildPurchaseRequest(
                $billingData['billAddrCountry'],
                $billingData['billAddrCity'],
                $billingData['billAddrLine1'],
                $billingData['billAddrPostCode'],
                $billingData['email'],
                array_diff_key($billingData, array_flip(['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email']))
            );
        }

        $fields['fingerprint'] = $this->GerarFingerPrintEnvio($fields);
        $postUrl = $this->baseUrl .
            "?FingerPrint=" . urlencode($fields["fingerprint"]) .
            "&TimeStamp=" . urlencode($fields["timeStamp"]) .
            "&FingerPrintVersion=" . urlencode($fields["fingerprintversion"]);

        return ['postUrl' => $postUrl, '' => $fields];
    }

    private function buildHtmlForm(array $paymentData): string
    {
        $inputs = '';
        foreach ($paymentData['fields'] as $key => $value) {
            $inputs .= "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>";
        }

        return "
        <html>
            <head><title>Pagamento Vinti4Net</title></head>
            <body onload='document.forms[0].submit()'>
                <h5>Processando o pagamento... Por favor, aguarde.</h5>
                <form action='{$paymentData['postUrl']}' method='post'>{$inputs}</form>
            </body>
        </html>";
    }

    // ----------------------------------------------------------
    // Validação de resposta
    // ----------------------------------------------------------

    /**
     * Processa e valida a resposta (callback) do Vinti4Net.
     *
     * Este método deve ser chamado na rota de retorno configurada no painel Vinti4Net.
     * Ele verifica se o pagamento foi bem-sucedido, se o utilizador cancelou, e valida
     * o fingerprint para garantir autenticidade.
     *
     * @param array $postData Dados recebidos via POST.
     * @return array{
     *   status: string,          // SUCCESS, ERROR, CANCELLED, INVALID_FINGERPRINT
     *   message: string,         // Mensagem descritiva
     *   success: bool,           // true se a transação foi processada com sucesso
     *   data: array,             // Dados originais do POST
     *   dcc?: array,        // Dados de DCC (conversão dinâmica de moeda)
     *   debug?: array,           // Info extra de fingerprint (opcional)
     *   detail?: string          // Detalhes de erro (quando aplicável)
     * }
     */
    public function processResponse(array $postData): array
    {
        // Estrutura base
        $result = [
            'status' => 'ERROR',
            'message' => 'Erro desconhecido na transação.',
            'success' => false,
            'data' => $postData,
            'dcc' => [],
        ];

        if (($postData["UserCancelled"] ?? '') === "true") {
            $result['status'] = 'CANCELLED';
            $result['message'] = 'Utilizador cancelou a requisição de pagamento.';
            return $result;
        }

        if (!empty($postData['merchantRespDCCData'])) {
            $decoded = json_decode($postData['merchantRespDCCData'], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $result['dcc'] = $decoded;
            } else {
                error_log("DCC Data inválido recebido: " . $postData['merchantRespDCCData']);
                $result['dcc'] = null;
            }
        }

        if (isset($postData["messageType"]) && in_array($postData["messageType"], self::SUCCESS_MESSAGE_TYPES)) {

            $calcFingerprint = $this->GerarFingerPrintRespostaBemSucedida($postData);
            $receivedFingerprint = $postData["resultFingerPrint"] ?? '';
            $result['success'] = true; // Transação pode ter sido aprovada, mas requer validacao de fingerprint

            if ($receivedFingerprint === $calcFingerprint) {
                $result['status'] = 'SUCCESS';
                $result['message'] = 'Transação válida e fingerprint verificado.';
            } else {
                $result['status'] = 'INVALID_FINGERPRINT';
                $result['message'] = 'Transação processada, mas fingerprint inválido.';
                $result['debug'] = [
                    'recebido' => $receivedFingerprint,
                    'calculado' => $calcFingerprint
                ];
            }

            return $result;
        }

        if (!empty($postData["merchantRespErrorDescription"])) {
            $result['message'] = $postData["merchantRespErrorDescription"];
        }

        if (!empty($postData["merchantRespErrorDetail"])) {
            $result['detail'] = $postData["merchantRespErrorDetail"];
        }

        $GLOBALS['vinti4paylastresponseresult'] = $result;

        // Retorno final unificado
        return  $result;
    }
}

/**
 * Defina callback para dado situacao de responsta
 * 
 * @param callable(array $result) $onSuccess
 * @param callable(array $result) $onFailure
 * @return mixed|void
 */
function onVinti4TransactionResult(callable $success, ?callable $error = null)
{
    $response = $GLOBALS['vinti4paylastresponseresult'] ?? null;
    if (!$response) {
        throw new Exception("onVinti4TransactionResult must be called after processingResponse from Vinti4Pay");
    }

    if ($response['success'] && $response['status'] == 'SUCCESS') {
        return $success($response);
    } elseif ($error !== null) {
        return $error($response);
    }
    return $response['status'] == 'SUCCESS';
}
