<?php

namespace Erilshk\Vinti4Pay\Traits;

trait ReceiptGeneretorTrait
{


    /**
     * Prepara dados comuns para qualquer tipo de recibo
     *
     * @return array Dados formatados e escapados prontos para render
     */
    private function prepareReceiptData($data = []): array
    {
        $d = $this->data;
        $dcc = $this->dcc ?? [];
        $escape = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE);

        $recordType = 1; // só Transaction/Serviço/Recarga no momento
        $typeLabel = 'Transaction';

        // Campos reais do retorno
        $merchantRef = $escape($d['merchantRespMerchantRef'] ?? '-');
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
        $statusText = $this->success ? 'Aprovada' : ($this->status == 'CANCELLED' ? 'Cancelado' : 'Falhou');
        $entity = $data['entity'] ?? '';
        $footer = $data['footer'] ?? '';


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
            'dcc',
            'entity',
            'footer'
        );
    }



    public function generateReceipt($copyright = ''): string
    {
        $data = [];
         if (!empty($copyright)) {
            if (str_word_count($copyright) > 2) {
                $data['footer'] = $copyright;
            } else {
                $data['entity'] = $copyright;
            }
        }
        $d = $this->prepareReceiptData($data);
        // render técnico (todos os campos)
        return $this->renderReceiptHtml($d, true);
    }

    public function generateClientReceipt($copyright = ''): string
    {
        $data = [];
        if (!empty($copyright)) {
            if (str_word_count($copyright) > 2) {
                $data['footer'] = $copyright;
            } else {
                $data['entity'] = $copyright;
            }
        }
        $d = $this->prepareReceiptData($data);
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

        $line = !empty($entity) ? ("© " . date('Y') . " {$entity}") : 'Receipt enerated automatically By Vinti4Pay';
        $line = !empty($footer) ? $footer : $line;

        $html .= "<p style=\"text-align:center;margin-top:25px;color:#777;font-size:0.8em;\">$line</p>";
        $html .= "</div>";

        return $html;
    }
}
