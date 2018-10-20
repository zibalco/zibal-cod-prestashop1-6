<?php
/*
 * Zibal COD module for Prestashop<=1.6
 * @author Mohammad Zamanzadeh (zamanzadeh@zibal.ir)
 */
if (isset($_GET['do'])) {

	include (dirname(__FILE__) . '/../../config/config.inc.php');
	include (dirname(__FILE__) . '/zibal_cod.php');
	$zibal_cod = new zibal_cod();

	if ($_GET['do'] == 'payment') {

		$zibal_cod->do_payment($cart);

	} else {

		if (isset($_GET['iso_code']) && isset($_POST['zibalId']) && isset($_POST['orderId']) && isset($_POST['refNumber']) && isset($_POST['paidAt'])) {

			$order  = $_POST['orderId'];
			$zibalId = $_POST['zibalId'];
			$paidAt = $_POST['paidAt'];
            $currency_iso_code = $_GET['iso_code'];
            $refNumber = $_POST['refNumber'];
//			$amount = htmlspecialchars($_GET['amount']);
//			$currency_id = $_GET['currency_id'];
//			$currency_iso_code = $_GET['iso_code'];

            $merchantId = Configuration::get('zibal_cod_merchant_id');
            $secretKey = Configuration::get('zibal_cod_secret_key');
            $sandbox = Configuration::get('zibal_cod_sandbox');

            $parameters = array(
                "merchantId"=> $merchantId,
                "secretKey"=> $secretKey,
                "orderId"=> $order,
            );

            $orderFromZibal = $zibal_cod->postToZibal('readOrder', $parameters, $sandbox);

            if($orderFromZibal->result!=1)die();

            $hash = md5($order.$orderFromZibal->amount  . Configuration::get('zibal_cod_hash'));

				
            if ($hash == $_GET['hash']) {

                $order = new Order((int)$order);


                $amount = $orderFromZibal->amount;

                $currency = $context->currency;

                $message = "تراکنش با شناسه زیبال {$zibalId} و کدمرجع {$refNumber} در {$paidAt} با دستگاه کارتخوان زیبال پرداخت شد.";


                //message
                $msg = new Message();
                $msg->message = $message;
                $msg->id_order = intval($order->id);
                $msg->private = 1;
                $msg->add();

                //change state
                $order->setCurrentState(_PS_OS_PAYMENT_);

                echo json_encode(['success'=> true]);

				} else {

					echo $zibal_cod->error('الگو رمزگذاری تراکنش غیر معتبر است');
				}



		} else {

			echo $zibal_cod->error('اطلاعات ارسال شده مربوط به تایید تراکنش ناقص و یا غیر معتبر است');
		}
	}


} else {

	die('Something wrong');
}



