<?php
/**
 *
 * PAYCOMET payment plugin
 *
 * @author Valerie Isaksen
 * @version $Id: helper.php 9420 2017-01-12 09:35:36Z Milbo $
 * @package VirtueMart
 * @subpackage payment
 * Copyright (C) 2004 - 2017 Virtuemart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */


defined('_JEXEC') or die('Restricted access');


/**
 * @property  request_type
 */
class  PaytpvHelperPaytpv {
	var $_method;
	var $cart;
	var $order;
	var $vendor;
	
	var $context;
	var $total;
	var $post_variables;
	var $post_string;
	var $requestData;
	var $response;
	var $currency_code_3;
	var $currency_display;
	var $plugin;



	function __construct ($method, $plugin) {
		
		if ($method->disableoffersavecard) {
			$method->offer_save_card = 0;
		}
		$this->_method = $method;
		$this->_method->password=trim($this->_method->password);
		$this->_method->clientcode=trim($this->_method->clientcode);
		$this->plugin = $plugin;
		$session = JFactory::getSession();
		$this->context = $session->getId();
	}

	/**
	 * @return string
	 */

	function getPaymentButton () {
		$card_payment_button = vmText::_('VMPAYMENT_PAYTPV_PAY_NOW');
		return $card_payment_button;
	}

    /**
     * @return string
     */

    function getRemoveButton () {
        $card_remove_button = vmText::_('VMPAYMENT_PAYTPV_REMOVE_CARD');
        return $card_remove_button;
    }

	public function isSecureTransaction($importe,$token_card){

        $terminales = $this->_method->terminales;
        $tdfirst = $this->_method->tdfirst;
        $tdmin = str_replace(",",".",$this->_method->tdmin);
        $tdmin = vmPSPlugin::getAmountValueInCurrency($tdmin, $this->_method->payment_currency) * 100;
        // Transaccion Segura:
        
        // Si solo tiene Terminal Seguro
        if ($terminales==0)
            return true;   

        // Si esta definido que el pago es 3d secure y no estamos usando una tarjeta tokenizada
        if ($tdfirst && $token_card==0){
            return true;
        }

       
        // Si se supera el importe maximo para compra segura
        if ($terminales==2 && ($tdmin>0 && $tdmin < $importe)){
            return true;
        }

         // Si esta definido como que la primera compra es Segura y es la primera compra aunque este tokenizada
        if ($terminales==2 && $tdfirst && $token_card>0 && $this->isFirstPurchaseToken($this->order['details']['BT']->virtuemart_user_id,$token_card)){
            return true;
        }
       
        return false;
    }


    public function isFirstPurchaseCustomer($userId){
    	$PaytpvTableName = $this->plugin->getPaytpvTableName();

    	$config = new JConfig();
    	$success_status = $this->_method->status_success;
    	$table_orders = "#__virtuemart_orders";

		$q = 'SELECT p.virtuemart_order_id FROM `' . $table_orders . '` o INNER JOIN `' . $PaytpvTableName . '` p ON o.virtuemart_order_id = p.virtuemart_order_id WHERE o.virtuemart_user_id="' . $userId . '" and o.order_status ="' . $success_status . '"';
	
		$db = JFactory::getDBO();
		$db->setQuery($q);
		if (!$db->loadResult()) {
			return true;
		}else{
			return false;
		}
	}


    public function removeCard($hashCard){

        $virtuemart_user_id = JFactory::getUser()->id;

        $PaytpvTableTokenName = $this->plugin->getPaytpvTokenTableName();
        $db = JFactory::getDBO();

        $db->setQuery('SELECT virtuemart_user_id FROM `' . $PaytpvTableTokenName . '` WHERE virtuemart_user_id="' . $virtuemart_user_id . '" and hash ="' . $hashCard . '"');
        $UserId = $db->loadResult();
        if($UserId){
            $db = JFactory::getDBO();
            $q = 'DELETE FROM `' . $PaytpvTableTokenName . '` WHERE virtuemart_user_id="' . $virtuemart_user_id . '" and hash ="' . $hashCard . '"';
            $db->setQuery($q);
            $db->execute();
            return true;
        }else{
            return false;
        }
    }


