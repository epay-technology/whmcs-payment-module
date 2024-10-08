<?php
/**
 * WHMCS Sample Remote Input Gateway Module
 *
 * This sample module demonstrates how to create a merchant gateway module
 * that accepts input of payment details via a remotely hosted page that is
 * displayed within an iframe, returning a token that is stored locally for
 * future billing attempts. As a result, card data never passes through the
 * WHMCS system.
 *
 * As with all modules, within the module itself, all functions must be
 * prefixed with the module filename, followed by an underscore, and then
 * the function name. For this example file, the filename is "remoteinputgateway"
 * and therefore all functions begin "remoteinputgateway_".
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2019
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function epay_MetaData()
{
    return [
        'DisplayName' => 'ePay Remote Input Gateway Module',
        'APIVersion' => '1.1', // Use API Version 1.1
    ];
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/configuration/
 *
 * @return array
 */
function epay_config()
{
    return array(
        "FriendlyName" => array("Type" => 'System', 'Value' => 'ePay'),
        "merchantnumber" => array("FriendlyName" => "Merchant Number", "Type" => "text", "Size" => "20"),
		"group" => array("FriendlyName" => "Group", "Type" => "text", "Size" => "20"),
		"md5key" => array("FriendlyName" => "MD5 Key", "Type" => "text", "Size" => "20"),
		"authsms" => array("FriendlyName" => "Auth SMS", "Type" => "text", "Size" => "20"),
		"authmail" => array("FriendlyName" => "Auth Mail", "Type" => "text", "Size" => "20"),
		"subscriptionfee" => array("FriendlyName" => "Add fee to subscription transactions", "Type" => "yesno"),
		"captureonduedate" => array("FriendlyName" => "Wait to capture on due date.", "Type" => "yesno")
    );

}

/**
 * No local credit card input.
 *
 * This is a required function declaration. Denotes that the module should
 * not allow local card data input.
 */
function epay_nolocalcc() {}

/**
 * Capture payment.
 *
 * Called when a payment is requested to be processed and captured.
 *
 * The CVV number parameter will only be present for card holder present
 * transactions and when made against an existing stored payment token
 * where new card data has not been entered.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 * @return array
 */
function epay_capture($params)
{
	$currencyCodeMap = array(
	"DKK"=>208,
	"USD"=>840,
	"EUR"=>978
	);
	
    // Gateway Configuration Parameters
    $apiUsername = $params['apiUsername'];
    $apiPassword = $params['apiPassword'];
    $testMode = $params['testMode'];

    // Capture Parameters
	$merchantnumber = $params['merchantnumber'];
    $remoteGatewayToken = $params['gatewayid'];
    $cardCvv = $params['cccvv']; // Card Verification Value

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params['description'];
    $amount = $params['amount']*100;
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // A token is required for a remote input gateway capture attempt
    if (!$remoteGatewayToken) {
        return [
            'status' => 'declined',
            'decline_message' => 'No Remote Token',
        ];
    }

    // Perform API call to initiate capture.
	$soap = new SoapClient("https://ssl.ditonlinebetalingssystem.dk/remote/subscription.asmx?wsdl");
	$epay_params                   = array();
	$epay_params['merchantnumber'] = $merchantnumber;
	$epay_params['subscriptionid'] = $remoteGatewayToken; // OK
	$epay_params['orderid']        = $invoiceId; // OK
	$epay_params['amount']         = (string) $amount; // OK
	$epay_params['currency']       = $currencyCodeMap[$currencyCode]; // OK
	$epay_params['instantcapture'] = 1; // OK
	$epay_params['fraud']          = 0; // OK
	$epay_params['transactionid']  = 0;
	$epay_params['description']	   = false;
	// $epay_params['group']          = $group;
	// $epay_params['pwd']            = $this->pwd;
	$epay_params['pbsresponse']    = '-1';
	$epay_params['epayresponse']   = '-1';

	$result_authorize_result = $soap->authorize( $epay_params );
	
	if($result_authorize_result->authorizeResult == true)
	{
        return [
            // 'success' if successful, otherwise 'declined', 'error' for failure
            'status' => 'success',
            // The unique transaction id for the payment
            'transid' => $result_authorize_result->transactionid,
            // Optional fee amount for the transaction
            'fee' => 0,
            // Return only if the token has updated or changed
            // 'gatewayid' => $response['token'],
            // Data to be recorded in the gateway log - can be a string or array
            'rawdata' => print_r($result_authorize_result, true)
        ];		
	}
	else
	{
		return [
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'declined',
        // For declines, a decline reason can optionally be returned
        'declinereason' => 'Error',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => print_r($params, true).print_r($result_authorize_result, true)
    	];
	}
}

/**
 * Remote input.
 *
 * Called when a pay method is requested to be created or a payment is
 * being attempted.
 *
 * New pay methods can be created or added without a payment being due.
 * In these scenarios, the amount parameter will be empty and the workflow
 * should be to create a token without performing a charge.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 * @return array
 */
