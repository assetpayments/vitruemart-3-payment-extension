<?php

if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentAssetpayments extends vmPSPlugin {

    public static $_this = false;

    function __construct(& $subject, $config) {

	parent::__construct($subject, $config);

		ob_start();
	    $this->_loggable = true;
	    $this->tableFields = array_keys($this->getTableSQLFields());
		
        if(version_compare(JVM_VERSION,'3','ge')){
            $varsToPush = $this->getVarsToPush ();
        } else {
	    $varsToPush = array('payment_logos' => array('', 'char'),
		'countries' => array(0, 'int'),
		'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
		'payment_currency' =>  array(0, 'int'),
		'assetpayments_merchant_id' => array('', 'string'),
		'assetpayments_merchant_sig' => array('', 'string'),
		'assetpayments_template_id' => array('', 'string'),
	    'status_success' => array('', 'char'),
	    'status_pending' => array('', 'char'),
	    'status_ordered' => array('', 'char'),
	    );
    }

	    $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	    $this->info = ob_get_contents();
	    ob_end_clean();

    }

    protected function getVmPluginCreateTableSQL() {
	return $this->createTableSQL('Payment AssetPayments Table');
    }

    function getTableSQLFields() {
	$SQLfields = array(
	    'id' => 'int(11) unsigned NOT NULL AUTO_INCREMENT',
	    'virtuemart_order_id' => 'int(11) UNSIGNED',
	    'order_number' => 'char(32)',
	    'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
	    'payment_name' => 'varchar(5000) NOT NULL',
	    'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
	    'payment_currency' => 'char(3)',
	);

	return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order) {

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$lang = JFactory::getLanguage();
		$filename = 'com_virtuemart';
		$lang->load($filename, JPATH_ADMINISTRATOR);
		$vendorId = 0;


		$session = JFactory::getSession();
		$return_context = $session->getId();
		$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		$html = "";

		if (!class_exists('VirtueMartModelOrders'))
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		$this->getPaymentCurrency($method);
		// END printing out HTML Form code (Payment Extra Info)
		$currency_code_3 = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = JFactory::getDBO();
        $db->setQuery($currency_code_3);
        $currency_code_3 = $db->loadResult();
        $this->loadInfo($method);
        assetpaymentsapi::form($method,$order,$this,$html,$this->renderPluginName($method),$dbValues,$currency_code_3);
        $this->storePSPluginInternalData($dbValues);
		$modelOrder = VmModel::getModel ('orders');
        $order['order_status'] = $method->status_ordered;
        $order['customer_notified'] = 1;
        $order['comments'] = '';
        $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

        //Delete stuff
        $cart->emptyCart ();
        JRequest::setVar ('html', $html);
        return TRUE;
    }

    protected function loadInfo($method){
        if (!class_exists('assetpaymentsapi')){
		}
    }

    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
	if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
	    return null; // Another method was selected, do nothing
	}

	$db = JFactory::getDBO();
	$q = 'SELECT * FROM `' . $this->_tablename . '` '
		. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
	$db->setQuery($q);
	if (!($paymentTable = $db->loadObject())) {
	    vmWarn(500, $q . " " . $db->getErrorMsg());
	    return '';
	}
	$this->getPaymentCurrency($paymentTable);

	$html = '<table class="adminlist">' . "\n";
	$html .=$this->getHtmlHeaderBE();
	$html .= $this->getHtmlRowBE('STANDARD_PAYMENT_NAME', $paymentTable->payment_name);
	$html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total.' '.$paymentTable->payment_currency);
	$html .= '</table>' . "\n";
	return $html;
    }
    function plgVmDeclarePluginParamsPaymentVM3( &$data) {
        return $this->declarePluginParams('payment', $data);
    }

    protected function checkConditions($cart, $method, $cart_prices) {

	$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

	$countries = array();
	if (!empty($method->countries)) {
	    if (!is_array($method->countries)) {
		$countries[0] = $method->countries;
	    } else {
		$countries = $method->countries;
	    }
	}

	// check address
	if (!is_array($address)) {
	    $address = array();
	    $address['virtuemart_country_id'] = 0;
	}

	if (!isset($address['virtuemart_country_id']))
	    $address['virtuemart_country_id'] = 0;
	if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
	    return true;
	}

	return false;
    }

    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
	return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
	return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
	return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
	return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

	if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
	    return null; // Another method was selected, do nothing
	}
	if (!$this->selectedThisElement($method->payment_element)) {
	    return false;
	}
	 $this->getPaymentCurrency($method);

	$paymentCurrencyId = $method->payment_currency;
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
	return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $result = $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
        if  (JRequest::getVar('option')=='com_virtuemart'&&
            Jrequest::getVar('view')=='orders'&&
            Jrequest::getVar('layout')=='details'){
            if (!class_exists('CurrencyDisplay'))
                require( JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php' );
            if (!class_exists('VirtueMartModelOrders'))
                require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
            $orderModel = VmModel::getModel('orders');
            $order = $orderModel->getOrder($virtuemart_order_id);
            $this->getPaymentCurrency($method);
            $this->loadInfo($method);
            $dbValues = array();
            $currency_code_3 = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
            $db = &JFactory::getDBO();
            $db->setQuery($currency_code_3);
            $currency_code_3 = $db->loadResult();
            if($order['details']['BT']->order_status == $method->status_ordered){
                assetpaymentsapi::form($method,$order,$this,$payment_name, $this->renderPluginName($method),$dbValues, $currency_code_3,0);
            }

    }
    return $result;
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
	return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
	return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
	return $this->setOnTablePluginParams($name, $id, $table);
    }

    public function plgVmOnPaymentNotification() {
		if (JRequest::getVar('pelement')!='assetpayments'){
			return null;
		}
        //file_put_contents(dirname(__FILE__).'/log.txt', var_export(JRequest::get('default'),true));
		if (!class_exists('VirtueMartModelOrders'))
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		$data_encode = JRequest::getVar('data');
		
		
		$data=json_decode(base64_decode($data_encode));
		$sign = Jrequest::getVar('signature');
		
		$json = json_decode(file_get_contents('php://input'), true);
		

		$orderid = $json['Order']['OrderId'];
		$transactionId = $json['Payment']['TransactionId'];
		
		$postprice = floatval($data->amount);
		$payment = $this->getDataByOrderId($orderid);
		$method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
        $order_model = new VirtueMartModelOrders();
        $order_info = $order_model->getOrder($orderid);
        $order_number = $order_info['details']['BT']->order_number;
		
		if(!$method->payment_currency)$this->getPaymentCurrency($method);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = &JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();
		if (!class_exists('CurrencyDisplay')
		)require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order_info['details']['BT']->order_total, false), 2);
		
		
		
		if ($json['Payment']['StatusCode'] == 1) {
			
			$order['order_status'] = $method->status_success;
			$order['virtuemart_order_id'] = $orderid;
			$order['customer_notified'] = 0;
			$order['comments'] = 'AssetPayments Transaction ID: ' .$transactionId; 
			
			if (!class_exists('VirtueMartModelOrders'))
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
			$modelOrder = new VirtueMartModelOrders();
			$modelOrder->updateStatusForOneOrder($orderid, $order, true);
			echo 'OK';
			return true;
		}
		
		if ($json['Payment']['StatusCode'] != 1) {
			
			$order['order_status'] = $method->status_pending;
			$order['virtuemart_order_id'] = $orderid;
			$order['customer_notified'] = 0;
			$order['comments'] = 'AssetPayments Transaction ID: ' .$transactionId; 
			
			if (!class_exists('VirtueMartModelOrders'))
			require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
			$modelOrder = new VirtueMartModelOrders();
			$modelOrder->updateStatusForOneOrder($orderid, $order, true);
			echo 'OK';
			return true;
		} 

		echo 'FAIL';
		return null;
    }

    function plgVmOnPaymentResponseReceived(  &$html) {

        $virtuemart_paymentmethod_id = JRequest::getInt('pm');

        $vendorId = 0;
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $order_pass = JRequest::getVar('order_pass');
        $order_number = JRequest::getVar('order_number');
        $db = JFactory::getDBO();
        $db->setQuery('select order_status from #__virtuemart_orders where order_number='.$db->quote($order_number));
        $order_status = $db->loadResult();
        switch($order_status) {
            case $method->status_success:$msg = JTExt::_('VMPAYMENT_ASSETPAYMENTS_SUCCESS_PAYMENT');break;
            case $method->status_ordered:$msg = JTExt::_('VMPAYMENT_ASSETPAYMENTS_FAIL_PAYMENT');break;
            case $method->status_pending:$msg = JTExt::_('VMPAYMENT_ASSETPAYMENTS_PENDING_PAYMENT');break;
            default:$msg = JTExt::_('VMPAYMENT_ASSETPAYMENTS_FAIL_PAYMENT');
        }

        JFactory::getApplication()->redirect("index.php?option=com_virtuemart&view=orders&layout=details&order_number=$order_number&order_pass=$order_pass",$msg);

        return true;
    }


    function plgVmOnUserPaymentCancel() {
		return null;
    }
    protected function displayLogos($logo_list) {

    $img = "";

    if (!(empty($logo_list))) {
        $url = JURI::base() . str_replace(JPATH_ROOT.'/','',dirname(__FILE__)).'/';
        if (!is_array($logo_list))
        $logo_list = (array) $logo_list;
        foreach ($logo_list as $logo) {
        $alt_text = substr($logo, 0, strpos($logo, '.'));
        $img .= '<img align="middle" src="' . $url . $logo . '"  alt="' . $alt_text . '" /> ';
        }
    }
    return $img;
    }

	private function notifycustomer($order, $order_info ) {
	$lang = jfactory::getlanguage();
	$filename = 'com_virtuemart';
	$lang->load($filename, jpath_administrator);
		if(!class_exists('virtuemartcontrollervirtuemart')) require(jpath_vm_site.ds.'controllers'.ds.'virtuemart.php');

	    if(!class_exists('shopfunctionsf')) require(jpath_vm_site.ds.'helpers'.ds.'shopfunctionsf.php');
		$controller = new virtuemartcontrollervirtuemart();
 		$controller->addviewpath(jpath_vm_administrator.ds.'views');

		$view = $controller->getview('orders', 'html');
		if (!$controllername) $controllername = 'orders';
		$controllerclassname = 'virtuemartcontroller'.ucfirst ($controllername) ;
		if (!class_exists($controllerclassname)) require(jpath_vm_site.ds.'controllers'.ds.$controllername.'.php');

		$view->addtemplatepath(jpath_component_administrator.'/views/orders/tmpl');


		$db = jfactory::getdbo();
		$q = "select concat_ws(' ',first_name, middle_name , last_name) as full_name, email, order_status_name
			from #__virtuemart_order_userinfos
			left join #__virtuemart_orders
			on #__virtuemart_orders.virtuemart_user_id = #__virtuemart_order_userinfos.virtuemart_user_id
			left join #__virtuemart_orderstates
			on #__virtuemart_orderstates.order_status_code = #__virtuemart_orders.order_status
			where #__virtuemart_orders.virtuemart_order_id = '".$order['virtuemart_order_id']."'
			and #__virtuemart_orders.virtuemart_order_id = #__virtuemart_order_userinfos.virtuemart_order_id";
		$db->setquery($q);
		$db->query();
		$view->user = $db->loadobject();
		$view->order = $order;
		jrequest::setvar('view','orders');
		$user= $this->sendvmmail($view, $order_info['details']['bt']->email,false);
		if (isset($view->dovendor)) {
			$this->sendvmmail($view, $view->vendoremail, true);
		}
	}
	private function sendvmmail (&$view, $recipient, $vendor=false) {

		ob_start();
		$view->rendermaillayout($vendor, $recipient);
		$body = ob_get_contents();
		ob_end_clean();

		$subject = (isset($view->subject)) ? $view->subject : jtext::_('com_virtuemart_default_message_subject');
		$mailer = jfactory::getmailer();
		$mailer->addrecipient($recipient);
		$mailer->setsubject($subject);
		$mailer->ishtml(vmconfig::get('order_mail_html',true));
		$mailer->setbody($body);

		if(!$vendor){
			$replyto[0]=$view->vendoremail;
			$replyto[1]= $view->vendor->vendor_name;
			$mailer->addreplyto($replyto);
		}

		if (isset($view->mediatosend)) {
			foreach ((array)$view->mediatosend as $media) {

				$mailer->addattachment($media);
			}
		}

		return $mailer->send();
	}
}

