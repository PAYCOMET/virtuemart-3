<?php
/**
 *
 * PAYTPV payment plugin
 *
 * @author PAYTPV
 * @package VirtueMart
 * @subpackage payment
 */
defined('_JEXEC') or die();
vmJsApi::css( 'paytpv','plugins/vmpayment/paytpv/paytpv/assets/css/');

?>

<div class="paytpv response">
	<div class="paytpv_order_info">
		<span class="paytpv_auth_value"><?php echo vmText::_('VMPAYMENT_PAYTPV_ORDER_NUMBER'); ?>: <?php echo $viewData['order_number']; ?></span>
	</div>
	<div class="paytpv_amount_info">
		<span class="paytpv_auth_value"><?php echo vmText::_('VMPAYMENT_PAYTPV_AMOUNT'); ?>: <?php echo $viewData['order_amount']; ?></span>
	</div>

	<div class="paytpv_auth_info">
		<span class="paytpv_auth_value"><?php echo $viewData['auth_code']; ?></span>
	</div>
	
	<div class="paytpv_vieworder">
		<a class="vm-button-correct"
		   href="<?php echo JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $viewData["order_number"] . '&order_pass=' . $viewData["order_pass"], false) ?>"><?php echo vmText::_('VMPAYMENT_PAYTPV_ORDER_VIEW'); ?></a>
	</div>
</div>
