<?php
/*
 * Zibal COD module for Prestashop<=1.6
 * @author Mohammad Zamanzadeh (zamanzadeh@zibal.ir)
 */
if (defined('_PS_VERSION_') == FALSE) {

	die('This file cannot be accessed directly');
}

class zibal_cod extends PaymentModule {

	private $_html = '';

	public function __construct() {

		$this->name             = 'zibal_cod';
		$this->tab              = 'payments_gateways';
		$this->version          = '1.0';
		$this->author           = 'zibal.ir';
		$this->currencies       = TRUE;
		$this->currencies_mode  = 'radio';

		parent::__construct();

		$this->displayName      = 'پرداخت در محل با زیبال';
		$this->description      = 'COD Payment with zibal.ir POS';
		$this->confirmUninstall = 'Are you sure you want to delete your details?';

		if (!sizeof(Currency::checkPaymentCurrencies($this->id))) {

			$this->warning = 'No currency has been set for this module.';
		}

		$config = Configuration::getMultiple(array('zibal_cod_merchant_id'));

		if (!isset($config['zibal_cod_merchant_id'])) {

			$this->warning = 'شما باید پارامترهای پیکربندی ماژول پرداخت در محل زیبال را وارد کنید';
		}
	}


	public function install() {

		if (!parent::install() || !Configuration::updateValue('zibal_cod_merchant_id', '') || !Configuration::updateValue('zibal_cod_secret_key', '') || !Configuration::updateValue('zibal_cod_sandbox', '0') || !Configuration::updateValue('zibal_cod_logo', '') || !Configuration::updateValue('zibal_cod_hash', $this->hash_key()) || !$this->registerHook('payment') || !$this->registerHook('paymentReturn')) {

			return FALSE;

		} else {

			return TRUE;
		}
	}

	public function uninstall() {

		if (!Configuration::deleteByName('zibal_cod_merchant_id') ||!Configuration::deleteByName('zibal_cod_secret_key') ||!Configuration::deleteByName('zibal_cod_sandbox') || !Configuration::deleteByName('zibal_cod_logo') || !Configuration::deleteByName('zibal_cod_hash') || !parent::uninstall()) {

			return FALSE;

		} else {

			return TRUE;
		}
	}

	public function hash_key() {

		$en = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');

		$one   = rand(1, 26);
		$two   = rand(1, 26);
		$three = rand(1, 26);

		return $hash = $en[$one] . rand(0, 9) . rand(0, 9) . $en[$two] . $en[$three] . rand(0, 9) . rand(10, 99);
	}

	public function getContent() {

		if (Tools::isSubmit('zibal_cod_setting')) {

			Configuration::updateValue('zibal_cod_merchant_id', $_POST['zibal_cod_merchant_id']);
			Configuration::updateValue('zibal_cod_secret_key', $_POST['zibal_cod_secret_key']);
			Configuration::updateValue('zibal_cod_sandbox', $_POST['zibal_cod_sandbox']);
			Configuration::updateValue('zibal_cod_logo', $_POST['zibal_cod_logo']);

			$this->_html .= '<div class="conf confirm">' . 'Settings Updated' . '</div>';
		}

		$this->_generateForm();

		return $this->_html;
	}

	private function _generateForm() {

		$this->_html .= '<div align="center" dir="ltr">';
		$this->_html .= '<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">';
        $this->_html .= 'merchantId: ' . '';
		$this->_html .= '<input type="text" name="zibal_cod_merchant_id" value="' . Configuration::get('zibal_cod_merchant_id') . '" ><br/>';
        $this->_html .= 'secretKey:' . '';

		$this->_html .= '<input type="text" name="zibal_cod_secret_key" value="' . Configuration::get('zibal_cod_secret_key') . '" ><br/>';

        $this->_html .= 'sandbox?' ;
		$sandbox = (Configuration::get('zibal_cod_sandbox')=='1')?'checked':'';
		$this->_html .= 'Yes <input type="radio" name="zibal_cod_sandbox" value="1" '.$sandbox.'>';
        $sandbox = (Configuration::get('zibal_cod_sandbox')=='0')?'checked':'';
        $this->_html .= 'No <input type="radio" name="zibal_cod_sandbox" value="0" '.$sandbox.'><br/><br/>';
		$this->_html .= '<input type="submit" name="zibal_cod_setting" value="' . 'Save' . '" class="button" />';
		$this->_html .= '</form>';
		$this->_html .= '</div>';
	}
	