class assetpaymentsapi{
 	static function form($method,$order,$ext,&$html,$name,&$dbValues,$currency_code_3, $redirect = 1){
		$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$OrderAmount = 
		$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order['details']['BT']->order_number);
        switch ($method->payment_currency) {
            case 144:
                $currency = 'USD';
				break;
			case 47:
				$currency = 'EUR';
				break;
            case 199:
                $currency = 'UAH';
                break;
            case 52:
                $currency = 'GBP';
                break;
            default: {
                $currency                 = 'RUB';
                $method->payment_currency = 131;
            };
        }
		
		//****NEW REQUEST****//
		$ip = getenv('HTTP_CLIENT_IP')?:
			  getenv('HTTP_X_FORWARDED_FOR')?:
			  getenv('HTTP_X_FORWARDED')?:
			  getenv('HTTP_FORWARDED_FOR')?:
			  getenv('HTTP_FORWARDED')?:
			  getenv('REMOTE_ADDR');
			  
		//*** Get currency code ***//
		if (!class_exists( 'VmConfig' )) require(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_virtuemart'.DS.'helpers'.DS.'config.php');
        $config = VmConfig::loadConfig();

		$currency_model = VmModel::getModel('currency');
		$displayCurrency = $currency_model->getCurrency( $order['details']['BT']->user_currency_id );
		
		//*** MultiCurrency ***//
		$OrderAmount = $order['details']['BT']->order_total;
		$OrderCurr = $order['details']['BT']->order_currency;
		$UserCurr = $order['details']['BT']->user_currency_id;
		$CurrRate = $order['details']['BT']->user_currency_rate;
		
		If ($OrderCurr != $UserCurr){
			$OrderAmount = round($OrderAmount * $CurrRate,2);
		}
        
		//****Required variables****//	
		$option = array();
		$option['TemplateId'] = $method->assetpayments_template_id;
		$option['CustomMerchantInfo'] = 'Order ID:'.$virtuemart_order_id;
		$option['MerchantInternalOrderId'] = $virtuemart_order_id;
		$option['StatusURL'] = JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&format=raw&pelement=assetpayments';
		$option['ReturnURL'] = JURI::root()."index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=".$order['details']['BT']->virtuemart_paymentmethod_id."&order_number=".$order['details']['BT']->order_number.'&order_pass='.$order['details']['BT']->order_pass;
		$option['IpAddress'] = $ip;
		$option['AssetPaymentsKey'] = $method->assetpayments_merchant_id;
		$option['Amount'] = $OrderAmount;
		$option['Currency'] = $displayCurrency->currency_code_3;
		
		
		$Country = $order['details']['BT']->virtuemart_country_id;
			If ($Country == null || $Country == '' || strlen($Country) > 3){
				$Country =='GB';
			}
		$Firstname = $order['details']['BT']->first_name;
		$Lastname = $order['details']['BT']->last_name;
		$Street = $order['details']['BT']->address_1;
		$City = $order['details']['BT']->city;
		$Email = $order['details']['BT']->email;
		$Phone = $order['details']['BT']->phone_1;
			If ($Phone == ''){
				$Phone = $order['details']['BT']->phone_2;
			}
		
		$option['FirstName'] = $Firstname. ' ' .$Lastname;
        $option['LastName'] = $Lastname;
        $option['Email'] = $Email;
        $option['Phone'] = $Phone;
        $option['City'] = $City;
        $option['Address'] = $Street. ' ' .$City. ' ' .$Country;
		$option['CountryISO'] = 'USA';
		
		//****Adding cart details****//
		foreach ($order['items'] as $key=>$product)
		{
			
			$option['Products'][] = array(
				'ProductId' => $product->order_item_sku,
				'ProductName' => $product->order_item_name,
				'ProductPrice' => round($product->product_final_price * $CurrRate,2),
				'ProductItemsNum' => $product->product_quantity,
				'ImageUrl' => 'https://assetpayments.com/dist/css/images/product.png',
			);
		
		}

		$ext->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['payment_name'] = $name;
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $ext->_virtuemart_paymentmethod_id;
		$dbValues['payment_currency'] = $currency_code_3 ;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency;
		
		$data = base64_encode( json_encode($option) );
		
		$html = '	<form action="https://assetpayments.us/checkout/pay" method="post" id="assetpayments_payment_form">
						<input type="hidden" name="data" id="data" value="'.$data.'"/>
					</form>
					<script> setTimeout(function() {
						document.getElementById("assetpayments_payment_form").submit();
						}, 100);
					</script>';
		return true;
	}
}