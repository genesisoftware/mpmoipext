<?php

namespace Genesisoft\MpMoipExt\Helper;

use Magento\Sales\Model\Order;
use Moip\Resource\Orders;

class Data extends \Webkul\MpMoip\Helper\Data

{
    public function createMoipHolder($order, $infoInstance)
    {
        try {
            $moip = $this->initializeMoip();
            $billingAddress = $order->getBillingAddress();
            $holderName = $infoInstance->getAdditionalInformation('fullname');
            $holderCpf = $infoInstance->getAdditionalInformation('holderCpfNumber');
            $holderDob = $infoInstance->getAdditionalInformation('holderBirthDate');
            if (!($this->validateCPF($holderCpf))) {
                $this->_messageManager->addError(__("Please use a valid CPF Number for Card Holder."));
                $this->_redirectFactory->create()->setPath("checkout/cart");
            }

            if (!$order->getCustomerFirstname()) {
                $name = $billingAddress->getName();
            } else {
                $name = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
            }

            $email = $order->getCustomerEmail();
            $phone = $billingAddress->getTelephone();
            $phoneCode = $this->getPhoneNumber($phone, true);
            $phoneNumber = $this->getPhoneNumber($phone, false);
            $street = $billingAddress->getStreet();
            if (is_array($street)) {
                $street = $street[0];
            }
            $number = $billingAddress->getStreet()[1];
            $city = $billingAddress->getData('city');
            $state = $billingAddress->getRegionCode();
            $postcode = $billingAddress->getData('postcode');
            $district = $billingAddress->getStreet()[2];
            $zip = substr(preg_replace("/[^0-9]/", "", $postcode) . '00000000', 0, 8);
            $customerDetails = $this->getCustomerDetails();
            $moipHolder = $moip->holders()
                ->setFullname(substr($holderName,0,90))
                ->setBirthDate($holderDob)
                ->setTaxDocument($holderCpf)
                ->setPhone($phoneCode, $phoneNumber)
                ->setAddress('BILLING', $street, substr($number,0,10), substr($district,0,45), substr($city,0,32), $state, $zip);
            return $moipHolder;
        } catch (\Exception $e) {
            $this->_mpMoipLogger->addInfo($e->getMessage());
        }
    }

    public function generateCustomerMoip($order, $infoInstance)
    {
        try {
            $moip = $this->initializeMoip();
            $billingAddress = $order->getBillingAddress();
            if (!$order->getCustomerFirstname()) {
                $name = $billingAddress->getName();
            } else {
                $name = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
            }

            $email = $order->getCustomerEmail();
            $phone = $billingAddress->getTelephone();
            $phoneCode = $this->getPhoneNumber($phone, true);
            $phoneNumber = $this->getPhoneNumber($phone, false);
            $street = $billingAddress->getStreet();
            if (is_array($street)) {
                $street = $street[0];
            }
            $number = $billingAddress->getStreet()[1];
            $city = $billingAddress->getData('city');
            $state = $billingAddress->getRegionCode();
            $postcode = $billingAddress->getData('postcode');
            $district = $billingAddress->getStreet()[2];
            $zip = substr(preg_replace("/[^0-9]/", "", $postcode) . '00000000', 0, 8);
            $customerDetails = $this->getCustomerDetails();
            $customerCpf = $infoInstance->getAdditionalInformation('customerCpfNumber');
            if (!($this->validateCPF($customerCpf))) {
                $this->_messageManager->addError(__("Please use a valid CPF Number for Customer."));
                return $this->_redirectFactory->create()->setPath("checkout/cart/index");
            }

            $birthDate = $customerDetails['dob'];
            $moipCustomer = $moip->customers()
                ->setOwnId(uniqid())
                ->setFullname(substr($name,0,90))
                ->setEmail($email)
                ->setBirthDate($birthDate)
                ->setTaxDocument($customerCpf)
                ->setPhone($phoneCode, $phoneNumber)
                ->addAddress('BILLING', $street, substr($number,0,10), substr($district,0,45), substr($city,0,32), $state, $zip);

            if ($shippingAddress = $order->getShippingAddress()) {
                $street = $shippingAddress->getStreet();
                if (is_array($street)) {
                    $street = $street[0];
                }

                $number = $billingAddress->getStreet()[1];
                $city = $shippingAddress->getData('city');
                $state = $shippingAddress->getRegionCode();
                $postcode = $shippingAddress->getData('postcode');
                $district = $billingAddress->getStreet()[2];
                $zip = substr(preg_replace("/[^0-9]/", "", $postcode) . '00000000', 0, 8);
                $moipCustomer->addAddress('SHIPPING', $street, substr($number, 0, 10), substr($district, 0, 45), substr($city, 0, 32), $state, $zip);;
            }

            $moipCustomer = $moipCustomer->create();
            return $moipCustomer;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $this->_mpMoipLogger->addInfo($e->getMessage());
        }
    }

