/**
 *
 * Paytpv payment plugin
 *
 * @author Valerie Isaksen
 * @version $Id: site.js 8200 2014-08-14 11:09:44Z alatak $
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
jQuery().ready(function ($) {

    /************/
    /* Handlers */
    /************/
    handlePaytpvRemoteCCForm = function (){
        if ($("#paytpv_card").length==0 || $("#paytpv_card").val()==0){
            $('.payment_module').show();
            $('.card_payment_button').hide();
            if ($("#paytpv_card option").length==1)
                $('.creditcardsDropDown').hide();

        }else{
            $('.creditcardsDropDown').show();
            if ($("#paytpv_card > option").length>1){
                $('.payment_module').hide();
                $('.card_payment_button').show();
            }else{
                $('.payment_module').show();
            }
        }
    }


    /**********/
    /* Events */
    /**********/
    $('.paytpvListCC').change(function () {
        
        handlePaytpvRemoteCCForm();

    });

    /*****************/
    /* Initial calls */
    /*****************/
    handlePaytpvRemoteCCForm();


    /**********/
    /* Events */
    /**********/
    $('#checkoutPaytpvFormSubmitButtonRemove').click(function () {

        if (confirm($("#txt_remove_card").val())){
            link = $("#checkoutPaytpvFormSubmit").attr("href");
            task_aux = $("#notificationTask").val();
            $("#notificationTask").val("removeCard");
            var datas = $(this).parents("form").serializeArray()
            $.post(link, datas, function (data) {
                if (data == "ok") {
                    $("#paytpv_card option:selected").remove();

                    handlePaytpvRemoteCCForm(); 

                    $("#paytpv_card").trigger("liszt:updated");
                }
            });
            $("#notificationTask").val(task_aux);
            return false;
        }

    });




});

