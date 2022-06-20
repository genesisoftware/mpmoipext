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
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'mpmoipboleto',
                component: 'Genesisoft_MpMoipExt/js/view/payment/method-renderer/mpmoipboleto'
            }
        );
        return Component.extend({});
    }
);
