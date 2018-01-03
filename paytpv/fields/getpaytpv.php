<?php

defined('JPATH_BASE') or die();


jimport('joomla.form.formfield');
class JFormFieldGetPaytpv extends JFormField {
	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	public $type = 'getpaytpv';

	protected function getInput() {
		$html = "";
		vmJsApi::addJScript( '/plugins/vmpayment/paytpv/paytpv/assets/js/admin.js');
		vmJsApi::css( 'admin','plugins/vmpayment/paytpv/paytpv/assets/css/');

		$url = "https://www.paytpv.com/es/alta-empresa?id=virtuemart";
		
		$html .= '<p><a target="_blank" href="' . $url . '" class="signin-button-link">' . vmText::_('VMPAYMENT_PAYTPV_REGISTER') . '</a>';
		$html .= ' <a target="_blank" href="http://developers.paytpv.com/es/modulos-de-pago/virtuemart2" class="signin-button-link">' . vmText::_('VMPAYMENT_PAYTPV_DOCUMENTATION') . '</a></p>';

		return $html;
	}
}