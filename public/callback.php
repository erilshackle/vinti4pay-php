<?php

use Erilshk\Vinti4Pay\Vinti4Pay;
use Erilshk\Vinti4Pay\Vinti4PayClient;
use Erilshk\Vinti4Pay\Vinti4Result;

include '../vendor/autoload.php';


// $vinti4 = new Vinti4Payment(
//     'ENV_VINTI4_POS_ID',
//     'ENV_VINTI4_POS_AUT_CODE'
// );


$vinti4 = new Vinti4PayClient(
    '90000443',
    '9G0UpvtnLXo7Mfa9'
);


// Processa os dados recebidos via POST
$result = $vinti4->processResponse($_POST);


$result->onSuccess(function ($r) {
    echo $r->generateClientReceipt();
});


$result->onError(function ($r) {
    // error
});

$result->onCancel(function ($r) {
    echo $r->generateClientReceipt();
});

echo "<pre>";
print_r($result);
echo "</pre>";
?>
<a href="/">Voltar</a>