<?php
/**
 * Webkul Software.
 *
 * @category  Webkul
 * @package   Webkul_MpMoip
 * @author    Webkul
 * @copyright Copyright (c) Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
namespace Genesisoft\MpMoipExt\Model;

use \Magento\Framework\Exception\LocalizedException;

class PaymentBoleto extends \Webkul\MpMoip\Model\PaymentBoleto
{
    /**
     * Authorize Payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $this->_mpMoipLogger->info("authorize ".$order->getIncrementId());
        try {
            if ($amount <= 0) {
                throw new LocalizedException(__('Invalid amount for authorization.'));
            }

            try {
                $infoInstance = $this->getInfoInstance();
                $result = $this->_moipHelper->createMoipOrder($order, $infoInstance);
                $moipOrder = $result['moip_order'];
                $moipCustomer = $result['moip_customer'];
                try {
                    $moipOrder->create();
                } catch (\Exception $e) {
                    $this->_mpMoipLogger->info($e->__toString());
                    throw new LocalizedException(__('Payment failed ' . $e->__toString()));
                }

                $moipOrderId = $moipOrder->getId();
                $moipOrderOwnId = $moipOrder->getOwnId();
                $data = [];
                $data['moip_order_id'] = $moipOrderId;
                $data['method'] = "boleto";
                $this->_moipHelper->setOrderData($data);

                try {
                    $moipPayment = $this->_moipHelper->addMoipPaymentBoleto($moipOrder);
                } catch (\Exception $e) {
                    $this->_mpMoipLogger->info($e->__toString());
                    throw new LocalizedException(__('Payment failed ' . $e->__toString()));
                }

                $moipPaymentId = $moipPayment->getId();
                $moipCustomerId = $moipCustomer->getId();

                $paymentInfo = [
                    'moip_customer_id' => $moipCustomer->getId(),
                    'moip_order_id' => $moipOrderId,
                    'moip_order_own_id' => $moipOrderOwnId,
                    'boleto_href' => $this->_moipHelper->getBoletoHref($moipPayment),
                    'boleto_href_print' => $this->_moipHelper->getBoletoPrintHref($moipPayment),
                    'boleto_line_code' => $moipPayment->getLineCodeBoleto(),
                    'boleto_expiration_date' => $moipPayment->getExpirationDateBoleto(),
                    'moip_payment_id' =>  $moipPayment->getId(),
                    'moip_payment' => $this->jsonHelper->jsonEncode($moipPayment),
                    'moip_order' => $this->jsonHelper->jsonEncode($moipOrder)
                ];
                $payment->setTransactionId($moipOrder->getId())
                        ->setIsTransactionClosed(0)
                        ->setTransactionAdditionalInfo('raw_details_info', $paymentInfo);
                $this->getInfoInstance()->setAdditionalInformation($paymentInfo);

                $data = [
                    'moip_order_id' => $moipOrderId,
                    'moip_customer_id' => $moipCustomerId,
                    'moip_payment_id' => $moipPaymentId,
                    'moip_order_own_id' => $moipOrderOwnId
                ];
                $this->_moipHelper->setOrderData($data);
            } catch (\Exception $e) {
                throw new LocalizedException(__('Payment failed ' . $e->getMessage()));
            }
        } catch (\Exception $e) {
            throw new LocalizedException(__('Payment failed ' . $e->getMessage()));
        }

        return $this;
    }

}