    public function createMoipOrder($order, $infoInstance)
    {
        $moip = $this->initializeMoip();
        $baseUrl = $this->getBaseUrl();

        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
        $moipHolder = "";

        if ($paymentMethod == "mpmoipcc") {
            $moipHolder = $this->createMoipHolder($order, $infoInstance);
        }
        $moipCustomer = $this->createMoipCustomer($order, $infoInstance);

        $orderItems = [];
        $totalSeller = 0;
        $isAdmin = false;
        foreach ($order->getAllVisibleItems() as $item) {
            $sellerId = $this->getSellerIdByOrderItem($item);
            $sellers[] = $sellerId;
            $orderItems[$sellerId][] = $item;
            $totalSeller++;
            if ($sellerId == 0) {
                $isAdmin = true;
            }
        }

        if (self::IS_ADMIN_PRIMARY_RECIEVER == 1) {
            $multiOrder = $moip->multiorders()->setOwnId($order->getIncrementId());
            $orderId = $order->getIncrementId() . "_" . uniqid();
            $shipping = round($order->getShippingAmount(),2) * self::ROUND_UP;
            $discount = round($order->getDiscountAmount(),2) * self::ROUND_UP;
            $adminSeller = $this->getMoipSellerBySellerId(0);
            $moipOrder = $moip->orders()->setOwnId($orderId);

            $moipOrder->setShippingAmount(0);
            $moipOrder->setDiscount(abs($discount));
            $moipOrder->setCustomer($moipCustomer);
            $moipOrder = $this->addAdditionalAmount($moipOrder, $order, $infoInstance);

            $adminAmount = 0;
            $result = $this->productSalesCalculation($order);

            // Se Frenet p/ Marketplace, pega valor do frete por Seller e coloca no array $freteSeller
            $shippingInfo = $this->_coreSession->getData('shippinginfo');
            if (!empty($shippingInfo['mpfrenet'])) {
//                $chosenMethod = explode('_',$order->getShippingMethod())[1];
                $chosenMethod = explode('_', $order->getShippingMethod(), 2); //Alteração no explode para remoção apenas do primeiro '_'. Antes ele quebrava o array em 3 itens e não prosseguia, pois não identificava o método de envio.
                if (!empty($chosenMethod)) {
                    foreach ($shippingInfo['mpfrenet'] as $k => $v) {
                        $freteSeller[$v['seller_id']] = $v['submethod'][$chosenMethod[1]]['base_amount'];
                    }
                }
            }

            foreach ($orderItems as $sellerId => $items) {
                if (!empty($shippingInfo['mpfrenet'])) {
                    $qtyOrderd = 0;
                    foreach ($items as $sellerItem) {
                        $qtyOrderd += $sellerItem->getQtyOrdered();
                    }
                    // Pega total de frete do seller e distribui pelos seus itens
                    $sellerShipping = $freteSeller[$sellerId];
                    // Distribui o valor do frente entre os itens
                    foreach ($items as $eachItem) {
                        $eachItem->setShopUrl($this->getSellerDetails($sellerId)->getShopUrl());
                    }
                    $moipOrder = $this->addItemsToMoipOrder($moipOrder, $items, $sellerShipping);
                } else {
                    $moipOrder = $this->addItemsToMoipOrder($moipOrder, [$item]);
                }

                $sellerData = $result[$sellerId];
                $totalTaxAmount = $sellerData['total_tax'] * self::ROUND_UP;
                $totalShippingAmount = $sellerData['total_shipping'] * self::ROUND_UP;
                $sellerId = $sellerData['seller_id'];
                if(empty($sellerShipping)){$sellerShipping = 0.01;}
                $sellerAmount = (round($sellerData['seller_amount'], 2) + $sellerShipping) * self::ROUND_UP;

                if (!empty($shippingInfo['mpfrenet'])) {
                    $sellerAmount += round($sellerData['shipping'], 2) * self::ROUND_UP;
                }

                $sellerData['tax'] += $this->addSplitInstallment($moipOrder->getSubtotalAddition(), $order->getBaseSubtotal(), $sellerData['seller_amount']);

                $sellerAmount = $this->addTaxForSeller($sellerAmount, $sellerData);
                if ($sellerAmount > 0 && $sellerId > 0) {
                    $moipSeller = $this->getMoipSellerBySellerId($sellerId);
                    $paySeller = $moipSeller ? 1 : 0;
                    if ($paySeller) {
                        $moipOrder->addReceiver($moipSeller->getMoipAccountId(), 'SECONDARY', $sellerAmount);
                    } else {
                        $adminAmount += $sellerAmount;
                    }
                }
            }

            if ($adminAmount > 0) {
                $moipOrder->addReceiver($adminSeller->getMoipAccountId(), 'PRIMARY', $adminAmount);
            } else {
                $moipOrder->addReceiver($adminSeller->getMoipAccountId(), 'PRIMARY', null);
            }

            $multiOrder->addOrder($moipOrder);
        } else {
            $multiOrder = $this->createMultiOrder($moip, $order, $moipCustomer, $infoInstance);
        }

        return ['moip_order' => $multiOrder, 'moip_customer' => $moipCustomer, 'moip_holder' => $moipHolder];
    }

