<?php
defined('_JEXEC') or die();

/**
 *
 * PAYTPV payment plugin
 *
 * @author PAYTPV
 * @package VirtueMart
 * @subpackage payment
 */

vmJsApi::jQuery();
vmJsApi::chosenDropDowns();
?>
<span class="vmpayment">
	<?php
	if (!empty($viewData['logo'])) {
		?>
		<span class="vmCartPaymentLogo">
			<?php echo $viewData['logo'] ?>
        </span>
	<?php
	}
	?>
	<span class="vmpayment_name"><?php echo $viewData['payment_name'] ?> </span>
	<?php
	if (!empty($viewData['payment_description'])) {
		?>
		<span class="vmpayment_description"><?php echo $viewData['payment_description'] ?> </span>
	<?php
	}
	?>
</span>



