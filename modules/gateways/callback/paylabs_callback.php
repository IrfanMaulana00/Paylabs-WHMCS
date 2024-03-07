<?php

require "../../../init.php";
$whmcs->load_function("gateway");
$whmcs->load_function("invoice");

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
	die('This endpoint should not be opened using browser (HTTP GET).');
	exit();
}

$gatewayParams = getGatewayVariables('paylabs_h5');
$jsonData = file_get_contents('php://input');

if (function_exists('getallheaders')) {
	$headers = getallheaders();
} else {
	// Jika getallheaders() tidak tersedia, gunakan apache_request_headers()
	$headers = apache_request_headers();
}

if (!$jsonData or !$headers) {
	error_log("callback can't get header or body.");
	logActivity("paylabs plugin error: callback can't get header or body.", 0);
	echo "paylabs plugin error: callback can't get header or body.";
	exit;
}

$sign = isset($headers['x-signature']) ? $headers['x-signature'] : '';
$timestamp = isset($headers['x-timestamp']) ? $headers['x-timestamp'] : '';

$data = json_decode($jsonData, true);
$status = $data['status'];
$errCode = $data['errCode'];
$merchantTradeNo = $data['merchantTradeNo'];
$split = explode("-", $merchantTradeNo);
$orderId = isset($split[0]) ? $split[0] : '';
$price = Paylabs_VtWeb::getPriceByOrderId($orderId);

$merchantId = $gatewayParams['paylabsMid'];
$paylabsMode = $gatewayParams['paylabsMode'];
$publicKey = $paylabsMode == "sandbox" ? $gatewayParams['publicKeySandbox'] : $gatewayParams['publicKey'];

$validate = Paylabs_VtWeb::validateTransaction($publicKey, $sign, $jsonData, $timestamp);

if ($validate == true && $status == '02' && $errCode == "0") {
	$message = 'Payment Success - Paylabs ID ' . $data['platformTradeNo'];

	addInvoicePayment(
		$orderId,
		$data['platformTradeNo'],
		$price,
		0,
		$gatewayModuleName
	);

	$privateKey = $paylabsMode == "sandbox" ? $gatewayParams['privateKeySandbox'] : $gatewayParams['privateKey'];
	$date = date("Y-m-d") . "T" . date("H:i:s.B") . "+07:00";
	$requestId = $data['merchantTradeNo'] . "-" . $data['successTime'];

	$response = array(
		"merchantId" => $data['merchantId'],
		"requestId" => $requestId,
		"errCode" => "0"
	);

	$signature = Paylabs_VtWeb::generateHash($privateKey, $response, "/index.php", $date);
	if ($signature->status == false) return false;

	// Set HTTP response headers
	header("Content-Type: application/json;charset=utf-8");
	header("X-TIMESTAMP: " . $date);
	header("X-SIGNATURE: " . $signature->sign);
	header("X-PARTNER-ID: " . $data['merchantId']);
	header("X-REQUEST-ID: " . $requestId);

	// Encode the response as JSON and output it
	echo json_encode($response, JSON_UNESCAPED_UNICODE);
	die();
}

die();
