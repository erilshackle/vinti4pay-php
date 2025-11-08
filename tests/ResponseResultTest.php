<?php

use Erilshk\Vinti4Pay\Models\ResponseResult;
use PHPUnit\Framework\TestCase;

class ResponseResultTest extends TestCase
{
    protected function makeBaseData(): array
    {
        return [
            'data' => [
                'messageType' => '8',
                'merchantRespMerchantRef' => 'TXN123',
                'merchantRespMessageID' => 'MSG001',
                'merchantResp' => '00',
                'merchantRespTimeStamp' => '20251106120000',
                'merchantRespPurchaseAmount' => 1000,
                'merchantRespPan' => '************4321',
            ],
            'message' => 'Transação aprovada',
            'success' => true,
        ];
    }

    public function testConstructSuccessDefaults()
    {
        $result = new ResponseResult($this->makeBaseData());

        $this->assertSame('SUCCESS', $result->getStatus());
        $this->assertTrue($result->isSuccessful());
        $this->assertSame('Transação aprovada', $result->getMessage());
        $this->assertIsArray($result->getData());
    }

    public function testConstructErrorWhenNoSuccess()
    {
        $data = $this->makeBaseData();
        $data['success'] = false;
        $data['status'] = 'ERROR';
        $data['message'] = 'Falha';

        $result = new ResponseResult($data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('ERROR', $result->getStatus());
        $this->assertSame('Falha', $result->getMessage());
    }

    public function testDccDataAndHasDcc()
    {
        $data = $this->makeBaseData();
        $data['dcc'] = [
            'amount' => '10.58',
            'currency' => 'USD',
            'rate' => '92.65882',
            'markup' => '0.31',
        ];
        $result = new ResponseResult($data);

        $this->assertTrue($result->hasDcc());
        $this->assertSame('USD', $result->getDcc()['currency']);
    }

    public function testGetStatusLabelInPortuguese()
    {
        $result = new ResponseResult($this->makeBaseData());
        $this->assertSame('Transação bem-sucedida', $result->getStatusLabel('pt'));
    }

    public function testInvalidFingerprintGetter()
    {
        $data = $this->makeBaseData();
        $data['status'] = ResponseResult::STATUS_INVALID_FINGERPRINT;
        $data['data']['resultFingerPrint'] = 'abc123';
        $result = new ResponseResult($data);

        $result->hasInvalidFingerprint($fp);
        $this->assertSame('abc123', $fp);
    }

    public function testGetMerchantReceipt()
    {
        $data = $this->makeBaseData();
        $data['data']['merchantRespClientReceipt'] = '<html>Recibo</html>';
        $result = new ResponseResult($data);

        $this->assertStringContainsString('Recibo', $result->getMerchantReceipt());
    }

    public function testToArrayAndJsonSerializeConsistency()
    {
        $data = $this->makeBaseData();
        $result = new ResponseResult($data);

        $array = $result->toArray();
        $json = $result->jsonSerialize();

        $this->assertSame($array, $json);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('message', $array);
    }

    public function testJsonDataReturnsValidJson()
    {
        $data = $this->makeBaseData();
        $result = new ResponseResult($data);

        $json = $result->jsonData();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('merchantRespMerchantRef', $decoded);
    }

    public function testOnSuccessAndOnErrorCallbacks()
    {
        $data = $this->makeBaseData();
        $result = new ResponseResult($data);

        $called = false;
        $result->onSuccess(function ($r) use (&$called) {
            $called = true;
        });

        $this->assertTrue($called, 'onSuccess() should trigger when successful');

        // error case
        $data['success'] = false;
        $data['status'] = ResponseResult::STATUS_ERROR;
        $error = new ResponseResult($data);

        $calledError = false;
        $error->onError(function ($r) use (&$calledError) {
            $calledError = true;
        });
        $this->assertTrue($calledError, 'onError() should trigger when failed');
    }

    public function testOnCancelCallback()
    {
        $data = $this->makeBaseData();
        $data['status'] = ResponseResult::STATUS_CANCELLED;
        $result = new ResponseResult($data);

        $called = false;
        $result->onCancel(function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called, 'onCancel() should trigger when status is CANCELLED');
    }

    public function testGenerateReceiptAndClientReceiptContainExpectedFields()
    {
        $data = $this->makeBaseData();
        $data['dcc'] = [
            'amount' => '10.58',
            'currency' => 'USD',
            'rate' => '92.65',
            'markup' => '0.31',
        ];
        $result = new ResponseResult($data);

        $htmlTech = $result->generateReceipt();
        $htmlClient = $result->generateClientReceipt();

        $this->assertStringContainsString('Transaction Details', $htmlTech);
        $this->assertStringContainsString('Currency Conversion Rate', $htmlTech);
        $this->assertStringContainsString('Resumo', $htmlClient);
        $this->assertStringContainsString('Dynamic Currency Conversion', $htmlClient);
    }
}
