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
        'underscore',
        'jquery',
        'ko',
        'Magento_Checkout/js/model/quote',
        'Magento_Catalog/js/price-utils',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Payment/js/model/credit-card-validation/credit-card-data',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Webkul_MpMoip/js/model/credit-card-validation/credit-card-number-validator',
        'Webkul_MpMoip/js/model/credit-card-validation/mpmoipcc',
        'mage/url',
        'mage/calendar',
        'mage/translate'
    ],
    function (
        _,
        $,
        ko,
        quote,
        priceUtils,
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        customer,
        checkoutData,
        creditCardData,
        validator,
        additionalValidators,
        cardNumberValidator,
        mpmoipcc,
        url,
        calendar,
        $t
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Genesisoft_MpMoipExt/payment/cc',
                creditCardType: '',
                creditCardExpYear: '',
                creditCardExpMonth: '',
                creditCardNumber: '',
                creditCardSsStartMonth: '',
                creditCardSsStartYear: '',
                creditCardSsIssue: '',
                creditCardVerificationNumber: '',
                selectedCardType: null,
                useEmi: 0,
                customerCpfNumber : '',
                holderCpfNumber : '',
                holderDob : ''
            },

            getCode: function () {
                return 'mpmoipcc';
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'creditCardType',
                        'creditCardExpYear',
                        'creditCardExpMonth',
                        'creditCardNumber',
                        'creditCardVerificationNumber',
                        'creditCardSsStartMonth',
                        'creditCardSsStartYear',
                        'creditCardSsIssue',
                        'selectedCardType',
                        'useEmi',
                        'customerCpfNumber',
                        'holderCpfNumber',
                        'holderDob'
                    ]);

                return this;
            },

            initialize: function () {
                this._super();
                var self = this;
                this.useEmi = 0;
                //Set credit card number to credit card data object
                this.creditCardNumber.subscribe(function (value) {
                    var result;
                    self.selectedCardType(null);
                    if (value === '' || value === null) {
                        return false;
                    }

                    result = cardNumberValidator(value);

                    if (!result.isPotentiallyValid && !result.isValid) {
                        return false;
                    }

                    if (result.card !== null) {
                        self.selectedCardType(result.card.type);
                        creditCardData.creditCard = result.card;
                    }

                    if (result.isValid) {
                        creditCardData.creditCardNumber = value;
                        self.creditCardType(result.card.type);
                    }

                });

                //Set expiration year to credit card data object
                this.creditCardExpYear.subscribe(function (value) {
                    creditCardData.expirationYear = value;
                });

                //Set expiration month to credit card data object
                this.creditCardExpMonth.subscribe(function (value) {
                    creditCardData.expirationMonth = value;
                });

                //Set cvv code to credit card data object
                this.creditCardVerificationNumber.subscribe(function (value) {
                    creditCardData.cvvCode = value;
                });
                this.setCustomerCpf();
                this.manageEmi();

            },

            setCustomerCpf: function () {
                setTimeout(
                    function () {
                        var cpfNoMask = $('input[name="vat_id"]').val().replace(/[^0-9]/gi, '');
                        if (cpfNoMask.length == 11) {
                            $('#mpmoipcc_holder_cpf_number').val(cpfNoMask);
                            $('#mpmoipcc_holder_cpf_number').blur();
                            $('#mpmoipcc_customer_cpf_number').val(cpfNoMask);
                            $('#mpmoipcc_customer_cpf_number').blur();
                        }
                    },
                    4000
                );
            },

            getCvvImageUrl: function () {
                if (typeof(window.checkoutConfig.payment.mpmoipcc) !== 'undefined') {
                    if (typeof(window.checkoutConfig.payment.mpmoipcc.image_cvv) !== 'undefined') {
                        return window.checkoutConfig.payment.mpmoipcc.image_cvv;
                    }
                }
            },

            getCvvImageHtml: function () {
                return '<img src="' + this.getCvvImageUrl() +
                    '" alt="' + $t('Card Verification Number Visual Reference') +
                    '" title="' + $t('Card Verification Number Visual Reference') +
                    '" />';
            },

            getCcAvailableTypes: function () {
                if (typeof(window.checkoutConfig.payment.this.item.method !== 'undefined')) {
                    if (typeof(window.checkoutConfig.payment.this.item.method.ccavailableTypes) != 'undefined') {
                        return window.checkoutConfig.payment.this.item.method.ccavailableTypes;
                    }
                }
            },

            selectPaymentMethod: function () {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },

            getPublickey: function () {
                if (typeof window.checkoutConfig.payment.mpmoipcc !== 'undefined') {
                    if (typeof window.checkoutConfig.payment.mpmoipcc.publickey !== 'undefined') {
                        return window.checkoutConfig.payment.mpmoipcc.publickey;
                    }
                }
            },

            getIcons: function (type) {
                return window.checkoutConfig.payment.mpmoipcc.icons.hasOwnProperty(type) ?
                    window.checkoutConfig.payment.mpmoipcc.icons[type]
                    : false;
            },

            getSpriteUrl: function (type) {
                if (typeof window.checkoutConfig.payment.mpmoipcc !== 'undefined') {
                    if (typeof window.checkoutConfig.payment.mpmoipcc.sprite_url !== 'undefined') {
                        return window.checkoutConfig.payment.mpmoipcc.sprite_url;
                    }
                }
            },

            displayEmi: function () {
                return false;
                return this.emi;
            },

            hideInstallments : function () {

            },

            showInstallments : function () {

            },

            manageEmi: function () {
                if ($(".wk-emi").prop('checked') == true) {
                    this.useEmi = 1;
                    $(".wk-installments").show();
                } else {
                    this.useEmi = 0;
                    $(".wk-installments").hide();
                }

                return true;
            },

            getCcAvailableTypesValues: function () {
                if (typeof window.checkoutConfig.payment.mpmoipcc !== 'undefined') {
                    if (typeof window.checkoutConfig.payment.mpmoipcc.ccavailabletypes !== 'undefined') {
                        return _.map(window.checkoutConfig.payment.mpmoipcc.ccavailabletypes, function (value, key) {
                            return {
                                'value': key,
                                'type': value
                            };
                        });
                    }
                }
            },

            getCcYearsValues: function () {
                        return _.map(window.checkoutConfig.payment.mpmoipcc.years, function (value, key) {
                            return {
                                'value': key,
                                'year': value
                            };
                        });
            },

            getCcMonthsValues: function () {
                        return _.map(window.checkoutConfig.payment.mpmoipcc.months, function (value, key) {
                            return {
                                'value': key,
                                'month': value
                            };
                        });
            },

            isActive :function () {
                return true;
            },

            getInstallmentsActive: ko.computed(function () {
                if (typeof window.checkoutConfig.payment.mpmoipcc !== 'undefined') {
                    if (typeof window.checkoutConfig.payment.mpmoipcc.allow_installments !== 'undefined') {
                        return window.checkoutConfig.payment.mpmoipcc.allow_installments;
                    }
                }
            }),

            getInstall: function () {
                var baseTotal = quote.totals().base_grand_total;
                var subtotalInclTax = quote.totals().subtotal_incl_tax;
                var subtotalWithDiscount = quote.totals().subtotal_with_discount;
                var taxAmount = quote.totals().tax_amount;
                var shippingInclTax = quote.totals().shipping_incl_tax;
                var total = quote.totals().grand_total;
                var total = subtotalWithDiscount + taxAmount + shippingInclTax;
                var interestType = window.checkoutConfig.payment.mpmoipcc.interest_type
                var interests = window.checkoutConfig.payment.mpmoipcc.info_interest;
                var totalInterests = {};
                var count = 2;
                _.each(interests, function (key, value) {
                    var value = interests[value];
                    var rate = value/100;
                    var installment;
                    if (interestType == "compound") {
                        var pw = Math.pow((1 / (1 + rate)), count);
                        if (rate == 0) {
                            installment = total / count;
                        } else {
                            installment = ((total * rate) / (1 - pw));
                        }
                    } else {
                        var installment = ((total*rate) + total) / count;
                    }

                    var totalInstallment = installment*count;
                    var interest = value;
                    totalInterests[count] = {
                        'installment' : priceUtils.formatPrice(installment, quote.getPriceFormat()),
                        'total_installment': priceUtils.formatPrice(totalInstallment, quote.getPriceFormat()),
                        'total_interest' : priceUtils.formatPrice(totalInstallment - total, quote.getPriceFormat()),
                        'interest' : interest,
                    };
                    count++;
                });

                return totalInterests;
            },

            getInstallments: function () {
                var temp = _.map(this.getInstall(), function (value, key) {
                    var juros = value['interest'] =="0"?' Sem Juros':' Com Juros';
                    var inst = key+' x '+value['installment']+' | ' + 'Total a pagar: '+value['total_installment']+ juros;

                    return { 'value': key, 'installments': inst, 'total': value['total_installment'], 'interest': value['interest']};
                });

                var newArray = [{"value" : "", "installments" : "-- Selecione o número de parcelas --" }];
                var allowedMonths = window.checkoutConfig.payment.mpmoipcc.allowed_months;
                for (var i = 0; i < temp.length; i++) {
                    if (temp[i].installments!='undefined' && temp[i].installments!=undefined) {
                        for (var key in allowedMonths) {
                            if (allowedMonths.hasOwnProperty(key)) {
                                var val = allowedMonths[key];
                                if (val == temp[i].value) {
                                    newArray.push(temp[i]);
                                }
                            }
                        }
                    }
                }

                return newArray;
            },

            getHash: function () {
                var cc = new Moip.CreditCard({
                    number  : this.creditCardNumber(),
                    cvc     : this.creditCardVerificationNumber(),
                    expMonth: this.creditCardExpMonth(),
                    expYear : this.creditCardExpYear(),
                    pubKey  : this.getPublickey()
                });

                if (cc.isValid()) {
                    $('#'+this.getCode()+'_hash').val(cc.hash());
                }

                return cc;
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'cc_number': this.creditCardNumber(),
                        'cc_type': this.creditCardType(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'fullname': $('#'+this.getCode()+'_fullname').val(),
                        'installments': $('#'+this.getCode()+'_installments').val(),
                        'hash': $('#'+this.getCode()+'_hash').val(),
                        'cvc' : this.creditCardVerificationNumber(),
                        'use_emi' : this.useEmi,
                        'customerCpfNumber' : this.customerCpfNumber(),
                        'holderCpfNumber' : this.holderCpfNumber(),
                        'holderBirthDate' : $('#'+this.getCode()+'_birthdate').val(),
                    }
                };
            },

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                this.getHash();
                return $form.validation() && $form.validation('isValid');
            },

            datePicker : function () {
                setTimeout(
                    function () {
                        $("input[name='holder_birthdate']").datepicker({
                            showsTime: false,
                            dateFormat: "dd/mm/yy",
                            yearRange: "-120y:c+nn",
                            buttonText: "",
                            maxDate: "-1d",
                            changeMonth: true,
                            changeYear: true,
                            showOn: "both",
                            dayNames: ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'],
                            dayNamesMin: ['D','S','T','Q','Q','S','S','D'],
                            dayNamesShort: ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb','Dom'],
                            monthNames: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
                            monthNamesShort: ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'],
                            nextText: 'Próximo',
                            prevText: 'Anterior'
                        });
                    },
                    3000
                );
                return true;
            }
        });
    }
);

