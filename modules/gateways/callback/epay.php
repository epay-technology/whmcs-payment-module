<?php
/**
 * WHMCS Remote Input Gateway Callback File
 *
 * The purpose of this file is to demonstrate how to handle the return post
 * from a Remote Input and Remote Update Gateway
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging, Adding Payment to an Invoice and
 * adding or updating a payment method.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2019
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

require_once __DIR__ . '/../../../init.php';

App::load_function('gateway');
App::load_function('invoice');

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Verify the module is active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Retrieve data returned in redirect


if(empty($_GET["txnid"]) && empty($_GET["amount"]))
{
	$action = 'create';
	$customerId = isset($_GET["orderid"]) ? $_GET["orderid"] : '';
}
else
{
	$action = 'payment';
	$invoiceId = isset($_GET["orderid"]) ? $_GET["orderid"] : '';
}

$transactionId = isset($_GET["txnid"]) ? $_GET["txnid"] : '';
$paymentAmount = $_GET["amount"] / 100;
$fees = $_GET["txnfee"] / 100;
$currencyCode = isset($_GET['currency']) ? $_GET['currency'] : '';
$cardLastFour = isset($_GET['cardno']) ? $_GET['cardno'] : '';
$cardType = isset($_GET['cardtype']) ? $_GET['paymenttype'] : '';
$cardToken = isset($_GET['subscriptionid']) ? $_GET['subscriptionid'] : '';
$hash = $_GET["hash"];

//Get expire date
$soap = new SoapClient("https://ssl.ditonlinebetalingssystem.dk/remote/subscription.asmx?wsdl");
$soap_subscription_result = $soap->getsubscriptions(array("merchantnumber" => $gatewayParams["merchantnumber"], "subscriptionid" => $_GET["subscriptionid"], "epayresponse" => -1));

if($soap_subscription_result->getsubscriptionsResult == true)
{
    $expmonth = $soap_subscription_result->subscriptionAry->SubscriptionInformationType->expmonth;
    $expyear = $soap_subscription_result->subscriptionAry->SubscriptionInformationType->expyear;
	
    $cardExpiryDate = str_pad($expmonth, 2, '0', STR_PAD_LEFT) . $expyear;
}
///

// $transactionStatus = $transactionId ? 'Success' : 'Failure';

/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */

//Calculate hash
$params = $_GET;
$var = "";

foreach ($params as $key => $value)
{
	if($key != "hash")
	{
		$var .= $value;
	}
}

$genstamp = md5($var . $gatewayParams['md5key']);

if ($hash != $genstamp) {
    logTransaction($gatewayParams['paymentmethod'], $_REQUEST, "Hash Verification Failure");
    die('Hash Verification Failure');
}

$payMethodId = isset($_REQUEST['custom_reference']) ? (int) $_REQUEST['custom_reference'] : 0;

if ($action == 'payment') {
	
    if ($transactionId) {
		
        // Validate invoice id received is valid.
        $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['paymentmethod']);

        // Log to gateway log as successful.
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, "Success");

        // Create a pay method for the newly created remote token.
        invoiceSaveRemoteCard($invoiceId, $cardLastFour, $cardType, $cardExpiryDate, $cardToken);

        // Apply payment to the invoice.
        addInvoicePayment($invoiceId, $transactionId, $paymentAmount, $fees, $gatewayModuleName);

        // Redirect to the invoice with payment successful notice.
        // callback3DSecureRedirect($invoiceId, true);
        echo "Payment done";
    } else {
        // Log to gateway log as failed.
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, "Failed");

        sendMessage('Credit Card Payment Failed', $invoiceId);

        // Redirect to the invoice with payment failed notice.
        // callback3DSecureRedirect($invoiceId, false);
    }
}

if ($action == 'create') {
    if ($cardToken) {
        try {
            // Function available in WHMCS 7.9 and later
            createCardPayMethod(
                $customerId,
                $gatewayModuleName,
                $cardLastFour,
                $cardExpiryDate,
                $cardType,
                null, //start date
                null, //issue number
                $cardToken
            );

            // Log to gateway log as successful.
            logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Create Success');

            // Show success message.
            echo 'Create successful.';
        } catch (Exception $e) {
            // Log to gateway log as unsuccessful.
            logTransaction($gatewayParams['paymentmethod'], $_REQUEST, $e->getMessage());

            // Show failure message.
            echo 'Create failed.. Please try again.';
        }
    } else {
        // Log to gateway log as unsuccessful.
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Create Failed');

        // Show failure message.
        echo 'Create failed. Please try again.';
    }
}

if ($action == 'update') {
    if ($success) {
        try {
            // Function available in WHMCS 7.9 and later
            updateCardPayMethod(
                $customerId,
                $payMethodId,
                $cardExpiryDate,
                null, // card start date
                null, // card issue number
                $cardToken
            );

            // Log to gateway log as successful.
            logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Update Success');

            // Show success message.
            echo 'Update successful.';
        } catch (Exception $e) {
            // Log to gateway log as unsuccessful.
            logTransaction($gatewayParams['paymentmethod'], $_REQUEST, $e->getMessage());

            // Show failure message.
            echo 'Update failed. Please try again.';
        }
    } else {
        // Log to gateway log as unsuccessful.
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Update Failed');

        // Show failure message.
        echo 'Update failed. Please try again.';
    }
}
