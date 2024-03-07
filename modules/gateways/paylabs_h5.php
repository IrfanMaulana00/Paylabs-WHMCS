<?php
date_default_timezone_set('Asia/Jakarta');
  
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
require_once(dirname(__FILE__) . '/paylabs-lib/Paylabs.php');

function redirect_payment($url)
{
  header('Location: ' . $url, true);
  die();
}

function paylabs_h5_MetaData()
{
    return array(
        'DisplayName' => 'Paylabs H5 Payment Gateway Module',
        'APIVersion' => '2.0',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => true,
    );
}

function paylabs_h5_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Paylabs Payment Gateway',
        ),
        'paylabsMid' => array(
            'FriendlyName' => 'Merchant ID',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => '<br>Input your Merchant Id. Get Merchant Id from Paylabs.',
        ),
        "paylabsMode" => array(
            "FriendlyName" => "Paylabs Mode",
            "Type" => "dropdown",
            "Options" => array(
                'sandbox' => 'Sandbox',
                'production' => 'Production',
            ),
            "Description" => "Select the plugin usage status",
            "Default" => "sandbox",
        ),
        'publicKey' => array(
            'FriendlyName' => 'Paylabs Public Key',
            'Type' => 'textarea',
            'Default' => '',
            'Description' => '<br>The public key provided by Paylabs.',
        ),
        'privateKey' => array(
            'FriendlyName' => 'Merchant Private Key',
            'Type' => 'textarea',
            'Default' => '',
            'Description' => "<br>The private key that generate by yourself. Please don't give this key to anyone.",
        ),
        'publicKeySandbox' => array(
            'FriendlyName' => 'Paylabs Public Key (Sandbox)',
            'Type' => 'textarea',
            'Default' => '',
            'Description' => '<br>The public key provided by Paylabs.',
        ),
        'privateKeySandbox' => array(
            'FriendlyName' => 'Merchant Private Key (Sandbox)',
            'Type' => 'textarea',
            'Default' => '',
            'Description' => "<br>The private key that generate by yourself. Please don't give this key to anyone.",
        ),
    );
}

function paylabs_h5_link($params)
{
    $date = date("Y-m-d") . "T" . date("H:i:s.B") . "+07:00";
    $merchantId = $params['paylabsMid'];
    $paylabsMode = $params['paylabsMode'];
    $publicKey = $paylabsMode == "sandbox" ? $params['publicKeySandbox'] : $params['publicKey'];
    $privateKey = $paylabsMode == "sandbox" ? $params['privateKeySandbox'] : $params['privateKey'];

    $orderid = $params['invoiceid'];
    $description = $params['description'];
    $amount = $params['amount'];
    $currencycode = $params['currency'];

    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $fullName = $firstname . " " . $lastname;
    $phone = $params['clientdetails']['phonenumber'];

    $systemUrl = $params['systemurl'];

    $requestId = $orderid . "-" . time();
    $url = Paylabs_Config::getBaseUrl($paylabsMode);

    $body = [
        'requestId' => $requestId,
        'merchantTradeNo' => $requestId,
        'merchantId' => $merchantId,
        'amount' => number_format($amount, 2, '.', ''),
        'phoneNumber' => $phone,
        'productName' => $orderid,
        'redirectUrl' => $systemUrl . "viewinvoice.php?id=" . $orderid,
        'notifyUrl' => $systemUrl . "modules/gateways/callback/paylabs_callback.php",
        'payer' => $fullName
    ];

    $path = "/payment/v2/h5/createLink";
    $sign = Paylabs_VtWeb::generateHash($privateKey, $body, $path, $date);
    if ($sign->status == false) {
        error_log('Error create sign trx, ' . $sign->desc);
        logActivity('paylabs plugin error: Error create sign trx, ' . $sign->desc, 0);
        echo $sign->desc;
        die();
    }

    try {
        $redirUrl = Paylabs_VtWeb::createTrascation($url . $path, $body, $sign->sign, $date);
        if (isset($redirUrl->errCodeDes)) {
            error_log('Error when create transaction, error : ' . $redirUrl->errCodeDes);
            logActivity('paylabs plugin error: Error when create transaction, error : ' . $redirUrl->errCodeDes, 0);
            echo $redirUrl->errCodeDes;
            die();
        }
      	unset($_SESSION['cart']);
        redirect_payment($redirUrl->url);
    } catch (Exception $e) {
        error_log('Exception create trx : Exception' . $e->getMessage());
        logActivity('paylabs plugin error: Exception create trx : Exception' . $e->getMessage(), 0);
        echo $e->getMessage();
        die();
    }
}