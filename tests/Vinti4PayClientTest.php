<?php

use PHPUnit\Framework\TestCase;
use Erilshk\Vinti4Pay\Vinti4PayClient;
use Erilshk\Vinti4Pay\Exceptions\Vinti4Exception;

class Vinti4PayClientTest extends TestCase
{
    private string $posID = '90000123';
    private string $authCode = 'AUTHCODE123';

    public function testPreparePurchaseCreatesRequestArray()
    {
        $client = new Vinti4PayClient($this->posID, $this->authCode);

        $billing = [
            'billAddrCountry' => 'CV',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua 123',
            'billAddrPostCode' => '7600',
            'email' => 'cliente@test.com',
        ];

        $client->preparePurchase(100.00, $billing, 'REF001', 'SESSION001');

        $reflection = new ReflectionClass($client);
        $requestProp = $reflection->getProperty('request');
        $requestProp->setAccessible(true);
        $request = $requestProp->getValue($client);

        $this->assertIsArray($request);
        $this->assertEquals(100.00, $request['amount']);
        $this->assertEquals('REF001', $request['merchantRef']);
        $this->assertEquals('SESSION001', $request['merchantSession']);
    }

    public function testPreparePurchaseThrowsExceptionIfAlreadyPrepared()
    {
        $this->expectException(Vinti4Exception::class);

        $client = new Vinti4PayClient($this->posID, $this->authCode);
        $billing = ['billAddrCountry' => 'CV', 'billAddrCity' => 'Praia', 'billAddrLine1' => 'Rua 1', 'billAddrPostCode' => '7600', 'email' => 'a@b.c'];

        $client->preparePurchase(50, $billing);
        $client->preparePurchase(60, $billing); // Deve lançar exceção
    }

    public function testSetRequestParamsAcceptsAllowedKeys()
    {
        $client = new Vinti4PayClient($this->posID, $this->authCode);

        $params = [
            'currency' => 'CVE',
            'addrMatch' => true
        ];

        $client->setRequestParams($params);

        $reflection = new ReflectionClass($client);
        $prop = $reflection->getProperty('params');
        $prop->setAccessible(true);
        $value = $prop->getValue($client);

        $this->assertEquals(132, $value['currency']); // CVE => 132
        $this->assertTrue($value['addrMatch']);
    }

    public function testSetRequestParamsThrowsForInvalidKey()
    {
        $this->expectException(Vinti4Exception::class);

        $client = new Vinti4PayClient($this->posID, $this->authCode);
        $client->setRequestParams(['invalidKey' => 123]);
    }

    public function testCreatePaymentFormCallsSdkMethods()
    {
        // Cria um mock do SDK
        $sdkMock = $this->createMock(\Erilshk\Vinti4Pay\Vinti4Pay::class);
        $sdkMock->expects($this->once())
            ->method('preparePayment')
            ->willReturn(['prepared' => true]);
        $sdkMock->expects($this->once())
            ->method('renderForm')
            ->willReturn('<form>ok</form>');

        $client = new Vinti4PayClient($this->posID, $this->authCode);

        // Injeta o mock manualmente via Reflection
        $ref = new ReflectionClass($client);
        $prop = $ref->getProperty('sdk');
        $prop->setAccessible(true);
        $prop->setValue($client, $sdkMock);

        $billing = [
            'billAddrCountry' => 'CV',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua 123',
            'billAddrPostCode' => '7600',
            'email' => 'cliente@test.com',
        ];
        $client->preparePurchase(100.00, $billing);

        $html = $client->createPaymentForm('https://callback.test');

        $this->assertStringContainsString('<form>', $html);
    }

    public function testCreatePaymentFormThrowsIfNotPrepared()
    {
        $this->expectException(Vinti4Exception::class);
        $client = new Vinti4PayClient($this->posID, $this->authCode);
        $client->createPaymentForm('https://callback.test');
    }

    public function testCurrencyToCodeConvertsKnownCurrencies()
    {
        $client = new Vinti4PayClient($this->posID, $this->authCode);

        $ref = new ReflectionClass($client);
        $method = $ref->getMethod('currencyToCode');
        $method->setAccessible(true);

        $this->assertEquals(132, $method->invoke($client, 'CVE'));
        $this->assertEquals(840, $method->invoke($client, 'usd'));
        $this->assertEquals(978, $method->invoke($client, 'EUR'));
        $this->assertEquals(132, $method->invoke($client, '132')); // já numérico
    }

    public function testCurrencyToCodeThrowsForInvalid()
    {
        $this->expectException(Vinti4Exception::class);

        $client = new Vinti4PayClient($this->posID, $this->authCode);
        $ref = new ReflectionClass($client);
        $method = $ref->getMethod('currencyToCode');
        $method->setAccessible(true);

        $method->invoke($client, 'XYZ');
    }

    public function testPrepareServicePaymentSetsRequestCorrectly()
    {
        $client = new Vinti4PayClient($this->posID, $this->authCode);
        $client->prepareServicePayment(50, '123', '456', 'REFX', 'SESSIONX');

        $ref = new ReflectionClass($client);
        $prop = $ref->getProperty('request');
        $prop->setAccessible(true);
        $request = $prop->getValue($client);

        $this->assertEquals('service', $ref->getProperty('mode')->getValue($client));
        $this->assertEquals('123', $request['entityCode']);
        $this->assertEquals('456', $request['referenceNumber']);
    }

    public function testReceiptReturnsReceiptInstance()
    {
        $client = new Vinti4PayClient($this->posID, $this->authCode);
        $receipt = $client->receipt(['id' => 1]);
        $this->assertInstanceOf(\Erilshk\Vinti4Pay\Models\Receipt::class, $receipt);
    }
}