    /**
     * Get Compound Interest EMI
     *
     * @param float $amount
     * @param float $interest
     * @param int $installment
     *
     * @return float
     */
    public function getCompoundInterestEmi($amount, $interest, $installment)
    {
        if ($interest == 0) {
            $emi = $amount / $installment;
        } else {
            $rate = $interest / 100;
            $emi = ($amount * $rate) / (1 - (pow(1 / (1 + $rate), $installment)));
        }

        return $emi;
    }

    /**
     * Get Boleto Href
     *
     * @param \Moip\Resource\Payment $moipPayment
     *
     * @return string
     */
    public function getBoletoHref($moipPayment)
    {
        return $moipPayment->getLinks()->getAllCheckout()->payBoleto;

    }

    /**
     * Create Webhooks
     */
    public function manageWebhooks()
    {
        $moip = $this->initializeMoip();
        if ($moip) {
            $baseUrl = $this->getBaseUrl();
            /*Setting Refund Webhook Preference*/
            $url = $baseUrl . 'mpmoip/notification/refund';
            $webhook = $moip->notifications()
                ->addEvent('REFUND.COMPLETED')
                ->setTarget($url)
                ->create();
            $token = $webhook->getToken();
            $tokenId = $webhook->getId();
            $data = [
                'type' => 'refund_completed',
                'token' => $token,
                'token_id' => $tokenId
            ];
            $this->_moipTokens->create()->setData($data)->save();

            /*Setting Payment Authorization Webhook Preference*/
            $url = $baseUrl . 'mpmoip/notification/authorized';
            $webhook = $moip->notifications()
                ->addEvent('MULTIPAYMENT.AUTHORIZED')
                ->setTarget($url)
                ->create();
            $token = $webhook->getToken();
            $tokenId = $webhook->getId();
            $data = [
                'type' => 'payment_authorized',
                'token' => $token,
                'token_id' => $tokenId
            ];
            $this->_moipTokens->create()->setData($data)->save();

            /*Setting Payment Cancelled Webhook Preference*/
            $url = $baseUrl . 'mpmoip/notification/multipaymentCancelled';
            $webhook = $moip->notifications()
                ->addEvent('MULTIPAYMENT.CANCELLED')
                ->setTarget($url)
                ->create();
            $token = $webhook->getToken();
            $tokenId = $webhook->getId();
            $data = [
                'type' => 'payment_cancelled',
                'token' => $token,
                'token_id' => $tokenId
            ];
            $this->_moipTokens->create()->setData($data)->save();
        }
    }

