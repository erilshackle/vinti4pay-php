<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../dist/Vinti4PayClient.php';

final class Vinti4PayClientStandaloneTest extends TestCase
{
    private Vinti4PayClient $client;

    protected function setUp(): void
    {
        $this->client = new Vinti4PayClient('POS123', 'AUTHCODE123');
    }

    /** @test */
    public function it_converts_currency_correctly()
    {
        $ref = new ReflectionClass($this->client);
        $method = $ref->getMethod('currencyToCode');
        $method->setAccessible(true);

        $this->assertEquals(132, $method->invoke($this->client, 'CVE'));
        $this->assertEquals(978, $method->invoke($this->client, 'EUR'));
        $this->assertEquals(840, $method->invoke($this->client, '840'));
        $this->expectException(Exception::class);
        $method->invoke($this->client, 'INVALID');
    }

    /** @test */
    public function it_sets_valid_request_params()
    {
        $result = $this->client->setRequestParams([
            'currency' => 'EUR',
            'languageMessages' => 'en',
            'entityCode' => '201',
            'referenceNumber' => '987654',
        ]);

        $this->assertInstanceOf(Vinti4PayClient::class, $result);
    }

    /** @test */
    public function it_throws_on_invalid_request_param_key()
    {
        $this->expectException(Exception::class);
        $this->client->setRequestParams(['invalidKey' => 'test']);
    }

    /** @test */
    public function it_sets_valid_billing_params_and_normalizes()
    {
        $client = $this->client->setBillingParams(
            'cliente@example.com',
            '132',
            'Praia',
            'Palmarejo',
            '7600',
            ['addrMatch' => 'Y', 'billAddrState' => 'ILHA']
        );

        $ref = new ReflectionClass($client);
        $prop = $ref->getProperty('params');
        $prop->setAccessible(true);
        $params = $prop->getValue($client);

        $this->assertArrayHasKey('billing', $params);
        $this->assertEquals('Y', $params['billing']['addrMatch']);
        $this->assertEquals('Praia', $params['billing']['shipAddrCity']);
    }

    /** @test */
    public function it_rejects_invalid_email_in_billing()
    {
        $this->expectException(Exception::class);
        $this->client->setBillingParams('invalid-email', '132', 'Praia', 'Palmarejo', '7600');
    }

    /** @test */
    public function it_handles_missing_phone_fields_in_billing()
    {
        $this->expectException(Exception::class);
        $this->client->setBillingParams(
            'a@b.cv', '132', 'Praia', 'Rua', '1234',
            ['mobilePhone' => ['cc' => '238']]
        );
    }

    /** @test */
    public function it_prepares_purchase_service_recharge_refund()
    {
        $billing = [
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua X',
            'billAddrPostCode' => '7600',
            'email' => 'cliente@cv.cv'
        ];

        $this->assertInstanceOf(Vinti4PayClient::class, $this->client->preparePurchase(1000, $billing));
        
        $client2 = new Vinti4PayClient('POS1', 'AUTH');
        $this->assertInstanceOf(Vinti4PayClient::class, $client2->prepareServicePayment(2000, '201', '123456'));

        $client3 = new Vinti4PayClient('POS1', 'AUTH');
        $this->assertInstanceOf(Vinti4PayClient::class, $client3->prepareRecharge(500, '301', '981234567'));

        $client4 = new Vinti4PayClient('POS1', 'AUTH');
        $this->assertInstanceOf(Vinti4PayClient::class, $client4->prepareRefund(1500, 'REF123', 'SESSION1', 'TX999', '20241101'));
    }

    /** @test */
    public function it_throws_when_preparing_twice()
    {
        $billing = [
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua',
            'billAddrPostCode' => '7600',
            'email' => 'x@y.cv'
        ];
        $this->client->preparePurchase(100, $billing);
        $this->expectException(Exception::class);
        $this->client->prepareServicePayment(100, '123', '987');
    }

    /** @test */
    public function it_creates_payment_form_correctly()
    {
        $billing = [
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua',
            'billAddrPostCode' => '7600',
            'email' => 'x@y.cv'
        ];

        $client = new Vinti4PayClient('POS123', 'AUTHCODE');
        $client->preparePurchase(1000, $billing);
        $html = $client->createPaymentForm('https://meu-site.cv/callback');
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('hidden', $html);
    }

    /** @test */
    public function it_throws_if_create_payment_form_called_before_prepare()
    {
        $this->expectException(Exception::class);
        $this->client->createPaymentForm('https://meu-site.cv/retorno');
    }

    /** @test */
    public function it_processes_cancelled_response()
    {
        $result = $this->client->processResponse(['UserCancelled' => 'true']);
        $this->assertEquals('CANCELLED', $result['status']);
    }

    /** @test */
    public function it_detects_invalid_fingerprint()
    {
        $post = [
            'messageType' => '8',
            'merchantRespPurchaseAmount' => '100',
            'resultFingerPrint' => 'INCORRECT',
            'merchantRespMerchantRef' => 'REF',
            'merchantRespMerchantSession' => 'SESSION',
        ];

        $result = $this->client->processResponse($post);
        $this->assertEquals('INVALID_FINGERPRINT', $result['status']);
        $this->assertArrayHasKey('debug', $result);
    }

    /** @test */
    public function it_processes_successful_fingerprint()
    {
        $ref = new ReflectionClass($this->client);
        $method = $ref->getMethod('generateResponseFingerprint');
        $method->setAccessible(true);

        $post = [
            'messageType' => '8',
            'merchantRespPurchaseAmount' => '100',
            'merchantRespMerchantRef' => 'REF',
            'merchantRespMerchantSession' => 'SESSION',
        ];
        $post['resultFingerPrint'] = $method->invoke($this->client, $post);

        $result = $this->client->processResponse($post);
        $this->assertTrue($result['success']);
        $this->assertEquals('SUCCESS', $result['status']);
    }

    /** @test */
    public function it_handles_error_response_with_message()
    {
        $post = [
            'messageType' => '99',
            'merchantRespErrorDescription' => 'Falha geral',
        ];
        $result = $this->client->processResponse($post);
        $this->assertEquals('Falha geral', $result['message']);
    }
}
