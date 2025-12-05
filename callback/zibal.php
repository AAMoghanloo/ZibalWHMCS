<?php
/**
 * Zibal Payment Gateway for WHMCS
 * AAMoghanloo
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

$gatewayModuleName = 'zibal';
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$success = $_GET['success'] ?? 0;
$trackId = $_GET['trackId'] ?? null;
$orderId = $_GET['orderId'] ?? null;
$status  = $_GET['status'] ?? 0;

$invoiceId = checkCbInvoiceID($orderId, $gatewayParams['name']);
checkCbTransID($trackId);

if ($success == 1 && ($status == 1 || $status == 2)) {
    
    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    $amountInInvoice = $invoice->total;
    
    $verifyAmount = $amountInInvoice;
    if ($gatewayParams['currencyType'] == 'IRT') {
        $verifyAmount = $amountInInvoice * 10;
    }
    $verifyAmount = (int)ceil($verifyAmount);

    // استفاده از MerchantID (حروف بزرگ) مطابق فایل کانفیگ
    $data = array(
        'merchant' => $gatewayParams['MerchantID'], 
        'trackId' => $trackId
    );
    
    $jsonData = json_encode($data);
    $ch = curl_init('https://gateway.zibal.ir/v1/verify');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Zibal WHMCS Module');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ));
    $result = curl_exec($ch);
    $result = json_decode($result, true);
    curl_close($ch);

    if (isset($result['result']) && ($result['result'] == 100 || $result['result'] == 201)) {
        
        addInvoicePayment(
            $invoiceId,
            $trackId,
            $amountInInvoice,
            0,
            $gatewayModuleName
        );

        logTransaction($gatewayParams['name'], array_merge($_REQUEST, $result), 'Success');
        callback3DSecureRedirect($invoiceId, true);
        
    } else {
        logTransaction($gatewayParams['name'], array_merge($_REQUEST, $result), 'Verify Failed');
        callback3DSecureRedirect($invoiceId, false);
    }

} else {
    logTransaction($gatewayParams['name'], $_REQUEST, 'Unsuccessful Return');
    callback3DSecureRedirect($invoiceId, false);
}