    /**
     * Create Moip Order For Seller
     *
     * @param \Moip\Moip $moip
     * @param array $items
     * @param array $sellerData
     * @param \Moip\Resource\Customer $moipCustomer
     * @param \Magento\Sales\Model\Order $order
     *
     * @return \Moip\Resource\Orders
     */
    public function createMoipOrderForSeller($moip, $items, $sellerData, $moipCustomer, $order)
    {
        $sellerId = $sellerData['seller_id'];
        $shipping = round($sellerData['shipping'],2) * self::ROUND_UP;
        $discount = round($sellerData['discount'],2) * self::ROUND_UP;
        $orderId = $order->getIncrementId()."_".uniqid();
        $moipSeller = $this->getMoipSellerBySellerId($sellerId);
        $moipOrder = $moip->orders()->setOwnId($orderId);
        $moipOrder = $this->addItemsToMoipOrder($moipOrder, $items);
        $moipOrder->setShippingAmount($shipping);
        $moipOrder->setDiscount($discount);
        $moipOrder->setCustomer($moipCustomer);
        $moipOrder->addReceiver($moipSeller->getMoipAccountId(), 'PRIMARY', null);
        return $moipOrder;
    }

    /**
     * Add Items To Moip Order
     *
     * @param \Moip\Resource\Orders $moipOrder
     * @param array $items
     *
     * @return \Moip\Resource\Orders
     */
    public function addItemsToMoipOrder($moipOrder, $items, $sellerShipping = null)
    {
        foreach ($items as $k => $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $name = $item->getName();
            $sku = $item->getSku();
            $qty = (int) $item->getQtyOrdered();

            $price = $item->getBasePrice();

            $setprice = (int) round(($price * self::ROUND_UP),0);

            // Adiciona item com total de frete do seller
            if (array_key_last($items) === $k) {
                $sellerShipping = $sellerShipping * self::ROUND_UP;
                if($sellerShipping > 0) {
                    $moipOrder->addItem('Frete ' . $item->getShopUrl() . ': ', 1, "", (int)$sellerShipping);
                }
            }

            $moipOrder->addItem("$name", $qty, "$sku", (int)$setprice);
        }
        return $moipOrder;
    }

    /**
     * Create Moip Order For Admin
     *
     * @param \Moip\Moip $moip
     * @param \Moip\Resource\Customer $moipCustomer
     * @param \Magento\Sales\Model\Order $order
     * @param float $shipping
     * @param float $tax
     *
     * @return \Moip\Resource\Orders
     */
    public function createMoipOrderForAdmin($moip, $moipCustomer, $order, $shipping, $tax)
    {
        try {
            $totalPrice = $shipping + $tax;
            $totalPrice = round($totalPrice,2) * self::ROUND_UP;
            $orderId = $order->getIncrementId()."_".uniqid();
            $moipSeller = $this->getMoipSellerBySellerId(0);
            $moipOrder = $moip->orders()->setOwnId($orderId);
            $moipOrder->addItem("Admin Amount", 1, "Admin Amount", $totalPrice);
            $moipOrder->setShippingAmount(0);
            $moipOrder->setDiscount(0);
            $moipOrder->setCustomer($moipCustomer);
            $moipOrder->addReceiver($moipSeller->getMoipAccountId(), 'PRIMARY', null);
            return $moipOrder;
        } catch (\Exception $e) {
            $error = $e->__toString();
        }
    }

    /**
     * Add Commission To Moip Order
     *
     * @param \Moip\Resource\Orders $moipOrder
     * @param array $sellerData
     *
     * @return \Moip\Resource\Orders
     */
    public function addCommission($moipOrder, $sellerData)
    {
        $sellerId = $sellerData['seller_id'];
        $commission = round($sellerData['commission'],2) * self::ROUND_UP;
        if ($commission > 0 && $sellerId > 0) {
            $moipSeller = $this->getMoipSellerBySellerId(0);
            $moipOrder->addReceiver($moipSeller->getMoipAccountId(), 'SECONDARY', $commission);
        }

        return $moipOrder;
    }

