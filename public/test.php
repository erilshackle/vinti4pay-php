<?php
require_once '../vendor/autoload.php'; // Ajuste o caminho conforme seu projeto

use Erilshk\Vinti4Pay\Models\Receipt;

// =============== DADOS BASE SIMULADOS ==================
$baseData = [
    'messageType' => '8', // transaction
    'merchantRespMerchantRef' => 'TXN-2025-001',
    'merchantRespMerchantSession' => 'SESS-9988',
    'merchantRespMessageID' => 'MSG-7788',
    'merchantResp' => '00',
    'merchantRespTimeStamp' => '20251106123045',
    'merchantRespPurchaseAmount' => 1000.00,
    'merchantRespPan' => '************1234',
    'cardType' => 'VISA',
    'merchantRespEntityCode' => '123',
    'productType' => 'Pagamento de Luz',
];

// =============== GERAR EXEMPLOS ==================

// 1️⃣ Compra normal sem DCC
$receiptNormal = new Receipt($baseData);
$htmlNormal = $receiptNormal->generateReceipt(null, ['entity' => 'EMPRESA XYZ']);

// 2️⃣ Compra com DCC habilitado
$dataDcc = array_merge($baseData, [
    'dcc' => 'Y',
    'dccAmount' => '10.58',
    'dccCurrency' => 'USD',
    'dccMarkup' => '0.31',
    'dccRate' => '92.65882',
]);
$receiptDcc = new Receipt($dataDcc);
$htmlDcc = $receiptDcc->generateReceipt(null, ['entity' => 'EMPRESA XYZ']);

// 3️⃣ Reembolso (refund)
$dataRefund = $baseData;
$dataRefund['messageType'] = '10';
$receiptRefund = new Receipt($dataRefund);
$htmlRefund = $receiptRefund->generateReceipt(null, ['entity' => 'EMPRESA XYZ']);

// 4️⃣ Pagamento de serviço
$dataService = $baseData;
$dataService['messageType'] = 'P';
$dataService['merchantRespEntityCode'] = '42';
$receiptService = new Receipt($dataService);
$htmlService = $receiptService->generateReceipt(null, ['entity' => 'ELECTRA']);

// 5️⃣ Exemplo com erro de resposta
$dataError = $baseData;
$dataError['merchantResp'] = '05';
$dataError['merchantRespErrorDescription'] = 'Transação não autorizada.';
$receiptError = new Receipt($dataError);
$htmlError = $receiptError->generateReceipt(null, ['entity' => 'EMPRESA XYZ']);

// =============== SAÍDA VISUAL ==================
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Demo de Recibos Vinti4Pay</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 30px;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
        }
        section {
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            max-width: 800px;
        }
        iframe {
            width: 100%;
            height: 500px;
            border: none;
            background: #fff;
        }
    </style>
</head>
<body>

<h1>💳 Demonstração Visual de Recibos Vinti4Pay</h1>

<section>
    <h2>1️⃣ Recibo de Compra Normal</h2>
    <iframe srcdoc="<?= htmlspecialchars($htmlNormal) ?>"></iframe>
</section>

<section>
    <h2>2️⃣ Recibo de Compra com DCC</h2>
    <iframe srcdoc="<?= htmlspecialchars($htmlDcc) ?>"></iframe>
</section>

<section>
    <h2>3️⃣ Recibo de Estorno (Refund)</h2>
    <iframe srcdoc="<?= htmlspecialchars($htmlRefund) ?>"></iframe>
</section>

<section>
    <h2>4️⃣ Recibo de Pagamento de Serviço</h2>
    <iframe srcdoc="<?= htmlspecialchars($htmlService) ?>"></iframe>
</section>

<section>
    <h2>5️⃣ Recibo com Erro</h2>
    <iframe srcdoc="<?= htmlspecialchars($htmlError) ?>"></iframe>
</section>

</body>
</html>