    public function isFirstPurchaseToken($userId,$token_card){
    	if (empty($userId)) {
			if (JFactory::getApplication()->isSite()) {
				$userId = $this->order['details']['BT']->virtuemart_user_id;
			}
		}

		$config = new JConfig();
		$table_orders = $connect->dbprefix."virtuemart_orders";

		$success_status = $this->_method->status_success;

		$PaytpvTableName = $this->plugin->getPaytpvTableName();
		$q = 'SELECT `p.virtuemart_order_id` FROM `' . $table_orders . '` o INNER JOIN ' . $PaytpvTableName . ' p ON o.virtuemart_order_id = p.virtuemart_order_id WHERE `o.virtuemart_user_id`="' . $userId . '" AND `p.TokenUser`="' . $token_card . '" and o.order_status ="' . $success_status . '"';
		print "Tx:" . $q;
		$db = JFactory::getDBO();
		$db->setQuery($q);
		if (!$db->loadResult()) {
			return true;
		}else{
			return false;
		}

    }

    function getExecutePurchaseTokenUrl($IdUser,$TokenUser,$dsecure){

    			
        $session = JFactory::getSession();
        
        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

        //$usr = & JFactory::getUser();
        $new_status = '';

        $address = ((isset($this->order['details']['ST'])) ? $this->order['details']['ST'] : $this->order['details']['BT']);

        $vendorModel = new VirtueMartModelVendor();
        $vendorModel->setId(1);
        $vendor = $vendorModel->getVendor();

        $currency_code_3 = $this->currency_code_3;

        
        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($this->order['details']['BT']->order_total, $this->_method->payment_currency);
        $order_amount = $totalInPaymentCurrency['display'];
        $order_amount_txt = vmText::sprintf('VMPAYMENT_PAYTPV_PAYMENT_TOTAL', $totalInPaymentCurrency['display']);

        $totalInPaymentCurrency = vmPSPlugin::getAmountValueInCurrency($this->order['details']['BT']->order_total, $this->_method->payment_currency) * 100;

        $urlok = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $this->order['details']['BT']->order_number . '&pm=' . $this->order['details']['BT']->virtuemart_paymentmethod_id);
        $urlko = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $this->order['details']['BT']->order_number . '&pm=' . $this->order['details']['BT']->virtuemart_paymentmethod_id);

        $ds_merchant_order = $this->order['details']['BT']->order_number;
        $ds_merchant_transactiontype = 109;
        
        $payment_name = $this->plugin->renderPluginName($this->_method, 'order');

        $consumerlanguage = $this->getLang();

        if ($this->_method->merchantdata){
            $merchantData = $this->getMerchantData($this->order);
        }else{
            $merchantData = null;
        }

        $url = "";

        if (!class_exists('Paytpv_Bankstore')) {
            require(VMPATH_ROOT . DS.'plugins'. DS.'vmpayment'. DS.'paytpv'. DS.'paytpv_bankstore.php');
        }

        $paytpv = new Paytpv_Bankstore($this->_method->clientcode,$this->_method->terminal, $this->_method->password, "");

        $response = $paytpv->ExecutePurchaseTokenUrl($this->order['details']['BT']->order_number, $totalInPaymentCurrency, $currency_code_3, $IdUser,$TokenUser, $consumerlanguage, "", $dsecure, null, $urlok, $urlko, $merchantData);
        if ($response->DS_ERROR_ID==0){
            $url = $response->URL_REDIRECT;
        }

        return $url;
    }


	function getRemoteCCFormParams(){
		$realvault = false;
		$useSSL = $this->cart->useSSL;
		$submit_url = JRoute::_('index.php?option=com_virtuemart&Itemid=' . vRequest::getInt('Itemid') . '&lang=' . vRequest::getCmd('lang', ''), $this->cart->useXHTML, $useSSL);
		$card_payment_button = $this->getPaymentButton();
        $card_remove_button  = $this->getRemoveButton();
		$notificationTask = "handleCapture";

		
        $session = JFactory::getSession();
        $return_context = $session->getId();
        //$this->debugLog('plgVmConfirmedOrder order number: ' . $this->order['details']['BT']->order_number, 'message');

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

        //$usr = & JFactory::getUser();
        $new_status = '';

        $vendorModel = new VirtueMartModelVendor();
        $vendorModel->setId(1);
        $vendor = $vendorModel->getVendor();

        $currency_code_3 = $this->currency_code_3;

        
        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($this->order['details']['BT']->order_total, $this->_method->payment_currency);
        $order_amount = $totalInPaymentCurrency['display'];
        $order_amount_txt = vmText::sprintf('VMPAYMENT_PAYTPV_PAYMENT_TOTAL', $totalInPaymentCurrency['display']);

        $totalInPaymentCurrency = vmPSPlugin::getAmountValueInCurrency($this->order['details']['BT']->order_total, $this->_method->payment_currency) * 100;

        $urlok = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $this->order['details']['BT']->order_number . '&pm=' . $this->order['details']['BT']->virtuemart_paymentmethod_id);
        $urlko = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $this->order['details']['BT']->order_number . '&pm=' . $this->order['details']['BT']->virtuemart_paymentmethod_id);

        $ds_merchant_order = $this->order['details']['BT']->order_number;
        $ds_merchant_transactiontype = 1;
        
        $payment_name = $this->plugin->renderPluginName($this->_method, 'order');

        if (!class_exists('Paytpv_Bankstore')) {
            require(VMPATH_ROOT . DS.'plugins'. DS.'vmpayment'. DS.'paytpv'. DS.'paytpv_bankstore.php');
        }

        $paytpv = new Paytpv_Bankstore($this->_method->clientcode,$this->_method->terminal, $this->_method->password, "");

        $dsecure = $this->isSecureTransaction($totalInPaymentCurrency,0)?1:0;
        $consumerlanguage = $this->getLang();

        if ($this->_method->merchantdata){
            $merchantData = $this->getMerchantData($this->order);
        }else{
            $merchantData = null;
        }

        $response = $paytpv->ExecutePurchaseUrl($this->order['details']['BT']->order_number, $totalInPaymentCurrency, $currency_code_3, $consumerlanguage, "", $dsecure, null, $urlok, $urlko, $merchantData);
        $url = "";
        if ($response->DS_ERROR_ID==0){
            $url = $response->URL_REDIRECT;
        }

        $paytpv_cards = $this->getPaytpvCardsDropDown();
       		

        return array(
        	"url"						  => $url,
			"order_amount"                => $order_amount_txt,
			"payment_name"                => $payment_name,
			"submit_url"                  => $submit_url,
			"card_payment_button"         => $card_payment_button,
            "card_remove_button"          => $card_remove_button,
			"notificationTask"            => $notificationTask,	
			'offer_save_card'             => !$this->_method->disableoffersavecard,
			'remembercardunselected'	  => (!$this->_method->remembercardunselected && $this->order['details']['BT']->virtuemart_user_id>0)?1:0,
			'paytpv_cards'	  			  => $paytpv_cards,
			'order_number'                => $this->order['details']['BT']->order_number,
			'user_id'		              => $this->order['details']['BT']->virtuemart_user_id,
			'virtuemart_paymentmethod_id' => $this->_method->virtuemart_paymentmethod_id,
		);
	}

	public function getMerchantData($order){
		 /*Datos Scoring*/
	
       
        $Merchant_Data["scoring"]["customer"]["id"] = $order['details']['BT']->virtuemart_user_id;
        $Merchant_Data["scoring"]["customer"]["name"] = $order['details']['BT']->first_name;
        $Merchant_Data["scoring"]["customer"]["surname"] = $order['details']['BT']->last_name;
        $Merchant_Data["scoring"]["customer"]["email"] =  $order['details']['BT']->email;

        $phone = "";

        $Merchant_Data["scoring"]["customer"]["phone"] = $order['details']['BT']->phone_1;
        $Merchant_Data["scoring"]["customer"]["mobile"] = $order['details']['BT']->phone_2;
        $Merchant_Data["scoring"]["customer"]["firstBuy"] = ($this->isFirstPurchaseCustomer($order['details']['BT']->virtuemart_user_id))?1:0;
        
        // Shipping
        // Address
        if ($order['details']['BT']){
            $street0 = $order['details']['BT']->address_1;
            $street1 = $order['details']['BT']->address_2;
            $state_name = ShopFunctions::getStateByID($order['details']['BT']->virtuemart_state_id);
            $country_name = ShopFunctions::getCountryByID($order['details']['BT']->virtuemart_country_id, 'country_2_code');
        }

        $Merchant_Data["scoring"]["shipping"]["address"]["streetAddress"] = ($order['details']['BT'])?$street0:"";
        $Merchant_Data["scoring"]["shipping"]["address"]["extraAddress"] = ($order['details']['BT'])?$street1:"";
        $Merchant_Data["scoring"]["shipping"]["address"]["city"] = ($order['details']['BT'])?$order['details']['BT']->city:"";
        $Merchant_Data["scoring"]["shipping"]["address"]["postalCode"] = ($order['details']['BT'])?$order['details']['BT']->zip:"";
        $Merchant_Data["scoring"]["shipping"]["address"]["state"] = ($order['details']['BT'])?$state_name:"";
        $Merchant_Data["scoring"]["shipping"]["address"]["country"] = ($order['details']['BT'])?$country_name:"";

        // Time
        $Merchant_Data["scoring"]["shipping"]["time"] = "";

        // Billing
        
        if ($order['details']['ST']){
        	$street0 = $order['details']['ST']->address_1;
            $street1 = $order['details']['ST']->address_2;
            $state_name = ShopFunctions::getStateByID($order['details']['ST']->virtuemart_state_id);
            $country_name = ShopFunctions::getCountryByID($order['details']['ST']->virtuemart_country_id, 'country_2_code');

        }

        $Merchant_Data["scoring"]["billing"]["address"]["streetAddress"] = ($order['details']['ST'])?$street0:"";
        $Merchant_Data["scoring"]["billing"]["address"]["extraAddress"] = ($order['details']['ST'])?$street1:"";
        $Merchant_Data["scoring"]["billing"]["address"]["city"] = ($order['details']['ST'])?$order['details']['ST']->city:"";
        $Merchant_Data["scoring"]["billing"]["address"]["postalCode"] = ($order['details']['ST'])?$order['details']['ST']->zip:"";
        $Merchant_Data["scoring"]["billing"]["address"]["state"] = ($order['details']['ST'])?$state_name:"";
        $Merchant_Data["scoring"]["billing"]["address"]["country"] = ($order['details']['ST'])?$country_name:"";

        $Merchant_Data["futureData"] = "";

        $Merchant_Data = urlencode(base64_encode(json_encode($Merchant_Data)));

        return $Merchant_Data;


	}


	public function saveCard($virtuemart_order_id,$IdUser,$TokenUser){

		if (!class_exists('Paytpv_Bankstore')) {
		    require(VMPATH_ROOT . DS.'plugins'. DS.'vmpayment'. DS.'paytpv'. DS.'paytpv_bankstore.php');
		}
		$paytpv = new Paytpv_Bankstore($this->_method->clientcode,$this->_method->terminal, $this->_method->password, "");

	    $resp = $paytpv->InfoUser($IdUser,$TokenUser);

	    if ('' == $resp->DS_ERROR_ID || 0 == $resp->DS_ERROR_ID) {
            return $this->addCustomerCard($virtuemart_order_id,$IdUser,$TokenUser,$resp);
        }else{
            return false;
        }
	}


	public function addCustomerCard($virtuemart_order_id,$IdUser,$TokenUser,$response){


        $card =  $response->DS_MERCHANT_PAN;
        $card = 'XXXX-XXXX-XXXX-' . substr($card, -4);
        $card_brand =  $response->DS_CARD_BRAND;
        $expiryDate = $response->DS_EXPIRYDATE;
        $card_hash = $response->DS_CARD_HASH;

        //$hash = hash('sha256', $IdUser . $TokenUser);
        $hash = $card_hash;

        if(!class_exists('VmModel'))require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'vmmodel.php');
        $order_model = VmModel::getModel('orders');
        $myorder = $order_model->getOrder($virtuemart_order_id);
        $user_id = $myorder['details']['BT']->virtuemart_user_id;

        $Date = JFactory::getDate();

        $TokenTableName = $this->plugin->getPaytpvTokenTableName();

        // Verify if exists card_hash
        if (!$this->getTokenData($user_id,$hash)){

			$q = 'INSERT INTO `' . $TokenTableName . '` (`virtuemart_user_id`, `hash`, `id_user`, `token_user`, `cc`, `brand`, `expiry`, `desc`, `date`) VALUES ("' . $user_id . '", "' . $hash . '", "' . $IdUser . '", "' . $TokenUser . '", "' . $card . '", "' . $card_brand . '", "' . $expiryDate . '","", "' . $Date . '")';

			$db = JFactory::getDBO();
			$db->setQuery($q);
			$db->execute();
			$err = $db->getErrorMsg();
			if (!empty($err)) {
				vmError('Database error: PAYTPV addCurstomerCard ' . $err);
			}
		}
	}


	public function getPaytpvCards(){
		$virtuemart_user_id = JFactory::getUser()->id;

		$TokenTableName = $this->plugin->getPaytpvTokenTableName();
        
        $q = 'SELECT * FROM ' . $TokenTableName . ' WHERE  `virtuemart_user_id` = ' . $virtuemart_user_id . ' order by date DESC';

        $db = JFactory::getDBO();
		$db->setQuery ($q);
		if (!($paytpvCards = $db->loadObjectList())) {
			// JError::raiseWarning(500, $db->getErrorMsg());
		}

		$arrCards = array();
		foreach ($paytpvCards as $key=>$card) {
			if (!empty($card->hash)) {
				$arrCards[$key]["hash"] = $card->hash;
				$arrCards[$key]["cc"] = $card->cc;
				$arrCards[$key]["brand"] = $card->brand;
				$arrCards[$key]["desc"] = $card->desc;
			}
		}
		return $arrCards;
    }


	public function getPaytpvCardsDropDown () {

		$storeCCs = $this->getPaytpvCards();
		if (empty($storeCCs)) {
			return null;
		}
		if (!class_exists('VmHTML')) {
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'html.php');
		}
		$selected_cc = 0;
		$attrs = 'class="inputbox vm-chzn-select"';
		$idA = $id = 'paytpv_card';
		$options[] = JHTML::_('select.option', 0, vmText::_('VMPAYMENT_PAYTPV_NEW_CARD'));
		
		foreach ($storeCCs as $storeCC) {
			$selected_cc = ($selected_cc==0)?$storeCC["hash"]:$selected_cc;
			$name = $storeCC["cc"] . " [". $storeCC["brand"] . "] " . (($storeCC["desc"])?$storeCC["desc"]:"");
			$options[] = JHTML::_('select.option', $storeCC["hash"], $name);
		}

		return JHTML::_('select.genericlist', $options, $idA, 'class="paytpvListCC inputbox vm-chzn-select" style="width: 350px;"', 'value', 'text', $selected_cc);
	}

	public function getTokenData($virtuemart_user_id, $paytpv_card_hash){

		$TokenTableName = $this->plugin->getPaytpvTokenTableName();
        
        $q = 'SELECT * FROM ' . $TokenTableName . ' WHERE  `virtuemart_user_id` = ' . $virtuemart_user_id . ' and hash = "' . $paytpv_card_hash . '"';

        $db = JFactory::getDBO();
		$db->setQuery ($q);
		if (!($paytpvCard = $db->loadObject())) {
			return false;
		}

        return $paytpvCard;

    }


	public function setTotalInPaymentCurrency ($total) {

		if (!class_exists('CurrencyDisplay')) {
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');
		}
		$this->total = vmPSPlugin::getAmountValueInCurrency($total, $this->_method->payment_currency) * 100;

		$cd = CurrencyDisplay::getInstance($this->cart->pricesCurrency);
	}

	public function getTotalInPaymentCurrency () {

		return $this->total;

	}

	public function setPaymentCurrency () {
		vmPSPlugin::getPaymentCurrency($this->_method);
		$this->currency_code_3 = shopFunctions::getCurrencyByID($this->_method->payment_currency, 'currency_code_3');
	}

	public function getPaymentCurrency () {
		return $this->currency_code_3;
	}

	public function setContext ($context) {
		$this->context = $context;
	}

	public function getContext () {
		return $this->context;
	}

	public function setCart ($cart, $doGetCartPrices = true) {
		$this->cart = $cart;
		if ($doGetCartPrices AND !isset($this->cart->cartPrices)) {
			$this->cart->getCartPrices();
		}
	}

	public function setOrder ($order) {
		$this->order = $order;
	}

	/**
	 * The digits from the first line of the address should be concatenated with the post code digits with a '|' in the middle.
	 * * For example: Flat 123, No. 7 Grove Park, E98 7QJ
	 * Billing Code: '987|123', the number of digits on each side of the '|' should also be restricted to 5.
	 * @param $address
	 */
	public function getCode ($address) {
		// get first digits of the address line,
		$digits_addr = $this->stripnonnumeric($address->address_1, 5);
		// get digits from zip,
		$digits_zip = $this->stripnonnumeric($address->zip, 5);
		// concatenate with |
		return $digits_zip . "|" . $digits_addr;
	}

	private function stripnonnumeric ($code, $maxLg) {
		$code = preg_replace("/[^0-9]/", "", $code);
		$code = substr($code, 0, $maxLg);
		return $code;
	}

	function _getPaytpvUrl () {
		return $this->_method->gateway_url;
	}

	
	/*********************/
	/* Log and Reporting */
	/*********************/
	public function debug ($subject, $title = '', $echo = true) {

		$debug = '<div style="display:block; margin-bottom:5px; border:1px solid red; padding:5px; text-align:left; font-size:10px;white-space:nowrap; overflow:scroll;">';
		$debug .= ($title) ? '<br /><strong>' . $title . ':</strong><br />' : '';
		//$debug .= '<pre>';
		if (is_array($subject)) {
			$debug .= str_replace("=>", "&#8658;", str_replace("Array", "<font color=\"red\"><b>Array</b></font>", nl2br(str_replace(" ", " &nbsp; ", print_r($subject, true)))));
		} else {
			$debug .= str_replace("=>", "&#8658;", str_replace("Array", "<font color=\"red\"><b>Array</b></font>", (str_replace(" ", " &nbsp; ", print_r($subject, true)))));

		}

		//$debug .= '</pre>';
		$debug .= '</div>';
		if ($echo) {
			echo $debug;
		} else {
			return $debug;
		}
	}

	function highlight ($string) {
		return '<span style="color:red;font-weight:bold">' . $string . '</span>';
	}

	public function debugLog ($message, $title = '', $type = 'message', $echo = false, $doVmDebug = false) {

		$this->plugin->debugLog($message, $title, $type, $doVmDebug);

	}

	


	public function validateConfirmedOrder ($enqueueMessage = true) {
		return true;
	}

	public function validateSelectCheckPayment ($enqueueMessage = true) {
		return true;
	}

	/**
	 * @param bool $enqueueMessage
	 * @return bool
	 */
	function validateCheckoutCheckDataPayment () {
		return true;
	}

	

	protected function getLang() {

        $language = JFactory::getLanguage();
        $tag = strtolower(substr($language->get('tag'), 0, 2));
        return $tag;
    }

	

	/**
	 * @param $message
	 */
	static $displayErrorDone = false;

	function displayError ($admin, $public = '') {
		if ($admin == NULL) {
			$admin = "an error occurred";
		}

		if (empty($public) AND $this->_method->debug) {
			$public = $admin;
		}
		vmError((string)$admin, (string)$public);
	}

	

	
}