	public function do_payment($cart) {

		if (extension_loaded('curl')) {

			$server   = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__;
			$amount   = floatval(number_format($cart->getOrderTotal(true, 3), 2, '.', ''));
			$address  = new Address(intval($cart->id_address_invoice));
			$mobile   = isset($address->phone_mobile) ? $address->phone_mobile : NULL;

            $merchantId = Configuration::get('zibal_cod_merchant_id');
            $secretKey = Configuration::get('zibal_cod_secret_key');
            $sandbox = Configuration::get('zibal_cod_sandbox');

			$currency_id = $cart->id_currency;

			foreach(Currency::getCurrencies() as $key => $currency){
				if ($currency['id_currency'] == $currency_id){
					$currency_iso_code = $currency['iso_code'];
				}
			}
			
			if ($currency_iso_code != 'IRR'){
				$amount = $amount * 10;
			}

            $hash = md5($cart->id.$amount  . Configuration::get('zibal_cod_hash'));

            $callback = $server . 'modules/zibal_cod/process.php?do=call_back&iso_code='.$currency_iso_code.'&hash='.$hash;

            $parameters = array(
                "merchantId"=> $merchantId,
                "secretKey"=> $secretKey,
                "orderId"=> "$cart->id",
                "callbackUrl"=> urlencode($callback),
                "amount"=> $amount,
//                "percentMode"=> 0,
//                "description"=> "Hello World!"
            );

			$result = $this->postToZibal('addOrder', $parameters,$sandbox);
            if($result->result==4)
                $result = $this->postToZibal('editOrder', $parameters,$sandbox);

            if ($result && isset($result->result) && $result->result == 1) {

                $message = "شناسه پرداخت کارتخوان زیبال:  ".$result->zibalId;

                if ($currency_iso_code != 'IRR'){
                    $amount = $amount / 10;
                }

                $customer = new Customer((int)$cart->id_customer);


                $this->validateOrder((int)$cart->id, _PS_OS_PREPARATION_, $amount, $this->displayName." شناسه $result->zibalId", $message, array('transaction_id'=> $result->zibalId), (int)$currency->id, false, $customer->secure_key);

                Tools::redirect('history.php');


			} else {
				$message = 'در ارتباط با وب سرویس zibal.ir خطایی رخ داده است'.'<br>';
				$message .= isset($result->message) ? $result->message : $message;

				echo $this->error($message);
			}

		} else {

			echo $this->error('تابع cURL در سرور فعال نمی باشد');
		}
	}

	public function error($str) {

		return '<div class="alert error">' . $str . '</div>';
	}

	public function success($str) {

		echo '<div class="conf confirm">' . $str . '</div>';
	}

	public function hookPayment($params) {

		global $smarty;
            $smarty->assign('zibal_cod_logo', Configuration::get('zibal_cod_logo'));

            if ($this->active) {

                return $this->display(__FILE__, 'zibal_cod.tpl');
//                return $this->context->smarty->fetch(__FILE__, 'zibal_cod.tpl');
            }

	}

	public function hookPaymentReturn($params) {

		if ($this->active) {

			return NULL;
		}
	}

    function postToZibal($path, $parameters, $sandbox = '0')
    {
        $url = ($sandbox=='0')?'https://api.zibal.ir/merchant/':'https://sandbox-api.zibal.ir/merchant/';
        $url.= $path;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response  = curl_exec($ch);
        curl_close($ch);
        return json_decode($response);
    }
}