    /**
     * Add Additional Price To Moip Order
     *
     * @param \Moip\Resource\Orders $moipOrder
     * @param array $sellerData
     * @param \Magento\Sales\Model\Order\Payment $infoInstance
     *
     * @return \Moip\Resource\Orders
     */
    public function addAdditionalPrice($moipOrder, $sellerData, $infoInstance)
    {
        $tax = $sellerData['tax'];
        $shipping = $sellerData['shipping'];
        $discount = $sellerData['discount'];
        $total = $sellerData['total'];
        $useEmi = (int) $infoInstance->getAdditionalInformation('use_emi');

        if ($useEmi == 0) {
            $installmentCount = 1;
        } else {
            $installmentCount = (int) $infoInstance->getAdditionalInformation('installments');
        }

        $grandTotal = $total + $tax + $shipping - $discount;
        $additionalPrice = round($tax,2) * self::ROUND_UP;

        if ($installmentCount > 1) {
            $rate = $this->getRate($installmentCount);
            $interestType = $this->getInterestType();
            if ($interestType == "compound") {
                $installment = $this->getCompoundInterestEmi($grandTotal, $rate, $installmentCount);
            } else {
                $installment = $this->getSimpleInterestEmi($grandTotal, $rate, $installmentCount);
            }

            $totalInstallments = $installment * $installmentCount;
            $additionalPrice = $totalInstallments - $grandTotal;
            $additionalPrice = number_format((float)$additionalPrice, 2, '.', '') * self::ROUND_UP;
            $additionalPrice = $additionalPrice + $tax;
        }

        $moipOrder->setAddition($additionalPrice);

        return $moipOrder;
    }

    /**
     * Get Calculated Details Of Item
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @param int $sellerId
     *
     * @return array
     */
    public function getCalculatedDetailsOfItem($item, $sellerId)
    {
        $discountDetails = $this->_coreSession->getData('salelistdata');
        $qty = $item->getQtyOrdered();
        if ($item->getProductType() != 'bundle') {
            $price = $this->getProductPrice($item, $discountDetails);
            $taxAmount = $item->getBaseTaxAmount();
            $totalAmount = $qty * $price;
            $advanceCommissionRule = $this->_customerSession->create()->getData('advancecommissionrule');

            $totalAmount = $totalAmount - $item->getDiscountAmount();
            $commission = $this->getCommission($sellerId, $totalAmount, $item, $advanceCommissionRule);
            $sellerAmount = $totalAmount - $commission;
        } else {
            $price = 0;
            $taxAmount = 0;
            $totalAmount = 0;
            $commission = 0;
            $sellerAmount = 0;
        }

        $result = [];
        $result['price'] = $price;
        $result['tax_amount'] = $taxAmount;
        $result['total_amount'] = $totalAmount;
        $result['commission'] = $commission;
        $result['seller_amount'] = $sellerAmount;
        return $result;
    }

    /**
     * Get Seller Coupon Details
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @param array $sellerCoupons
     * @param int $sellerId
     *
     * @return array
     */
    public function getSellerCouponDetails($item, $sellerCoupons, $sellerId)
    {
        $amount = 0;
        if (!$this->_moduleManager->isEnabled('Webkul_MpSellerCoupons')) {
            $baseDiscountAmount = $item->getBaseDiscountAmount();
            if ($baseDiscountAmount > 0) {
                if (array_key_exists($sellerId, $sellerCoupons)) {
                    $sellerCoupons[$sellerId] = $sellerCoupons[$sellerId] + $baseDiscountAmount;
                } else {
                    $sellerCoupons[$sellerId] = $baseDiscountAmount;
                }
            }
        }

        return $sellerCoupons;
    }

    /**
     * @param float $subtotal_addition
     * @param float $grand_total
     * @param float $seller_amount
     * @return float|int
     */
    public function addSplitInstallment(float $subtotal_addition, float $grand_total, float $seller_amount)
    {
        $commissionPercent = (100 * $seller_amount) / ($grand_total);
        $addition = $subtotal_addition / 100;
        $value = ($addition * $commissionPercent) / 100;
        return round($value, 2);
    }

    /**
     * Add Payment Method Boleto To Moip Order
     *
     * @param \Moip\Resource\Orders $moipOrder
     *
     * @return \Moip\Resource\Payment
     */
    public function addMoipPaymentBoleto($moipOrder)
    {
        $days = $this->getExpirationDays();
        $expirationDate = $this->getExpirationDate($days);
        $type = \Magento\Framework\UrlInterface::URL_TYPE_MEDIA;
        $mediaUrl = $this->_storeManager->getStore()->getBaseUrl($type);
        $logoUri = null;
        $instructions = [
            $this->getInstructionLines(1),
            $this->getInstructionLines(2),
            $this->getInstructionLines(3)
        ];
        $payMoip = $moipOrder->multipayments()
            ->setBoleto($expirationDate, $logoUri, $instructions)
            ->execute();
        return $payMoip;
    }
}
