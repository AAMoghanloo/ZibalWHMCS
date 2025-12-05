<?php
/**
 * Zibal Payment Gateway for WHMCS
 * AAMoghanloo
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function zibal_MetaData()
{
    return array(
        'DisplayName' => 'Zibal Payment (New)',
        'APIVersion' => '1.1',
    );
}

function zibal_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'پرداخت امن زیبال',
        ),
        'MerchantID' => array(
            'FriendlyName' => 'پین درگاه پرداخت',
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'zibal',
            'Description' => 'پین دریافتی از درگاه پرداخت زیبال',
        ),
        'currencyType' => array(
            'FriendlyName' => 'واحد مالی',
            'Type' => 'dropdown',
            'Options' => array(
                'IRR' => 'ریال',
                'IRT' => 'تومان',
            ),
            'Description' => 'واحد پول سیستم WHMCS خود را انتخاب کنید',
        ),
    );
}

function zibal_link($params)
{
    // دریافت پارامتر با نام قدیمی
    $merchantId = $params['MerchantID'];
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount']; 

    // تبدیل ارز
    $payAmount = $amount;
    if ($params['currencyType'] == 'IRT') {
        $payAmount = $amount * 10; 
    }
    $payAmount = (int)ceil($payAmount);

    $callbackUrl = $params['systemurl'] . 'modules/gateways/callback/zibal.php';
    $description = 'Invoice ID: ' . $invoiceId;
    
    // موبایل
    $mobile = $params['clientdetails']['phonenumber'];
    $validMobile = null;
    if (!empty($mobile)) {
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        if (strlen($mobile) >= 10) {
            $validMobile = $mobile;
        }
    }

    $data = array(
        'merchant' => $merchantId,
        'amount' => $payAmount,
        'callbackUrl' => $callbackUrl,
        'description' => $description,
        'orderId' => $invoiceId, 
        'mobile' => $validMobile
    );

    $jsonData = json_encode($data);
    $ch = curl_init('https://gateway.zibal.ir/v1/request');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Zibal WHMCS Module');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ));
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    $result = json_decode($result, true);

    if ($err) {
        return '<span style="color:red">خطا ارتباط: ' . $err . '</span>';
    } elseif (isset($result['result']) && $result['result'] == 100) {
        $trackId = $result['trackId'];
        $url = 'https://gateway.zibal.ir/start/' . $trackId;
        
        return '<form action="' . $url . '" method="GET">
                <input type="submit" class="btn btn-success" value="' . $params['langpaynow'] . '" />
                </form>';
    } else {
        // نمایش خطای دقیق
        return '<span style="color:red">خطا زیبال: ' . ($result['message'] ?? 'Unknown') . ' (Code: ' . ($result['result'] ?? 'N/A') . ')</span>';
    }
}