function epay_remoteinput($params)
{
	$epay_params = array();
	
    $merchantnumber = $params['merchantnumber'];
	
	if(empty($params['invoiceid']) && empty($params['amount']))
	{
		$invoice_or_client_id = $params["clientdetails"]["id"];
	}
	else
	{
		$invoice_or_client_id = $params['invoiceid'];
	}
	
	$amount = $params['amount'] * 100;

	$acceptURL = $params['systemurl'].'index.php?rp=/account/paymentmethods';
    $cancelURL = $params['systemurl'].'index.php?rp=/account/paymentmethods';
        
    if(defined('ADMINAREA')) 
	{
		preg_match("/(.*\/clientssummary.php\?userid=[0-9])/", $_SERVER['HTTP_REFERER'], $matches);
		
        $acceptURL = $matches[1];
        $cancelURL = $matches[1];
    }
	
	$epay_params["windowstate"] = "3"; // 2 = iframe, 3 = Full screen
	$epay_params["paymentcollection"] = "1";
	$epay_params["lockpaymentcollection"] = "1";
	$epay_params["cssurl"] = "https://test.ssdvps.dk/modules/gateways/epay/paymentwindow.css";
	
	$epay_params["merchantnumber"] = $merchantnumber;
	$epay_params["orderid"] = $invoice_or_client_id;
	$epay_params["currency"] = !empty($params['currency']) ? $params['currency'] : 208;
	$epay_params["amount"] = $amount;
	
	$epay_params["accepturl"] = $acceptURL;
	$epay_params["cancelurl"] = $cancelURL;
	$epay_params["callbackurl"] = $params['systemurl'] . 'modules/gateways/callback/epay.php';
	$epay_params["subscription"] = "1";
	$epay_params["subscriptionname"] = $params["clientdetails"]["id"];
	$epay_params["instantcallback"] = "1";
	$epay_params["instantcapture"] = "1";
	$epay_params["smsreceipt"] = $params["authsms"];
	$epay_params["mailreceipt"] = $params["authmail"];
	$epay_params["group"] = $params["group"];
	$epay_params["ownreceipt"] = 1;
	$epay_params["hash"] = md5(implode("", array_values($epay_params)) . $params['md5key']);

    $code = '
    <form action="https://ssl.ditonlinebetalingssystem.dk/integration/ewindow/Default.aspx" method="post">';
    foreach ($epay_params as $key => $value)
    {
        $code .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
    }
    // $code .= '<input type="submit" value="Open the ePay Payment Window">';
    $code .= '</form>';

    return $code;
}

/**
 * Remote update.
 *
 * Called when a pay method is requested to be updated.
 *
 * The expected return of this function is direct HTML output. It provides
 * more flexibility than the remote input function by not restricting the
 * return to a form that is posted into an iframe. We still recommend using
 * an iframe where possible and this sample demonstrates use of an iframe,
 * but the update can sometimes be handled by way of a modal, popup or
 * other such facility.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 * @return array
 */

function epay_remoteupdate($params) {
    return '<div class="alert alert-info text-center">
        Updating your card is not possible. Please create a new Pay Method to make changes.
    </div>';
}


/**
 * Admin status message.
 *
 * Called when an invoice is viewed in the admin area.
 *
 * @param array $params Payment Gateway Module Parameters.
 *
 * @return array
 */
function epay_adminstatusmsg($params)
{
    // Gateway Configuration Parameters
    $apiUsername = $params['apiUsername'];
    $apiPassword = $params['apiPassword'];
    $testMode = $params['testMode'];

    // Invoice Parameters
    $remoteGatewayToken = $params['gatewayid'];
    $invoiceId = $params['id']; // The Invoice ID
    $userId = $params['userid']; // The Owners User ID
    $date = $params['date']; // The Invoice Create Date
    $dueDate = $params['duedate']; // The Invoice Due Date
    $status = $params['status']; // The Invoice Status

    if ($remoteGatewayToken) {
        return [
            'type' => 'info',
            'title' => 'Token Gateway Profile',
            'msg' => 'This customer has a Remote Token storing their card'
                . ' details for automated recurring billing with ID ' . $remoteGatewayToken,
        ];
    }
}


/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 * @throws Exception
 */
function epay_refund($params) {
	
	$epay_params['merchantnumber'] = $params['merchantnumber']; // OK
	$epay_params['transactionid'] = $params['transid'];  // OK
	$epay_params['amount'] = (string) $params['amount']*100; // OK
	$epay_params['pbsresponse'] = "-1";  // OK
	$epay_params['epayresponse'] = "-1";  // OK

	$client = new SoapClient('https://ssl.ditonlinebetalingssystem.dk/remote/payment.asmx?WSDL');
	$gw_status = $client->credit($epay_params);

	if ($gw_status->creditResult == true) {
		/** Success */
    	return [
        	/** 'success' if successful, any other value for failure */
        	'status' => 'success',
        	/** Data to be recorded in the gateway log */
        	'rawdata' => 'Transaction successfully refunded',
        	'transid' => $params['transid']
    	];
	} else {
        return [
            /** 'success' if successful, any other value for failure */
            'status' => 'failed',
            /** Data to be recorded in the gateway log */
            'rawdata' => $gw_status->epayresponse,
            'transid' => $params['transid']
        ];	
	}
}
