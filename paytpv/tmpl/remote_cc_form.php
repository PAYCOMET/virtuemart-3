<?php
/**
 *
 * PAYTPV payment plugin
 *
 * @author Valerie Isaksen
 * @version $Id: remote_cc_form.php 9420 2017-01-12 09:35:36Z Milbo $
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
defined('_JEXEC') or die();


$img_src = JURI::root(TRUE) . '/plugins/vmpayment/paytpv/paytpv/assets/img/';

//$ccData = $viewData['ccData'];

JHTML::_('behavior.tooltip');
JHTML::script('vmcreditcard.js', 'components/com_virtuemart/assets/js/', false);
vmLanguage::loadJLang('com_virtuemart', true);
vmJsApi::jCreditCard();
vmJsApi::jQuery();
vmJsApi::chosenDropDowns();
vmJsApi::addJScript( '/plugins/vmpayment/paytpv/paytpv/assets/js/site.js');
vmJsApi::css( 'paytpv','plugins/vmpayment/paytpv/paytpv/assets/css/');

vmJsApi::addJScript ('vmPaytpvSumit',"

	jQuery(document).ready(function($) {
		jQuery(this).vm2front('stopVmLoading');
		jQuery('#checkoutPaytpvFormSubmitButton').bind('click dblclick', function(e){
		jQuery(this).vm2front('startVmLoading');
		e.preventDefault();
	    jQuery(this).attr('disabled', 'true');
	    jQuery(this).removeClass( 'vm-button-correct' );
	    jQuery(this).addClass( 'vm-button' );
	    jQuery('#checkoutPaytpvFormSubmit').submit();
	});

	});

");

?>
<div class="paytpv remote_cc_form" id="remote_cc_form">

	<h3 class="order_amount"><?php echo $viewData['order_amount']; ?></h3>

	<div class="cc_form_payment_name">
		<?php
		echo $viewData['payment_name'];
		?>
	</div>

	<form method="post" action="<?php echo $viewData['submit_url'] ?>" id="checkoutPaytpvFormSubmit">
	<?php { ?>
		
		<div class="vmpayment_cardinfo" id="vmpayment_cardinfo">
			<div class="vmpayment_cardinfo_text">
				<?php echo vmText::_('VMPAYMENT_PAYTPV_CARD');?>
			</div>
			<div class="creditcardsDropDown"  style="display:none;">
				<?php if (!empty($viewData['paytpv_cards'])){
					echo $viewData['paytpv_cards'];
				} 
				?>
			</div>
		</div>
		<?php
	}
	?>
		<div class="card_payment_button details-button">
			<span class="addtocart-button">
			<input type="submit" class="offer_btn addtocart-button" value="<?php echo $viewData['card_payment_button'] ?>" id="checkoutPaytpvFormSubmitButton"/>
			<input type="button" class="offer_btn addtocart-button" style="background-color:#ff0000; border: solid #ff0000 1px;" value="<?php echo $viewData['card_remove_button'] ?>" id="checkoutPaytpvFormSubmitButtonRemove"/>
			<input type="hidden" name="option" value="com_virtuemart"/>
			<input type="hidden" name="view" value="pluginresponse"/>
			<input type="hidden" name="task" value="pluginnotification"/>
			<input type="hidden" name="notificationTask" id="notificationTask" value="<?php echo $viewData['notificationTask']; ?>"/>
			<input type="hidden" name="order_number" value="<?php echo $viewData['order_number']; ?>"/>
			<input type="hidden" name="pm" value="<?php echo $viewData['virtuemart_paymentmethod_id']; ?>"/>
			<input type="hidden" name="virtuemart_paymentmethod_id" value="<?php echo $viewData['virtuemart_paymentmethod_id']; ?>"/>
			<input type="hidden" id="txt_remove_card" value="<?php echo vmText::_('VMPAYMENT_PAYTPV_REMOVE_CARD_SURE') ?>"/>

			</span>

		</div>
	</div>
	</form>
	<div class="payment_module paytpv_iframe" style="display:none">
		<iframe src="<?php echo $viewData['url'];?>"name="paytpv" style="width: 670px; border-top-width: 0px; border-right-width: 0px; border-bottom-width: 0px; border-left-width: 0px; border-style: initial; border-color: initial; border-image: initial; height: 342px; " marginheight="0" marginwidth="0" scrolling="no"></iframe>


	<?php if ($viewData['offer_save_card'] && $viewData['user_id']>0){

		if($viewData['remembercardunselected']) {
			$checked = 'checked="checked"';
		} else {
			$checked = '';
		}
		?>

		<div class="offer_save_card">
			<div id="save_card_tip"><?php echo vmText::_('VMPAYMENT_PAYTPV_SAVE_CARD_DETAILS_TIP') ?></div>
			<label for="save_card">
				<input id="save_card" name="save_card" type="checkbox" value="1" <?php echo $checked ?>><span
					class="save_card"> <?php echo vmText::_('VMPAYMENT_PAYTPV_SAVE_CARD_DETAILS') ?></span> </label>
		</div>
	<?php
	}
	?>
	</div>


</div>


<div class="footer_paytpv">
  <div class="paytpv_wrapper mobile">
    <div class="footer_line">
      <div class="footer_logo">
        <a href="https://secure.paytpv.com/" target="_blank">
          <img alt="PATYPV" src="<?php print $img_src?>paytpv_logo.svg">
        </a>
      </div>
      <ul class="payment_icons">
        <li><img alt="Veryfied by Visa" src="<?php print $img_src?>veryfied_by_visa.png"></li>
        <li><img alt="Mastercard Secure code" src="<?php print $img_src?>mastercard_secure_code.png"></li>
        <li><img alt="PCI" src="<?php print $img_src?>pci.png"></li>
        <li><img alt="Thawte" src="<?php print $img_src?>thawte.png"></li>
      </ul>
    </div>
  </div>
</div>
