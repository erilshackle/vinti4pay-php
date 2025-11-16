<?php

use PHPUnit\Framework\TestCase;
use Erilshk\Vinti4Pay\Exceptions\Vinti4Exception;
use Erilshk\Vinti4Pay\Models\ResponseResult;
use Erilshk\Vinti4Pay\Core\Vinti4Pay;

class Vinti4PayTest extends TestCase
{
    private Vinti4Pay $vinti4;

    protected function setUp(): void
    {
        $this->vinti4 = new Vinti4Pay('POS123', 'AUTH456', 'https://mock.vinti4net.cv');
    }

    public function testPreparePaymentMinimal(): void
    {
        $response = $this->vinti4->preparePayment('https://example.com/callback', [
            'amount' => 150.00,
            'billing' => [
                "billAddrCountry" => '',
                "billAddrCity" => '',
                "billAddrLine1" => '',
                "billAddrPostCode" => '',
                'email' => ''
            ]
        ]);

        $this->assertArrayHasKey('postUrl', $response);
        $this->assertArrayHasKey('fields', $response);
        $this->assertEquals(150.00, $response['fields']['amount']);
        $this->assertEquals('1', $response['fields']['transactionCode']);
        $this->assertNotEmpty($response['fields']['fingerprint']);
    }

    public function testPreparePaymentWithBilling(): void
    {
        $billing = [
            'billAddrCountry' => 'CV',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua Teste 123',
            'billAddrPostCode' => '7600',
            'email' => 'cliente@email.cv'
        ];

        $response = $this->vinti4->preparePayment('https://example.com/callback', [
            'amount' => 200.00,
            'billing' => $billing
        ]);

        $this->assertArrayHasKey('purchaseRequest', $response['fields']);
        $this->assertEquals(200.00, $response['fields']['amount']);
        $this->assertNotEmpty($response['fields']['fingerprint']);
    }

    public function testPrepareRefund(): void
    {
        $params = [
            'amount' => 100,
            'merchantRef' => 'REF123',
            'merchantSession' => 'SES456',
            'transactionID' => 'TX789',
            'clearingPeriod' => '2025-11',
            'responseUrl' => 'https://example.com/refund-callback'
        ];

        $response = $this->vinti4->prepareRefund($params);

        $this->assertArrayHasKey('postUrl', $response);
        $this->assertArrayHasKey('fields', $response);
        $this->assertEquals(100, $response['fields']['amount']);
        $this->assertEquals('4', $response['fields']['transactionCode']);
        $this->assertNotEmpty($response['fields']['fingerprint']);
    }

    public function testRenderForm(): void
    {
        $data = $this->vinti4->preparePayment('https://example.com/callback', [
            'amount' => 150.00,
            'transactionCode' => 2
        ]);

        $html = $this->vinti4->renderForm($data);
        $this->assertStringContainsString('Processando o pagamento... por favor aguarde', $html);
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('input type=\'hidden\'', $html);
    }

    public function testProcessPaymentResponseWithInvalidFingerprint(): void
    {
        $postData = [
            'messageType' => '8',
            'resultFingerPrint' => 'INVALID',
            'merchantRespPurchaseAmount' => 150.00,
            'merchantRespMerchantRef' => 'REF123',
            'merchantRespMerchantSession' => 'SES456',
            'timeStamp' => date('Y-m-d H:i:s')
        ];

        $result = $this->vinti4->processPaymentResponse($postData);
        $this->assertInstanceOf(ResponseResult::class, $result);
        $this->assertEquals('INVALID_FINGERPRINT', $result->status);
        $this->assertFalse($result->success);
    }

    public function testProcessRefundResponseWithInvalidFingerprint(): void
    {
        $postData = [
            'messageType' => '10',
            'resultFingerPrint7' => 'INVALID',
            'merchantRespMerchantRef' => 'REF123',
            'merchantRespMerchantSession' => 'SES456',
            'merchantRespErrorCode' => '',
            'merchantRespErrorDescription' => '',
            'timeStamp' => date('Y-m-d H:i:s'),
            'languageMessages' => 'pt'
        ];

        $result = $this->vinti4->processRefundResponse($postData);
        $this->assertInstanceOf(ResponseResult::class, $result);
        $this->assertEquals('INVALID_FINGERPRINT', $result->status);
        $this->assertFalse($result->isSuccessful());
    }

    public function testPreparePaymentWithoutAmountThrowsException(): void
    {
        $this->expectException(Vinti4Exception::class);
        $this->vinti4->preparePayment('https://example.com', []);
    }

    public function testProcessPaymentResponseCancelledByUser(): void
    {
        $data = ['UserCancelled' => 'true'];
        $result = $this->vinti4->processPaymentResponse($data);
        $this->assertEquals('CANCELLED', $result->status);
        $this->assertFalse($result->success);
    }


    public function testProcessRefundResponseWithoutMessageType(): void
    {
        $data = [
            'messageType' => null
        ];
        $result = $this->vinti4->processRefundResponse($data);
        $this->assertEquals('Error.', $result->message); // ou a msg em pt conforme getLangMessage
        $this->assertFalse($result->success);
    }

    public function testProcessPaymentResponseWithValidFingerprint(): void
    {
        $reflection = new ReflectionClass(objectOrClass: $this->vinti4);
        $method = $reflection->getMethod('generateResponseFingerprint');
        $method->setAccessible(true);

        $data = [
            'messageType' => '8',
            'merchantRespPurchaseAmount' => 150.00,
            'merchantRespMerchantRef' => 'REF123',
            'merchantRespMerchantSession' => 'SES456',
            'timeStamp' => date('Y-m-d H:i:s'),
        ];

        $fingerprint = $method->invoke($this->vinti4, $data, 'payment');
        $data['resultFingerPrint'] = $fingerprint;

        $result = $this->vinti4->processPaymentResponse($data);
        $this->assertTrue($result->success);
        $this->assertEquals('SUCCESS', $result->status);
    }
}
