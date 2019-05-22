<?php

if (IS_LOGGED == false) {
    $data = array(
        'status' => 400,
        'error' => 'Not logged in'
    );
    echo json_encode($data);
    exit();
}


use PayPal\Api\Payer;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Details;
use PayPal\Api\Amount;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;


$payment_currency = $pt->config->payment_currency;
$payer        = new Payer();
$item         = new Item();
$itemList     = new ItemList();
$details      = new Details();
$amount       = new Amount();
$transaction  = new Transaction();
$redirectUrls = new RedirectUrls();
$payment      = new Payment();
$payer->setPaymentMethod('paypal');

if ($first == 'replenish') {
	$data    = array('status' => 400);
	$request = (!empty($_POST['amount']) && is_numeric($_POST['amount']));
	if ($request === true) {
		$rep_amount  = $_POST['amount'];
		$redirectUrl = PT_Link("aj/wallet/get_paid?status=success&amount=$rep_amount");
		$redirectUrls->setReturnUrl($redirectUrl)->setCancelUrl(PT_Link(''));    
	    $item->setName('Replenish your balance')->setQuantity(1)->setPrice($rep_amount)->setCurrency($payment_currency);  
	    $itemList->setItems(array($item));    
	    $details->setSubtotal($rep_amount);
	    $amount->setCurrency($payment_currency)->setTotal($rep_amount)->setDetails($details);
	    $transaction->setAmount($amount)->setItemList($itemList)->setDescription('Replenish your balance')->setInvoiceNumber(time());
	    $payment->setIntent('sale')->setPayer($payer)->setRedirectUrls($redirectUrls)->setTransactions(array(
	        $transaction
	    ));

	    try {
	        $payment->create($paypal);
	    }

	    catch (Exception $e) {
	        $data = array(
	            'type' => 'ERROR',
	            'details' => json_decode($e->getData())
	        );

	        if (empty($data['details'])) {
	            $data['details'] = json_decode($e->getCode());
	        }
	        echo json_encode($data);
	    	exit();
	    }

	    $data = array(
	        'status' => 200,
	        'type' => 'SUCCESS',
	        'url' => $payment->getApprovalLink()
	    );

	}
}

if ($first == 'get_paid') {
	$data['status'] = 500;
	$request        = (
		!empty($_GET['paymentId']) && 
		!empty($_GET['PayerID']) && 
		!empty($_GET['status']) && 
		!empty($_GET['amount']) && 
		is_numeric($_GET['amount']) && 
		$_GET['status'] == 'success'
	);

	if ($request === true) {

		$paymentId = PT_Secure($_GET['paymentId']);
		$PayerID   = PT_Secure($_GET['PayerID']);
		$payment   = Payment::get($paymentId, $paypal);
	    $execute   = new PaymentExecution();
	    $execute->setPayerId($PayerID);

	    try{
	        $result = $payment->execute($execute, $paypal);
	    }

	    catch (Exception $e) {
	        $data = array(
	            'type' => 'ERROR',
	            'details' => json_decode($e->getData())
	        );

	        if (empty($data['details'])) {
	            $data['details'] = json_decode($e->getCode());
	        }

	        echo json_encode($data);
	    	exit();
	    }

		$amount  = $_GET['amount'];
		$update  = array('wallet' => ($user->wallet += $amount));
		$db->where('id',$user->id)->update(T_USERS,$update);
		$_SESSION['upgraded'] = true;
		$url     = PT_Link('ads');
    	header("Location: $url");
    	exit();

	}
}