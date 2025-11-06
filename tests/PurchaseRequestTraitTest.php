<?php

use PHPUnit\Framework\TestCase;
use Erilshk\Vinti4Pay\Traits\PurchaseRequestTrait;
use Erilshk\Vinti4Pay\Exceptions\Vinti4Exception;

class PurchaseRequestTraitTest extends TestCase
{
    /**
     * Classe dummy para testar a trait
     */
    public function getTraitInstance(): object
    {
        return new class {
            use PurchaseRequestTrait;
        };
    }

    /**
     * Testa buildPurchaseRequest com todos campos obrigatórios
     */
    public function testBuildPurchaseRequestSuccess()
    {
        $instance = $this->getTraitInstance();

        $billing = [
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Av Teste',
            'billAddrPostCode' => '7600',
            'email' => 'teste@email.cv',
        ];

        $method = new ReflectionMethod($instance, 'buildPurchaseRequest');
        $method->setAccessible(true);

        $result = $method->invoke($instance, $billing);

        $this->assertIsString($result);

        $decoded = json_decode(base64_decode($result), true);
        $this->assertArrayHasKey('billAddrCountry', $decoded);
        $this->assertEquals('132', $decoded['billAddrCountry']);
    }

    /**
     * Testa buildPurchaseRequest lançando exceção quando faltam campos obrigatórios
     */
    public function testBuildPurchaseRequestMissingFields()
    {
        $instance = $this->getTraitInstance();

        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessageMatches('/Missing billing fields/');

        $billing = [
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Av Teste',
        ];

        $method = new ReflectionMethod($instance, 'buildPurchaseRequest');
        $method->setAccessible(true);

        $method->invoke($instance, $billing);
    }

    /**
     * Testa buildPurchaseRequest com addrMatch = 'Y' duplicando endereço para shipping
     */
    public function testBuildPurchaseRequestAddrMatch()
    {
        $instance = $this->getTraitInstance();

        $billing = [
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Av Teste',
            'billAddrPostCode' => '7600',
            'email' => 'teste@email.cv',
            'addrMatch' => 'Y'
        ];

        $method = new ReflectionMethod($instance, 'buildPurchaseRequest');
        $method->setAccessible(true);

        $result = $method->invoke($instance, $billing);
        $decoded = json_decode(base64_decode($result), true);

        $this->assertArrayHasKey('shipAddrCountry', $decoded);
        $this->assertEquals($decoded['billAddrCountry'], $decoded['shipAddrCountry']);
    }

    /**
     * Testa formatUserBillingData com array de usuário
     */
    public function testFormatUserBillingDataWithUserArray()
    {
        $instance = $this->getTraitInstance();

        $user = [
            'email' => 'usuario@email.cv',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Av Teste',
            'postCode' => '7600',
            'mobilePhone' => '+23891234567',
        ];

        $method = new ReflectionMethod($instance, 'formatUserBillingData');
        $method->setAccessible(true);

        $billing = $method->invoke($instance, $user);
        
        $this->assertEquals('usuario@email.cv', $billing['email']);
        $this->assertEquals('132', $billing['billAddrCountry']);
        $this->assertArrayHasKey('mobilePhone', $billing);
        $this->assertArrayHasKey('cc', $billing['mobilePhone']);
        $this->assertEquals('238', $billing['mobilePhone']['cc']);
    }

    /**
     * Testa formatUserBillingData com telefone de trabalho
     */
    public function testFormatUserBillingDataWithWorkPhone()
    {
        $instance = $this->getTraitInstance();

        $user = [
            'email' => 'usuario@email.cv',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Av Teste',
            'postCode' => '7600',
            'mobilePhone' => '+23891234567',
            'workPhone' => '+23899887766'
        ];

        $method = new ReflectionMethod($instance, 'formatUserBillingData');
        $method->setAccessible(true);

        $billing = $method->invoke($instance, $user);

        $this->assertArrayHasKey('workPhone', array: $billing);
        $this->assertEquals('238', $billing['workPhone']['cc']);
    }
}
