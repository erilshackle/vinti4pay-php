<?php

include '../dist/Vinti4PayClient.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$price = !empty($_POST['price']) ? $_POST['price'] : 100;
$ref = !empty($_POST['pedido']) ? $_POST['pedido'] : 'R' . date('YmdHis');

// include 'Vinti4PayClient.php';

$vinti4 = new Vinti4PayClient(
    getenv('VINTI4_POS_ID'),
    getenv('VINTI4_POS_AUTCODE'),
    $testEndpoint ?? 'https://3dsteste.vinti4net.cv/endpoint.php'
);

$vinti4->preparePurchase(amount: $price, billing: [
    'email' => 'cliente@email.com',
    'billAddrCountry' => '132',
    'billAddrCity' => 'Praia',
    'billAddrLine1' => 'Praia',
    'billAddrPostCode' => '7600',
]);

$vinti4->setRequestParams([
    'merchantRef' => $ref
]);

echo $vinti4->createPaymentForm(
    responseUrl: 'http://localhost:8000/callback.php',
);
