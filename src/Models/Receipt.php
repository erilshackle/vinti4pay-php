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
    public function __construct(array $data, ?string $templatesPath = null)
    {
        $this->data = $data;
        $this->templatesPath = $templatesPath ?? __DIR__ . '/../templates';
    }

    /**
     * Determina o tipo de registro com base no Record Type ou Transaction Code.
     */
    public function getRecordType(): int
    {
        return (int) ($this->data['recordType'] ?? $this->data['transactionCode'] ?? 0);
    }

    /**
     * Gera o recibo HTML baseado no tipo de transação e template.
     *
     * @param string|null $template Nome do template (sem extensão)
     * @param array $params Parâmetros extras para placeholders
     * @return string HTML do recibo
     */
    public function generateReceipt(?string $template = null, array $params = []): string
    {
        $recordType = $this->getRecordType();
        $type = match ($recordType) {
            1 => 'transaction',
            2 => 'refund',
            3 => 'service',
            default => 'transaction',
        };

        $template = $template ?? "{$type}_receipt";
        $templateFile = rtrim($this->templatesPath, '/') . "/{$template}.html";

        if (file_exists($templateFile)) {
            $html = file_get_contents($templateFile);

            // Merge dos dados + params para placeholders
            $placeholders = array_merge($this->prepareDataForTemplate(), $params);
            foreach ($placeholders as $key => $value) {
                $html = str_replace('{{' . strtoupper($key) . '}}', htmlspecialchars((string) $value), $html);
            }

            return $this->html = $html;
        }

        // Se não existir template, gera recibo simples
        return $this->html = $this->generateSimpleReceipt($type);
    }

    /**
     * Prepara dados da transação para uso no template.
     */
    protected function prepareDataForTemplate(): array
    {
        $d = $this->data;

        $formattedDate = '';
        if (!empty($d['transactionTimestamp']) && strlen($d['transactionTimestamp']) >= 14) {
            $ts = $d['transactionTimestamp'];
            $formattedDate = substr($ts,0,4) . '-' . substr($ts,4,2) . '-' . substr($ts,6,2)
                . ' ' . substr($ts,8,2) . ':' . substr($ts,10,2) . ':' . substr($ts,12,2);
        }

        $amount = isset($d['amount']) ? number_format((float)$d['amount'], 2, ',', '.') : '0,00';
        $commission = isset($d['commission']) ? number_format((float)$d['commission'], 2, ',', '.') : '0,00';
        $total = number_format(((float)($d['amount'] ?? 0) + (float)($d['commission'] ?? 0)), 2, ',', '.');

        return [
            'RECORD_TYPE' => $d['recordType'] ?? '',
            'MERCHANT_REFERENCE' => $d['merchantReference'] ?? '',
            'ORIGINAL_MERCHANT_REFERENCE' => $d['originalMerchantReference'] ?? '',
            'MESSAGE_ID' => $d['messageId'] ?? '',
            'RESPONSE_CODE' => $d['rtResponseCode'] ?? '',
            'TRANSACTION_TIMESTAMP' => $formattedDate,
            'CLEARING_PERIOD' => $d['clearingPeriod'] ?? '',
            'ORIGINAL_CLEARING_PERIOD' => $d['originalClearingPeriod'] ?? '',
            'TRANSACTION_ID' => $d['transactionId'] ?? '',
            'ORIGINAL_TRANSACTION_ID' => $d['originalTransactionId'] ?? '',
            'PAN' => $d['pan'] ?? '',
            'CARD_TYPE' => $d['cardType'] ?? '',
            'AMOUNT' => $amount,
            'COMMISSION' => $commission,
            'TOTAL' => $total,
            'VERIFICATION_CODE' => $d['verificationCode'] ?? '',
            'ENTITY_NAME' => $d['entityName'] ?? '',
            'PRODUCT_TYPE' => $d['productType'] ?? '',
        ];
    }

    /**
     * Gera um recibo simples, sem template.
     */
    protected function generateSimpleReceipt(string $type): string
    {
        $html = "<div style='font-family:Arial,sans-serif;line-height:1.4;max-width:600px;margin:auto;padding:20px;border:1px solid #ccc;border-radius:8px;'>";
        $html .= "<h3>Recibo de " . ucfirst($type) . "</h3>";

        $fields = match ($type) {
            'transaction' => ['MERCHANT_REFERENCE','MESSAGE_ID','RESPONSE_CODE','TRANSACTION_TIMESTAMP','AMOUNT','COMMISSION','PAN','CARD_TYPE'],
            'refund' => ['MERCHANT_REFERENCE','ORIGINAL_MERCHANT_REFERENCE','MESSAGE_ID','RESPONSE_CODE','TRANSACTION_TIMESTAMP','AMOUNT','COMMISSION','PAN','ORIGINAL_TRANSACTION_ID'],
            'service' => ['MERCHANT_REFERENCE','MESSAGE_ID','RESPONSE_CODE','TRANSACTION_TIMESTAMP','AMOUNT','COMMISSION','PAN','ENTITY_NAME','PRODUCT_TYPE'],
            default => array_keys($this->data),
        };

        foreach ($fields as $field) {
            $value = $this->data[$field] ?? '';
            $html .= "<p><strong>{$field}:</strong> " . htmlspecialchars((string)$value) . "</p>";
        }

        $html .= "<hr>";
        $html .= "<p style='font-size:0.8em;color:#666;'>Recibo gerado automaticamente.</p>";
        $html .= "</div>";

        return $html;
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
