<?php

use PHPUnit\Framework\TestCase;
use Erilshk\Vinti4Pay\Models\Receipt;

class ReceiptTest extends TestCase
{
    protected array $baseData;

    protected function setUp(): void
    {
        $this->baseData = [
            'messageType' => '8',
            'merchantRespMerchantRef' => 'REF123',
            'merchantRespMerchantSession' => 'SES456',
            'merchantRespMessageID' => 'MSG789',
            'merchantResp' => '00',
            'merchantRespTimeStamp' => '20251106123045',
            'merchantRespPurchaseAmount' => 1500.00,
            'merchantRespPan' => '************1234',
            'cardType' => 'VISA',
            'merchantRespEntityCode' => '123',
            'productType' => 'Serviço TV',
        ];
    }

    /** @test */
    public function it_should_detect_transaction_type_from_message_type()
    {
        $receipt = new Receipt(['messageType' => '10']);
        $this->assertSame('10', $receipt->getMessageType());
    }

    /** @test */
    public function it_should_generate_html_receipt_without_template()
    {
        $receipt = new Receipt($this->baseData);

        $html = $receipt->generateReceipt();

        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('Recibo de Compra', $html);
        $this->assertStringContainsString('REF123', $html);
        $this->assertStringContainsString('************1234', $html);
        $this->assertStringContainsString('1.500,00', $html);
        $this->assertStringContainsString('Gerado automaticamente por Vinti4Pay', $html);
    }

    /** @test */
    public function it_should_format_timestamp_correctly()
    {
        $receipt = new Receipt($this->baseData);
        $method = new ReflectionMethod(Receipt::class, 'prepareDataForTemplate');
        $method->setAccessible(true);

        $result = $method->invoke($receipt);

        $this->assertEquals('2025-11-06 12:30:45', $result['TRANSACTION_TIMESTAMP']);
    }

    /** @test */
    public function it_should_include_dcc_information_when_enabled()
    {
        $data = array_merge($this->baseData, [
            'dcc' => 'Y',
            'dccAmount' => '16.68',
            'dccCurrency' => 'USD',
            'dccMarkup' => '0.31',
            'dccRate' => '92.65882',
        ]);

        $receipt = new Receipt($data);
        $html = $receipt->generateReceipt();

        $this->assertStringContainsString('Currency Conversion Rate', $html);
        $this->assertStringContainsString('Dynamic Currency Conversion (DCC)', $html);
        $this->assertStringContainsString('USD', $html);
    }

    /** @test */
    public function it_should_not_include_dcc_when_disabled()
    {
        $data = array_merge($this->baseData, ['dcc' => 'N']);
        $receipt = new Receipt($data);
        $html = $receipt->generateReceipt();

        $this->assertStringNotContainsString('Currency Conversion Rate', $html);
        $this->assertStringNotContainsString('DCC', $html);
    }

    /** @test */
    public function it_should_save_html_file_and_return_path()
    {
        $receipt = new Receipt($this->baseData);

        $tmpFile = sys_get_temp_dir() . '/receipt_test.html';
        if (file_exists($tmpFile)) unlink($tmpFile);

        $path = $receipt->saveReceipt($tmpFile);

        $this->assertFileExists($path);
        $this->assertStringContainsString('>Recibo de', file_get_contents($path) ?: '<div>');
    }

    /** @test */
    public function it_should_generate_refund_receipt()
    {
        $data = $this->baseData;
        $data['messageType'] = '10'; // refund

        $receipt = new Receipt($data);
        $html = $receipt->generateReceipt();

        $this->assertStringContainsString('Recibo de Estorno', $html);
    }

    /** @test */
    public function it_should_generate_service_receipt()
    {
        $data = $this->baseData;
        $data['messageType'] = 'P'; // service

        $receipt = new Receipt($data);
        $html = $receipt->generateReceipt();

        $this->assertStringContainsString('Recibo de Pagamento', $html);
    }

    /** @test */
    public function get_html_should_return_last_generated_html()
    {
        $receipt = new Receipt($this->baseData);
        $receipt->generateReceipt();
        $html = $receipt->getHtml();

        $this->assertStringContainsString('Recibo de Compra', $html);
    }

    /** @test */
    public function it_should_return_fallback_when_template_not_found()
    {
        $receipt = new Receipt($this->baseData, __DIR__ . '/invalid/templates');
        $html = $receipt->generateReceipt('non_existent_template');

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Recibo de Compra', $html);
    }
}
