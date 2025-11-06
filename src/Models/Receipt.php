<?php

namespace Erilshk\Vinti4Pay\Models;

/**
 * Class Vinti4Receipt
 *
 * Responsável por gerar recibos HTML a partir dos dados de resposta da Vinti4Net.
 * Suporta múltiplos tipos de transação: Transaction, Refund e Service.
 */
class Receipt
{
    protected array $data;
    protected string $templatesPath;
    protected string $html = '';

    /**
     * @param array $data Dados retornados pela API.
     * @param string|null $templatesPath Caminho para a pasta de templates.
     */
    public function __construct(array $postedData, ?string $templatesPath = null)
    {
        $this->data = $postedData;
        $this->templatesPath = $templatesPath ?? __DIR__ . '/../templates';
    }

    /**
     * Determina o tipo de registro com base no Record Type ou Transaction Code.
     */
    public function getMessageType(): string
    {
        return (string) ($this->data['messageType'] ??  null);
    }

    /**
     * Gera o recibo HTML baseado no tipo de transação e template.
     *
     * @param string|null $template Nome do template (sem extensão)
     * @param array $params Parâmetros extras para placeholders
     *  - entity    - Nome da Entidade
     *  - footer    - Menssagemde rodapé
     * @return string HTML do recibo
     */
    public function generateReceipt(?string $template = null, array $params = []): string
    {
        $messageType = $this->getMessageType();
        $type = match ($messageType) {
            '8' => 'transaction',
            '10' => 'refund',
            'P','M' => 'service',
            default => null,
        };

        $template = $template ?? "{$type}_receipt";
        $templateFile = rtrim($this->templatesPath, '/') . "/{$template}.html";

        // Merge dos dados + params para placeholders
        $placeholders = array_merge($this->prepareDataForTemplate(), $params);

        if (file_exists($templateFile)) {
            $html = file_get_contents($templateFile);

            foreach ($placeholders as $key => $value) {
                $html = str_replace('{{' . strtoupper($key) . '}}', htmlspecialchars((string) $value), $html);
            }

            return $this->html = $html;
        }

        // Se não existir template, gera recibo simples
        return $this->html = $this->generateSimpleReceipt($type, $placeholders);
    }

    /**
     * Prepara dados da transação para uso no template.
     */
    protected function prepareDataForTemplate(): array
    {
        $d = $this->data;

        // Formata timestamp da transação
        $formattedDate = '';
        if (!empty($d['merchantRespTimeStamp']) && strlen($d['merchantRespTimeStamp']) >= 14) {
            $ts = $d['merchantRespTimeStamp'];
            $formattedDate = substr($ts, 0, 4) . '-' . substr($ts, 4, 2) . '-' . substr($ts, 6, 2)
                . ' ' . substr($ts, 8, 2) . ':' . substr($ts, 10, 2) . ':' . substr($ts, 12, 2);
        }

        // Montantes
        $amount = isset($d['merchantRespPurchaseAmount']) ? number_format((float)$d['merchantRespPurchaseAmount'], 2, ',', '.') : '0,00';

        return [
            'MERCHANT_REFERENCE' => $d['merchantRespMerchantRef'] ?? '',
            'MERCHANT_SESSION' => $d['merchantRespMerchantSession'] ?? '',
            'MESSAGE_ID' => $d['merchantRespMessageID'] ?? '',
            'RESPONSE_CODE' => $d['merchantResp'] ?? '',
            'TRANSACTION_TIMESTAMP' => $formattedDate,
            'CLEARING_PERIOD' => $d['merchantRespCP'] ?? '',
            'TRANSACTION_ID' => $d['merchantRespTid'] ?? '',
            'PAN' => $d['merchantRespPan'] ?? '',
            'CARD_TYPE' => $d['cardType'] ?? '',
            'AMOUNT' => $amount,
            'TOTAL' => $amount . ' CVE',
            'ERROR_CODE' => $d['merchantRespErrorCode'] ?? '',
            'ERROR_DESCRIPTION' => $d['merchantRespErrorDescription'] ?? '',
            'ERROR_DETAIL' => $d['merchantRespErrorDetail'] ?? '',
            'ADDITIONAL_ERROR_MESSAGE' => $d['merchantRespAdditionalErrorMessage'] ?? '',
            'ENTITY_CODE' => $d['merchantRespEntityCode'] ?? '',
            'REFERENCE_NUMBER' => $d['merchantRespReferenceNumber '] ?? '',

            // DCC
            'DCC' => $d['dcc'] ?? 'N',
            'DCC_AMOUNT' => $d['dcc']['amount'] ??  $d['dccAmount'] ?? '',
            'DCC_CURRENCY' => $d['dcc']['currency'] ??  $d['dccCurrency'] ?? '',
            'DCC_MARKUP' => $d['dcc']['markup'] ?? $d['dccMarkup'] ?? '',
            'DCC_RATE' => $d['dcc']['rate'] ?? $d['dccRate'] ?? '',
        ];
    }


