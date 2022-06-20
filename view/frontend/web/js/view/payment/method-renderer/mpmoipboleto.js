/**
 * Webkul Software.
 *
 * @category  Webkul
 * @package   Webkul_MpMoip
 * @author    Webkul
 * @copyright Copyright (c) Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Webkul_MpMoip/js/model/mpmoipboleto'
    ],
    function (
        $,
        Component,
        mpmoipboleto
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Genesisoft_MpMoipExt/payment/boleto',
                customerCpfNumber : ''
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'customerCpfNumber'
                    ]);

                return this;
            },

            initialize: function () {
                this._super();
                this.setCustomerCpf();
            },

            setCustomerCpf: function () {
                setTimeout(
                    function () {
                        var cpfNoMask = $('input[name="vat_id"]').val().replace(/[^0-9]/gi, '');
                        if (cpfNoMask.length == 11) {
                            $('#mpmoipboleto_customer_cpf_number').val(cpfNoMask);
                            $('#mpmoipboleto_customer_cpf_number').blur();
                        }
                    },
                    4000
                );
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'customerCpfNumber' : this.customerCpfNumber()
                    }
                };
            },

            isActive :function () {
                return true;
            },

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            getCheckoutInstruction: function () {
                if(typeof window.checkoutConfig.payment.mpmoipboleto !== 'undefined') {
                    if(typeof window.checkoutConfig.payment.mpmoipboleto.checkout_instruction !== 'undefined') {
                        return window.checkoutConfig.payment.mpmoipboleto.checkout_instruction;
                    }
                }
            },

            getExpirationMessage: function () {
                if(typeof window.checkoutConfig.payment.mpmoipboleto !== 'undefined') {
                    if(typeof window.checkoutConfig.payment.mpmoipboleto.expiration_message !== 'undefined') {
                        return window.checkoutConfig.payment.mpmoipboleto.expiration_message;
                    }
                }
            }
        });
    }
);
