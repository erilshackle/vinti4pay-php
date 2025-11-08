<?php

namespace Erilshk\Vinti4Pay\Tests\Traits;

use Erilshk\Vinti4Pay\Traits\ReceiptGeneretorTrait;
use PHPUnit\Framework\TestCase;

class ReceiptGeneratorTraitTest extends TestCase
{
    use ReceiptGeneretorTrait;

    private $data = [];
    private $dcc = [];
    private $success = true;

    public function testPrepareReceiptData(): void
    {
        $this->data = [
            'merchantRespMerchantRef' => 'ref123',
            'merchantRespMessageID' => 'msg456',
            'merchantRespCP' => 'cp789',
            'merchantResp' => '000',
            'merchantRespTimeStamp' => '2024-01-01 10:00:00',
            'merchantRespTid' => 'tid101',
            'merchantRespPan' => 'pan111',
            'merchantRespPurchaseAmount' => 100.50,
            'merchantRespClientReceipt' => 'receipt121',
        ];
        $this->dcc = [
            'amount' => 110.50,
            'currency' => 'USD',
            'rate' => '0.9',
            'markup' => 5.50,
        ];
        $data = $this->prepareReceiptData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('merchantRef', $data);
    }

    public function testGenerateReceipt(): void
    {
        $this->data = ['merchantRespPurchaseAmount' => 100];
        $receipt = $this->generateReceipt();
        $this->assertIsString($receipt);
        $this->assertStringContainsString('Transaction Details', $receipt);
    }

    public function testGenerateClientReceipt(): void
    {
        $this->data = ['merchantRespPurchaseAmount' => 100];
        $receipt = $this->generateClientReceipt();
        $this->assertIsString($receipt);
        $this->assertStringContainsString('Resumo', $receipt);
    }

    public function testRenderReceiptHtmlTechnical(): void
    {
        $data = [
            'recordType' => 1,
            'typeLabel' => 'Transaction',
            'merchantRef' => 'ref123',
            'messageId' => 'msg456',
            'responseCode' => '000',
            'clearingPeriod' => 'cp789',
            'transactionTimestamp' => '2024-01-01 10:00:00',
            'transactionId' => 'tid101',
            'pan' => 'pan111',
            'purchaseAmount' => '100,50',
            'clientReceipt' => 'receipt121',
            'amountEUR' => '110,50',
            'dccCurrency' => 'USD',
            'dccRate' => '0.9',
            'dccMarkup' => '5,50',
            'statusColor' => '#0a8a00',
            'statusText' => 'Aprovada',
            'dcc' => [],
        ];
        $html = $this->renderReceiptHtml($data, true);
        $this->assertIsString($html);
        $this->assertStringContainsString('Transaction Details', $html);
    }
}