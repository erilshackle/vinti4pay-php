<?php

use Erilshk\Vinti4Pay\Vinti4PayClient;

/**
 * 
 */

require '../vendor/autoload.php';
//  include 'Vinti4PayClient.php';

$testEndpoint = getenv('VINTI4_URL') ?: null;
$responseUrl = getenv('VINTI4_RESPONSE_URL') ?: 'http://localhost:8024/callback.php';

$vinti4 = new Vinti4PayClient(
    getenv('VINTI4_POS_ID') ?: '',
    getenv('VINTI4_POS_AUTCODE') ?: '',
    // $testEndpoint ?? 'https://3dsteste.vinti4net.cv/endpoint.php'
);

$amount = 100;

$billing = [
    'email' => 'cliente@email.com',
    'billAddrCountry' => '132',
    'billAddrCity' => 'Praia',
    'billAddrLine1' => 'Praia',
    'billAddrPostCode' => '7600',
];

$vinti4->preparePurchase($amount, $billing, $_POST['ref'] ?? null);
// $vinti4->prepareServicePayment($amount, 7, 1234567);
// $vinti4->prepareRecharge($amount, $entity, $reference);
// $vinti4->prepareRefund($amount, $merchantRef, $merchantSession, $transactionID, $clearingPeriod);

$vinti4->setRequestParams([
    'currency' => '132',
]);

echo $vinti4->createPaymentForm($responseUrl, $language = 'pt');


/// --------- callback.php

$response = $vinti4->processResponse($_POST);
// return Vinti4Response

$response->onSuccess(function ($r) {

});

$response->onError(function ($r) {});

$response->onCancel(function ($r) {});
