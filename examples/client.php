<?php

require '../vendor/autoload.php';

use Erilshk\Vinti4Pay\Vinti4PayClient;

// -------------------------
// Configuration
// -------------------------
$testEndpoint = getenv('VINTI4_ENDPOINT') ?: null;
$responseUrl  = getenv('VINTI4_RESPONSE_URL') ?: 'http://localhost:8000/callback.php';
$posID        = getenv('VINTI4_POS_ID') ?: '';
$posAuthCode  = getenv('VINTI4_POS_AUTCODE') ?: '';

// -------------------------
// Initialize client
// -------------------------
$vinti4 = new Vinti4PayClient($posID, $posAuthCode, $testEndpoint);

// -------------------------
// Prepare purchase
// -------------------------
$amount = 100.00;

$billing = [
    'email'           => 'cliente@email.com',
    'billAddrCountry' => '132',   // CVE numeric code
    'billAddrCity'    => 'Praia',
    'billAddrLine1'   => 'Av. Principal 10',
    'billAddrPostCode'=> '7600',
];

// Prepare purchase transaction
$vinti4->preparePurchase($amount, $billing, $_POST['ref'] ?? null);

// Optional: set additional request parameters
$vinti4->setRequestParams([
    'currency' => 'CVE', // Will be converted automatically to numeric code
]);

// -------------------------
// Generate HTML payment form
// -------------------------
$htmlForm = $vinti4->createPaymentForm($responseUrl);
echo $htmlForm;

// -------------------------
// Example for processing callback (callback.php)
// -------------------------
/*
$response = $vinti4->processResponse($_POST);

$response->onSuccess(function ($r) {
    echo "Transaction successful! Status: " . $r->status;
});

$response->onError(function ($r) {
    echo "Transaction failed. Message: " . $r->message;
});

$response->onCancel(function ($r) {
    echo "Transaction cancelled by user.";
});
*/
