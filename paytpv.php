<?php

/**
 * @author PAYCOMET
 * @version $Id: PAYCOMET.php,v 2.0
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2019 PAYCOMET - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

defined('_JEXEC') or die('Restricted access');


if (!class_exists( 'VmConfig' )) require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart'.DS.'helpers'.DS.'config.php');

if (!class_exists('vmPSPlugin')) {
    require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

if (!class_exists('PaytpvHelperPaytpv')) {
    require(VMPATH_ROOT . DS.'plugins'. DS.'vmpayment'. DS.'paytpv'. DS.'paytpv'. DS.'helpers'. DS.'helper.php');
}



class plgVmpaymentPaytpv extends vmPSPlugin {

    function __construct(& $subject, $config) {

        parent::__construct($subject, $config);

        $this->_loggable = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush();

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        
    }
      

    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment PAYTPV Table');
    }

    /**
     * Fields to create the payment table
     * @return string SQL Fileds
     */
    function getTableSQLFields() {
        $SQLfields = array(
            'id'                           => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'          => 'int(11) UNSIGNED',
            'order_number'                 => 'char(64)',
            'virtuemart_paymentmethod_id'  => 'mediumint(1) UNSIGNED',
            'payment_name'                 => 'varchar(5000)',
            'payment_order_total'          => 'decimal(15,5) NOT NULL',
            'payment_currency'             => 'smallint(1)',
            'cost_per_transaction'         => 'decimal(10,2)',
            'cost_percent_total'           => 'decimal(10,2)',
            'tax_id'                       => 'smallint(1)',
            'paytpv_api'                   => 'varchar(255)',
            'TransactionType'              => 'smallint(2)',
            'TransactionName'              => 'char(32)',
            'CardCountry'                  => 'char(32)',
            'BankDateTime'                 => 'char(32)',
            'Order'                        => 'char(255)',
            'ErrorID'                      => 'int(11)',
            'ErrorDescription'             => 'char(255)',
            'AuthCode'                     => 'char(255)',
            'Currency'                     => 'char(3)',
            'Amount'                       => 'int(11)',
            'AmountEur'                    => 'int(11)',
            'Language'                     => 'char(32)',
            'AccountCode'                  => 'char(32)',
            'TpvID'                        => 'int(11)',
            'SecurePayment'                => 'smallint(1)',
            'post_raw'                     => 'text',
            'IdUser'                       => 'int(11)',
            'TokenUser'                    => 'char(64)',
            'SaveCard'                     => 'smallint(1)',
        );
        return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order) {
        
        if (!($this->_currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return FALSE;
        }

        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }
        if (!class_exists('VirtueMartModelCurrency')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
        }
        //$this->setInConfirmOrder($cart);
        $email_currency = $this->getEmailCurrency($this->_currentMethod);

        $payment_name = $this->renderPluginName($this->_currentMethod, 'order');

        $paytpvInterface = $this->_loadPaytpvInterface();

        $paytpvInterface->debugLog('order number: ' . $order['details']['BT']->order_number, 'plgVmConfirmedOrder', 'debug');
        $paytpvInterface->setCart($cart);

        $paytpvInterface->setOrder($order);
        $paytpvInterface->setPaymentCurrency();
        $paytpvInterface->setTotalInPaymentCurrency($order['details']['BT']->order_total);

        $session = JFactory::getSession();
        $return_context = $session->getId();

        $paymentCurrency = CurrencyDisplay::getInstance($this->_currentMethod->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($this->_currentMethod->payment_currency, $order['details']['BT']->order_total, false) * 100);

        // Prepare data that should be stored in the database
        $dbValues = array();
        $dbValues['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $payment_name;
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['return_context'] = $return_context;
        $dbValues['cost_per_transaction'] = $this->_currentMethod->cost_per_transaction;
        $dbValues['cost_percent_total'] = $this->_currentMethod->cost_percent_total;
        $dbValues['payment_currency'] = $this->_currentMethod->payment_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency / 100;
        $dbValues['tax_id'] = $this->_currentMethod->tax_id;
        $dbValues['paytpv_api'] = $paytpvInterface->getContext();

        // Save Card for future purchase
        $saveCard = 1;

        if ($order['details']['BT']->virtuemart_user_id==0 || $this->_currentMethod->disableoffersavecard==1 || $this->_currentMethod->remembercardunselected==1) {
            $saveCard = 0;
        }

        $dbValues['SaveCard'] = $saveCard;

        $this->storePSPluginInternalData($dbValues);

        $remoteCCFormParams =$paytpvInterface->getRemoteCCFormParams();
       
        $html = $this->renderByLayout('remote_cc_form', $remoteCCFormParams);
        vRequest::setVar('html', $html);
        vRequest::setVar('display_title', false);

        return true;

    }


    function renderPluginName ($method, $where = 'checkout') {

        $display_logos = "";
        
        $this->_currentMethod = $method;
        $paytpvInterface = $this->_loadPaytpvInterface();


        if ($paytpvInterface == NULL) {
            vmdebug('renderPluginName', $method);
            return;
        }      

        $logos = $method->payment_logos;
        if (!empty($logos)) {
            $display_logos = $this->displayLogos($logos) . ' ';
        }
        $payment_name = $method->payment_name;

        

        $html = $this->renderByLayout('render_pluginname', array(
                                                                'where'                       => $where,
                                                                'virtuemart_paymentmethod_id' => $method->virtuemart_paymentmethod_id,
                                                                'logo'                        => $display_logos,
                                                                'payment_name'                => $payment_name,
                                                                'payment_description'         => $method->payment_desc,
                                                           ));
        $html = $this->rmspace($html);

        return $html;
    }

    private function rmspace ($buffer) {
        return preg_replace('~>\s*\n\s*<~', '><', $buffer);
    }



    /*********************/
    /* Private functions */
    /*********************/
    private function _loadPaytpvInterface () {

        if (!class_exists('PaytpvHelperPaytpv')) {
            require(VMPATH_ROOT .  DS  .'plugins'. DS  .'vmpayment'. DS  .'paytpv'. DS  .'paytpv'. DS  .'helpers'. DS  .'helper.php');
        }
        $paytpvInterface = new PaytpvHelperPaytpv($this->_currentMethod, $this);
   
        return $paytpvInterface;
    }

    

    protected function getLang() {

        $language = JFactory::getLanguage();
        $tag = strtolower(substr($language->get('tag'), 0, 2));
        return $tag;
    }

    

    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices) {

        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
                OR
                ($method->min_amount <= $amount AND ($method->max_amount == 0) ));
        if (!$amount_cond) {
            return false;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
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

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
        $this->createPaytpvTokenTable();

        return parent::onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * @return string
     */
    function getPaytpvTokenTableName () {
        return $this->_tablename . '_token';
    }

    function getPaytpvTableName(){
        return $this->_tablename;
    }
    
    /**
     * Fields to create the payment table
     * @return string SQL Fileds
     */
    private function getPaytpvTokenTableSQLFields () {
        // We must save both , since the customer number can be changed
        $SQLfields = array(
            'token_id'                 => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_user_id'       => 'int(11) UNSIGNED',
            'hash'                     => 'varchar(64)',
            'id_user'                  => 'int(11)',
            'token_user'               => 'varchar(64)',
            'cc'                       => 'varchar(32)',
            'brand'                    => 'varchar(32)',
            'expiry'                   => 'varchar(7)',
            'desc'                     => 'varchar(64)',
            'date'                     => 'DATETIME');
        return $SQLfields;
    }

    /**
     * @param $tableComment
     * @return string
     */
    private function createPaytpvTokenTable ($tablesFields = 0) {
        $payerRefTableName = $this->getPaytpvTokenTableName();
        $query = "CREATE TABLE IF NOT EXISTS `" . $payerRefTableName . "` (";

        $SQLfields = $this->getPaytpvTokenTableSQLFields();
        
        foreach ($SQLfields as $fieldname => $fieldtype) {
            $query .= '`' . $fieldname . '` ' . $fieldtype . " , ";
        }
        
        $query .= "       PRIMARY KEY (`token_id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='PAYTPV Token' AUTO_INCREMENT=1 ;";


        $db = JFactory::getDBO();
        $db->setQuery($query);
        if (!$db->execute()) {
            JError::raiseWarning(1, $payerRefTableName . '::createPaytpvTokenTable: ' . vmText::_('COM_VIRTUEMART_SQL_ERROR') . ' ' . $db->stderr(TRUE));
            echo $payerRefTableName . '::createPaytpvTokenTable: ' . vmText::_('COM_VIRTUEMART_SQL_ERROR') . ' ' . $db->stderr(TRUE);
        }

    }


    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
        return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     *
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     *
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */

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

    // Payment OK
    function plgVmOnPaymentResponseReceived(&$html){
        vmLanguage::loadJLang('com_virtuemart_orders', TRUE);

        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);

        $vendorId = 0;
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        
        if (!class_exists('shopFunctionsF')) {
            require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        
        
        $order_number = JRequest::getVar('on');

        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
        $payment_name = $this->renderPluginName($method);
        $payment_data = $this->getDataByOrderId($virtuemart_order_id);

        if(!class_exists('VmModel'))require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'vmmodel.php');
        $order_model = VmModel::getModel('orders');
        $myorder = $order_model->getOrder($virtuemart_order_id);


        vmdebug('plgVmOnPaymentResponseReceived', $payment_data);

        if (!class_exists('CurrencyDisplay'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
        $currency = CurrencyDisplay::getInstance();
        $amount_currency = $currency->priceDisplay($payment_data->payment_order_total);
        $auth_code = $payment_data->AuthCode;
        
        vmLanguage::loadJLang('com_virtuemart');

        $params = array();
        $params["order_number"] = $order_number;
        $params["order_amount"] = $amount_currency;
        $params["order_pass"]   = $myorder['details']['BT']->order_pass;
        $params["auth_code"]    = $auth_code;
     
        $html = $this->renderByLayout('response',$params);

        if (!class_exists('VirtueMartCart')) {
            require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        
        // get the correct cart / session
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        
        return true;
    }


    // Payment KO
    public function plgVmOnUserPaymentCancel() {
        $virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);

        if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return NULL;
        }
        JFactory::getApplication()->enqueueMessage(vmText::_('VMPAYMENT_PAYTPV_ERROR_TRY_AGAIN'));
    }

    
    // Payment Notification
    function plgVmOnPaymentNotification(){

        $lang = JFactory::getLanguage();
        $filename = 'plg_vmpayment_paytpv';
        $lang->load($filename, JPATH_ADMINISTRATOR);

        $paytpv_data = JRequest::get();

        if (isset($paytpv_data["notificationTask"]) && $paytpv_data["notificationTask"]=="handleCapture"){
            $this->PaymentCapture();
            return;
        }

        if (isset($paytpv_data["notificationTask"]) && $paytpv_data["notificationTask"]=="removeCard"){
            $this->removeCard();
            return;
        }

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        if (isset($paytpv_data['Order']))
            $order_number = $paytpv_data['Order'];

        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
        $payment_data = $this->getDataByOrderId($virtuemart_order_id);

        $this->debugLog('plgVmOnPaymentNotification: virtuemart_order_id  found ' . $virtuemart_order_id, 'message');

        if (!$virtuemart_order_id) {
            $this->_debug = true; // force debug here
            $this->debugLog('plgVmOnPaymentNotification: virtuemart_order_id not found ', 'ERROR');
            // send an email to admin, and ofc not update the order status: exit  is fine
            $this->sendEmailToVendorAndAdmins(JText::_('VMPAYMENT_PAYTPV_ERROR_EMAIL_SUBJECT'), JText::_('VMPAYMENT_PAYTPV_UNKNOW_ORDER_ID'));
            exit;
        }
        $vendorId = 0;
        $payment = $this->getDataByOrderId($virtuemart_order_id);

        $method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->_debug = $method->debug;
        if (!$payment) {
            $this->debugLog('getDataByOrderId payment not found: exit ', 'ERROR');
            return null;
        }
        $this->debugLog('paytpv_data ' . serialize($paytpv_data), 'message');

        $_firma = md5($method->clientcode.$method->terminal.$paytpv_data["TransactionType"].$order_number.$paytpv_data["Amount"].$paytpv_data["Currency"].md5($method->password).$paytpv_data["BankDateTime"].$paytpv_data["Response"]);

        if ($paytpv_data['ExtendedSignature'] != $_firma) {
            $this->_debug = true; // force debug here
            $this->debugLog('plgVmOnPaymentNotification: virtuemart_order_id not found ', 'ERROR');
            // send an email to admin, and ofc not update the order status: exit  is fine
            $this->sendEmailToVendorAndAdmins(JText::_('VMPAYMENT_PAYTPV_ERROR_EMAIL_SUBJECT'), sprintf(JText::_('VMPAYMENT_PAYTPV_ILEGAL_ACCESS'), JRequest::getVar('REMOTE_ADDR', null, 'server')));
            echo "Error Firma";
            exit;
        }
        $new_status = $method->status_pending;
        $comments = '';

        $result = $paytpv_data['Response']=='OK'?0:-1;

        if ((int) $result!=0) {
            //Transacci&oacute;n denegada
            $new_status = $method->status_canceled;
            $comments = JText::sprintf('VMPAYMENT_PAYTPV_PAYMENT_CANCELED', $order_number);
            
        } else {
            $new_status = $method->status_success;
            $comments = JText::sprintf('VMPAYMENT_PAYTPV_PAYMENT_CONFIRMED', $order_number);
        }

        // Refund
        if ($paytpv_data['TransactionType']==2){
            $new_status = $method->status_rebate;

            if (!class_exists('CurrencyDisplay'))
                require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
            $currency = CurrencyDisplay::getInstance();
            $amount_currency = $currency->priceDisplay($paytpv_data['AmountEur']);

            $order_model = VmModel::getModel('orders');
            $myorder = $order_model->getOrder($virtuemart_order_id);
            $totalInPaymentCurrency = vmPSPlugin::getAmountValueInCurrency($myorder['details']['BT']->order_total, $method->payment_currency) * 100;

            if ($totalInPaymentCurrency==$paytpv_data['Amount'])
                $new_status = $method->status_rebate;
            else
                $new_status = $myorder['details']['BT']->order_status;

            $modelOrder = new VirtueMartModelOrders();
            
            $comments = vmText::sprintf('VMPAYMENT_PAYTPV_PAYMENT_REFUND', $amount_currency);
            $order = array();
            $order['order_status'] = $new_status;
            $order['virtuemart_order_id'] = $virtuemart_order_id;
            $order['customer_notified'] = false;
            $order['comments'] = $comments;
            $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

            echo $comments;
            exit;
        }

        
        $response_fields = array();
        $response_fields['payment_name'] = $this->renderPluginName($method);
        $response_fields['virtuemart_paymentmethod_id'] = $payment_data->virtuemart_paymentmethod_id;
        $response_fields['order_number'] = $payment_data->order_number;
        $response_fields['cost_per_transaction'] = $payment_data->cost_per_transaction;
        $response_fields['cost_percent_total'] = $payment_data->cost_percent_total;
        $response_fields['payment_currency'] = $payment_data->payment_currency;
        $response_fields['payment_order_total'] = $payment_data->payment_order_total;
        $response_fields['paytpv_api'] = $payment_data->paytpv_api;
        $response_fields['SaveCard'] = $payment_data->SaveCard;
        $response_fields['tax_id'] = $payment_data->tax_id;

        $response_fields['virtuemart_order_id'] = $virtuemart_order_id;
        $response_fields['post_raw'] = serialize($paytpv_data);
        $response_fields['TransactionType'] = $paytpv_data['TransactionType'];
        $response_fields['TransactionName'] = $paytpv_data['TransactionName'];
        $response_fields['CardCountry'] = $paytpv_data['CardCountry'];
        $response_fields['BankDateTime'] = $paytpv_data['BankDateTime'];
        $response_fields['Order'] = $paytpv_data['Order'];
        $response_fields['ErrorID'] = $paytpv_data['ErrorID'];
        $response_fields['ErrorDescription'] = $paytpv_data['ErrorDescription'];
        $response_fields['AuthCode'] = $paytpv_data['AuthCode'];        
        $response_fields['Currency'] = $paytpv_data['Currency'];        
        $response_fields['Amount'] = $paytpv_data['Amount'];        
        $response_fields['AmountEur'] = $paytpv_data['AmountEur'];        
        $response_fields['Language'] = $paytpv_data['Language'];        
        $response_fields['AccountCode'] = $paytpv_data['AccountCode'];
        $response_fields['TpvID'] = $paytpv_data['TpvID'];
        $response_fields['SecurePayment'] = $paytpv_data['SecurePayment'];

        if (isset($paytpv_data['IdUser']) && isset($paytpv_data['TokenUser'])){
            $response_fields['IdUser'] = $paytpv_data['IdUser'];
            $response_fields['TokenUser'] = $paytpv_data['TokenUser'];
        }
        
        
        $this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);
        $this->debugLog('process PAYTPV notificación OK, status', 'message');

        if ($virtuemart_order_id) {
            // send the email only if payment has been accepted
            if (!class_exists('VirtueMartModelOrders'))
                require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
            $modelOrder = new VirtueMartModelOrders();
            $order = array();
            $order['order_status'] = $new_status;
            $order['virtuemart_order_id'] = $virtuemart_order_id;
            $order['customer_notified'] = 1;
            $order['comments'] = $comments;
            $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

            // Si hay que almacenar la tarjeta
            if ($payment_data->SaveCard==1){
                if (isset($paytpv_data['IdUser']) && isset($paytpv_data['TokenUser'])){
                    $paytpvInterface = $this->_loadPaytpvInterface();
                    $IdUser = $paytpv_data["IdUser"];
                    $TokenUser = $paytpv_data["TokenUser"];
                    $paytpvInterface->saveCard($virtuemart_order_id,$IdUser,$TokenUser);                  
                }
            }
            
            // remove vmcart
            
            if (isset($payment_data->paytpv_api)) {
                
                $res = $this->emptyCart($payment_data->paytpv_api, $order_number);
                
            }
        }
        echo $comments;
        exit;
    }

    function removeCard(){
        $lang = JFactory::getLanguage();
        $filename = 'plg_vmpayment_paytpv';
        $lang->load($filename, JPATH_ADMINISTRATOR);

        $paytpv_data = JRequest::get();

        if (!($this->_currentMethod = $this->getVmPluginMethod($paytpv_data["virtuemart_paymentmethod_id"]))) {
            return NULL;
        }

        $paytpvInterface = $this->_loadPaytpvInterface();

        $resp = $paytpvInterface->removeCard($paytpv_data["paytpv_card"]);

        print ($resp)?"ok":"ko";
        exit;

    }


    // Payment Capture
    function PaymentCapture(){

        $lang = JFactory::getLanguage();
        $filename = 'plg_vmpayment_paytpv';
        $lang->load($filename, JPATH_ADMINISTRATOR);

        $paytpv_data = JRequest::get();

        $paytpv_card_hash = $paytpv_data["paytpv_card"];
        $order_number = $paytpv_data["order_number"];

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
        

        $this->debugLog('PaymentCapture: virtuemart_order_id  found ' . $virtuemart_order_id, 'message');

        if (!$virtuemart_order_id) {
            $this->_debug = true; // force debug here
            $this->debugLog('PaymentCapture: virtuemart_order_id not found ', 'ERROR');
            // send an email to admin, and ofc not update the order status: exit  is fine
            $this->sendEmailToVendorAndAdmins(JText::_('VMPAYMENT_PAYTPV_ERROR_EMAIL_SUBJECT'), JText::_('VMPAYMENT_PAYTPV_UNKNOW_ORDER_ID'));
            exit;
        }
        $vendorId = 0;
        $payment = $this->getDataByOrderId($virtuemart_order_id);

        if (!($this->_currentMethod = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id))) {
            return NULL;
        }

        $this->_debug = $this->_currentMethod->debug;
        if (!$payment) {
            $this->debugLog('getDataByOrderId payment not found: exit ', 'ERROR');
            return null;
        }
        $this->debugLog('paytpv_data ' . serialize($paytpv_data), 'message');

        if(!class_exists('VmModel'))require(JPATH_VM_ADMINISTRATOR.DS.'helpers'.DS.'vmmodel.php');
        $order_model = VmModel::getModel('orders');
        $myorder = $order_model->getOrder($virtuemart_order_id);

        $cart = VirtueMartCart::getCart();

        $paytpvInterface = $this->_loadPaytpvInterface();
        $paytpvInterface->debugLog('order number: ' . $order_number, 'PaymentCapture', 'debug');
        
        $paytpvInterface->setOrder($myorder);
        $paytpvInterface->setCart($cart);

        $paytpvInterface->setPaymentCurrency();
        $paytpvInterface->setTotalInPaymentCurrency($myorder['details']['BT']->order_total);

        
        $totalInPaymentCurrency = vmPSPlugin::getAmountValueInCurrency($myorder['details']['BT']->order_total, $this->_currentMethod->payment_currency) * 100;

        $dsecure = $paytpvInterface->isSecureTransaction($totalInPaymentCurrency,0)?1:0;


        $user_id = $myorder['details']['BT']->virtuemart_user_id;
        $card_data = $paytpvInterface->getTokenData($user_id,$paytpv_card_hash);
        $IdUser = $card_data->id_user;
        $TokenUser = $card_data->token_user;

        // Execute_Purchase_Token -> Secure
        if ($dsecure){

            $url = $paytpvInterface->getExecutePurchaseTokenUrl($IdUser,$TokenUser,$dsecure);
            $app = JFactory::getApplication();
            $app->redirect($url, '');


        // Execute_Purchase
        }else{
            
            if (!class_exists('Paytpv_Bankstore')) {
                require(VMPATH_ROOT . DS.'plugins'. DS.'vmpayment'. DS.'paytpv'. DS.'paytpv_bankstore.php');
            }

            $paytpv = new Paytpv_Bankstore($this->_currentMethod->clientcode,$this->_currentMethod->terminal, $this->_currentMethod->password, "");
            
            $currency = $paytpvInterface->getPaymentCurrency();
            $resp = $paytpv->ExecutePurchase($IdUser,$TokenUser,$totalInPaymentCurrency,$order_number,$currency,"","",null,null,null);
            $msg = '';

            if ('' == $resp->DS_ERROR_ID || 0 == $resp->DS_ERROR_ID) {
                $url = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&tmpl=component&on=' . $order_number . '&pm=' . $myorder['details']['BT']->virtuemart_paymentmethod_id);
                
                // Save IDUser y Token to Order
                $payment_data = $this->getDataByOrderId($virtuemart_order_id);

                foreach ($payment_data as $key => $value) {
                    $response_fields[$key] = $value;
                }
                $response_fields['IdUser'] = $IdUser;
                $response_fields['TokenUser'] = $TokenUser;

                $this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);

            }else{
                $url = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&tmpl=component&on=' . $order_number . '&pm=' . $myorder['details']['BT']->virtuemart_paymentmethod_id);
                $msg = vmText::_('VMPAYMENT_PAYTPV_ERROR_TRY_AGAIN');
                $paytpvInterface->debugLog('order number: ' . $order_number . ", Error: " . $resp->DS_ERROR_ID, 'PaymentCapture', 'debug');
            }

            $app = JFactory::getApplication();
            $app->redirect($url, $msg);
        }
    }


    /**
     * Display stored payment data for an order
     *
     * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id){

        if(!$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
            return NULL; // Another method was selected, do nothing
        }
        if(!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }

        $payments = $this->getDatasByOrderId($virtuemart_order_id);
        $html = '<table class="adminlist table"  >' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->showActionOrderBEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, $payments);
        //$html .= $this->showOrderBEPayment($virtuemart_order_id, $payments);
        $html .= '</table>' . "\n";

        return $html;
    }


    private function showActionOrderBEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payments) {
        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($virtuemart_order_id);
        $options = array();

        $payment = $this->getDataByOrderId($virtuemart_order_id);

        $method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);

        $arr_StatusRefund = array($method->status_pending,$method->status_rebate,$method->status_canceled);
       
        if (in_array($order['details']['BT']->order_status,$arr_StatusRefund))
            return "";
        
        $options[] = JHTML::_('select.option', 'rebatePayment', vmText::_('VMPAYMENT_PAYTPV_API_ORDER_BE_REBATE'), 'value', 'text');
        $actionList = JHTML::_('select.genericlist', $options, 'action', '', 'value', 'text', 'capturePayment', 'action', true);


        $html = '<table class="adminlist table"  >' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= '<form action="index.php" method="post" name="updateOrderBEPayment" id="updateOrderBEPayment">';

        $html .= '<tr ><td >';
        $html .= $actionList;
        $html .= ' </td><td>';
        $html .= '<input type="text" id="amount" name="amount" size="20" value="" class="required" maxlength="25"  placeholder="' . vmText::sprintf('VMPAYMENT_PAYTPV_API_ORDER_BE_AMOUNT', shopFunctions::getCurrencyByID($payments[0]->payment_currency, 'currency_code_3')) . '"/>';
        $html .= '<input type="hidden" name="type" value="vmpayment"/>';
        $html .= '<input type="hidden" name="name" value="paytpv"/>';
        $html .= '<input type="hidden" name="view" value="plugin"/>';
        $html .= '<input type="hidden" name="option" value="com_virtuemart"/>';
        $html .= '<input type="hidden" name="virtuemart_order_id" value="' . $virtuemart_order_id . '"/>';
        $html .= '<input type="hidden" name="virtuemart_paymentmethod_id" value="' . $virtuemart_paymentmethod_id . '"/>';

        $html .= '<a class="updateOrderBEPayment btn btn-small" href="#"   >' . vmText::_('VMPAYMENT_PAYTPV_API_ORDER_BE_REBATE') . '</a>';
        $html .= '</form>';
        $html .= ' </td></tr>';

        vmJsApi::addJScript('paytpv.updateOrderBEPayment',"
                jQuery(document).ready( function($) {
                    jQuery('.updateOrderBEPayment').click(function() {
                        if (confirm('".vmText::_('VMPAYMENT_PAYTPV_PAYMENT_SURE_REFUND')."')){
                        document.updateOrderBEPayment.submit();
                        return false;
                        }

            });
        });
        ");

        //$html .= '</table>'  ;
        return $html;

    }


    function plgVmOnSelfCallBE ($type, $name, &$render) {
        if ($name != $this->_name || $type != 'vmpayment') {
            return FALSE;
        }

        $virtuemart_paymentmethod_id = vRequest::getInt('virtuemart_paymentmethod_id');
        //Load the method
        if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }


        $amount = vRequest::get('amount');

        $amount = str_replace(",",".",$amount);

        $actions=array('rebatePayment');
        $action = vRequest::getCmd('action');
        if (!in_array($action, $actions)) {
            vmError('VMPAYMENT_PAYTPV_API_UPDATEPAYMENT_UNKNOWN_ACTION');
            return NULL;
        }
        $virtuemart_order_id = vRequest::getInt('virtuemart_order_id');
        if (!($payment_data = $this->getDataByOrderId($virtuemart_order_id))) {
            return null;
        }

        $orderModel = VmModel::getModel('orders');
        $orderData = $orderModel->getOrder(vRequest::getInt('virtuemart_order_id'));
        $requestSent = false;
        $order_history_comment = '';
        $paytpvInterface = $this->_loadPaytpvInterface();
        $paytpvInterface->setPaymentCurrency();
        $canDo = true;

        $totalOrder = vmPSPlugin::getAmountValueInCurrency($orderData['details']['BT']->order_total, $this->_currentMethod->payment_currency) * 100;
        $amount_ref = vmPSPlugin::getAmountValueInCurrency($amount, $this->_currentMethod->payment_currency);
        
        if ( $action=='rebatePayment') {
            $requestSent = true;
            $response = $this->doRebate($paytpvInterface, $orderData, $payment_data, $amount);

            $msg = '';
            if ('' == $response->DS_RESPONSE || 0 == $response->DS_RESPONSE) {
                $error = "Error: " . $response->DS_ERROR_ID;
                $paytpvInterface->displayError($error);
                $order_history_comment = vmText::_('VMPAYMENT_PAYTPV_API_UPDATE_STATUS_REBATE_ERROR');
            }
            
        }

        $app = JFactory::getApplication();
        $link = 'index.php?option=com_virtuemart&view=orders&task=edit&virtuemart_order_id=' . $virtuemart_order_id;

        $app->redirect(JRoute::_($link, FALSE));

    }


    /**
     * Merchants can Rebate for any amount up to 115% of the original order value.
     * Pop up will ask for the amount
     * @param $paytpv
     * @param $orderData
     * @param $payments
     */
    function doRebate ($paytpvInterface, $orderData, $payment_data, $amount=null) {

        if (!class_exists('Paytpv_Bankstore')) {
            require(VMPATH_ROOT . DS.'plugins'. DS.'vmpayment'. DS.'paytpv'. DS.'paytpv_bankstore.php');
        }

        if ($amount){
            $amount = vmPSPlugin::getAmountValueInCurrency($amount, $this->_currentMethod->payment_currency) * 100;
        }
     
        $paytpv = new Paytpv_Bankstore($this->_currentMethod->clientcode,$this->_currentMethod->terminal, $this->_currentMethod->password, "");
       
        $currency = $paytpvInterface->getPaymentCurrency();


        $response = $paytpv->ExecuteRefund($payment_data->IdUser,$payment_data->TokenUser,$payment_data->order_number,$currency,$payment_data->AuthCode,$amount,"");

        return $response;
        /*
        $msg = '';
        if ('' == $response['DS_RESPONSE'] || 0 == $response['DS_RESPONSE']) {
            $order_history_comment = vmText::_('VMPAYMENT_PAYTPV_API_UPDATE_STATUS_REBATE_ERROR');
        }

        $order_history_comment = vmText::_('VMPAYMENT_PAYTPV_API_UPDATE_STATUS_REBATE');
        $paytpvInterface->setOrder($orderData);
        $paytpvInterface->setPaymentCurrency();
        if ($amount===false) {
            $amount=$orderData['details']['BT']->order_total;
        }
        $paytpvInterface->setTotalInPaymentCurrency($amount);
        
        return $response;
        */
    }


    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$methodCounter = 0) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

   
    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     */
    public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);

        return true;
    }

    

    /**
     * This is for checking the input data of the payment method within the checkout
     *
     */
    public function plgVmOnCheckoutCheckDataPayment (VirtueMartCart $cart) {

        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            return NULL; // Another method was selected, do nothing
        }
        if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return NULL;
        }

        return true;
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     *
     */
    public function plgVmonShowOrderPrintPayment($order_number, $method_id) {
        return $this->onShowOrderPrint($order_number, $method_id);
    }



    

    /**
     * Save updated order data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.


    public function plgVmOnUpdateOrderPayment(  $_formData) {
    return null;
    }
     */
    /**
     * Save updated orderline data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.


    public function plgVmOnUpdateOrderLine(  $_formData) {
    return null;
    }
     */

    /**
     * plgVmOnEditOrderLineBE
     * This method is fired when editing the order line details in the backend.
     * It can be used to add line specific package codes
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise


    public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
    return null;
    }
     */

    /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise

    public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
    return null;
    }
     */

    public function plgVmDeclarePluginParamsPaymentVM3( &$data) {
        return $this->declarePluginParams('payment', $data);
    }

    public function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

        return $this->setOnTablePluginParams($name, $id, $table);
    }
    
}

// No closing tag