    protected function generateSimpleReceipt(string $type, array $data): string
    {
        // Mapeamento de placeholders para nomes legíveis em português
        $labels = [
            'MERCHANT_REFERENCE' => 'Referência do Comerciante',
            'MERCHANT_SESSION' => 'Sessão do Comerciante',
            'MESSAGE_ID' => 'ID da Mensagem',
            'RESPONSE_CODE' => 'Código de Resposta',
            'TRANSACTION_TIMESTAMP' => 'Data/Hora da Transação',
            'CLEARING_PERIOD' => 'Período Contabilístico',
            'TRANSACTION_ID' => 'ID da Transação',
            'PAN' => 'Número do Cartão',
            'CARD_TYPE' => 'Tipo de Cartão',
            'AMOUNT' => 'Valor',
            'COMMISSION' => 'Comissão',
            'TOTAL' => 'Total',
            'ERROR_CODE' => 'Código de Erro',
            'ERROR_DESCRIPTION' => 'Descrição do Erro',
            'ERROR_DETAIL' => 'Detalhe do Erro',
            'ADDITIONAL_ERROR_MESSAGE' => 'Mensagem de Erro Adicional',
            'ENITY' => 'Entidade',
            'ENTITY_CODE' => 'Código da Entidade',
            'REFERENCE_NUNBER' => 'Número de Referência',
            'PRODUCT' => 'Produto',
        ];

        // Definir a ordem dos campos no recibo, por tipo
        $fieldsByType = match ($type) {
            'transaction' => ['MERCHANT_REFERENCE', 'MESSAGE_ID', 'RESPONSE_CODE', 'TRANSACTION_TIMESTAMP', 'PAN', 'CARD_TYPE', 'PRODUCT',  'TOTAL'],
            'refund' => ['MERCHANT_REFERENCE', 'ORIGINAL_MERCHANT_REFERENCE', 'MESSAGE_ID', 'RESPONSE_CODE', 'TRANSACTION_TIMESTAMP', 'PAN', 'TRANSACTION_ID', 'TOTAL'],
            'service' => ['MERCHANT_REFERENCE', 'MESSAGE_ID', 'RESPONSE_CODE', 'TRANSACTION_TIMESTAMP', 'PAN', 'ENTITY_CODE', 'REFERENCE_NUMBER', 'TOTAL'],
            default => array_keys($data),
        };

        $tipo = match ($type) {
            'transaction' => 'compra',
            'refund' => 'estorno',
            'service' => 'pagamento',
            default => 'transação'
        };

        $html = "<div style=\"
        font-family: Arial, sans-serif;
        max-width: 600px;
        margin: 30px auto;
        padding: 25px;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        background-color: #f9f9f9;
    \">";

        $entity = $data['entity'] ?? '';

        $html .= "<h2 style='text-align:center; color:#2c3e50;'>Recibo de " . ucfirst($tipo) . "</h2>";
        $html .= "<p style='text-align:center; color:#7f8c8d; margin-top:-10px;font-variant: small-caps;'>{$entity}</p>";
        $html .= "<hr style='border:0; border-top:1px solid #d0d0d0; margin:20px 0;'>";

        $html .= "<table style='width:100%; border-collapse:collapse;'>";

        foreach ($fieldsByType as $index => $field) {
            if(empty($data[$field])) continue;
            $value = htmlspecialchars((string)($data[$field] ?? ''));
            $label = $labels[$field] ?? $field; // usa nome legível, se existir
            $bg = $index % 2 === 0 ? '' : 'background-color:#f0f0f0;';
            $fontWeight = ($field === 'TOTAL') ? 'font-weight:bold;color:#27ae60;' : 'font-weight:bold;';
            $html .= "<tr style='$bg'>
            <td style='padding:5px 0; $fontWeight'>{$label}:</td>
            <td style='padding:5px 0;'>$value</td>
        </tr>";
        }

        $html .= "</table>";
        $html .= "<hr style='border:0; border-top:1px solid #d0d0d0; margin:20px 0;'>";

        // DCC details (apenas se dcc = 'Y')
        if (($data['DCC'] ?? 'N') === 'Y') {
            $dccHtml = "<p style='font-size:0.75em; color:#95a5a6; line-height:1.4;'>";
            $dccHtml .= "Taxa de conversão / Currency Conversion Rate: " . ($data['DCC_RATE'] ?? 'N/A') . "<br>";
            $dccHtml .= "Taxa do serviço DCC / DCC Markup: " . ($data['DCC_MARKUP'] ?? 'N/A') . " " . ($data['DCC_CURRENCY'] ?? '') . "<br>";
            $dccHtml .= "Moeda da Transação / Transaction Currency: " . ($data['DCC_CURRENCY'] ?? 'N/A') . "<br>";
            $dccHtml .= "TOTAL na moeda estrangeira / TOTAL: " . ($data['DCC_AMOUNT'] ?? 'N/A') . "<br><br>";
            $dccHtml .= "I have been offered choice of currencies and agreed to pay in " . ($data['DCC_CURRENCY'] ?? 'N/A') . ".<br>";
            $dccHtml .= "Dynamic Currency Conversion (DCC) offered by rede Vinti4.<br>";
            $dccHtml .= "Exchange rate provided by Banco de Cabo Verde.";
            $dccHtml .= "</p>";
            $html .= $dccHtml;
            $html .= "<hr style='border:0; border-top:1px solid #d0d0d0; margin:20px 0;'>";
        }

        $footer = !empty($data['footer']) ? $data['footer'] : 'Este recibo é apenas para fins informativos.';
        $html .= "<p style='font-size:0.85em; color:#95a5a6; text-align:center; margin-top:10px;'>$footer</p>";

        $html .= "</div>";
        $html .= '<div style="text-align: center; opacity: .2; font-family: monospace;">Gerado automaticamente por Vinti4Pay</div>';

        return $this->html = $html;
    }


    /**
     * Salva o recibo gerado em arquivo.
     *
     * @param string $outputPath Caminho completo do arquivo HTML.
     * @param string|null $template Nome do template
     * @param array $params Parâmetros extras
     * @return string Caminho do arquivo salvo
     */
    public function saveReceipt(string $outputPath, ?string $template = null, array $params = []): string
    {
        $html = $this->generateReceipt($template, $params);
        file_put_contents($outputPath, $html);
        return $outputPath;
    }

    /**
     * Retorna o HTML do último recibo gerado.
     */
    public function getHtml(): string
    {
        return $this->html;
    }
}
