<?php

namespace Genesisoft\MpMoipExt\Plugin\Observer;

use Magento\Customer\Model\Session;
use Magento\Framework\Event\Manager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Webkul\Marketplace\Helper\Data as MarketplaceHelper;
use Webkul\Marketplace\Model\ResourceModel\Saleperpartner\CollectionFactory as SalePerPartnerCollectionFactory;
use Webkul\Marketplace\Model\Saleperpartner;
use Webkul\Marketplace\Observer\SalesOrderPlaceAfterObserver as SalesOrderPlaceAfterObserverSubject;

class SalesOrderPlaceAfterObserver
{
    /**
     * @var eventManager
     */
    protected $_eventManager;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var Magento\Customer\Model\Session
     */
    protected $_customerSession;


    /**
     * [$_coreSession description].
     *
     * @var SessionManager
     */
    protected $_coreSession;

    /**
     * @var MarketplaceHelper
     */
    protected $_marketplaceHelper;

    /**
     * @var DateTime
     */
    protected $_date;

    /**
     * @var SalePerPartnerCollectionFactory
     */
    protected $salePerPartnerCollectionFactory;

    /**
     * SalesOrderPlaceAfterObserver constructor.
     * @param Manager $eventManager
     * @param ObjectManagerInterface $objectManager
     * @param Session $customerSession
     * @param SessionManager $coreSession
     * @param MarketplaceHelper $marketplaceHelper
     * @param DateTime $date
     * @param SalePerPartnerCollectionFactory $salePerPartnerCollectionFactory
     */
    public function __construct(
        Manager $eventManager,
        ObjectManagerInterface $objectManager,
        Session $customerSession,
        SessionManager $coreSession,
        MarketplaceHelper $marketplaceHelper,
        DateTime $date,
        SalePerPartnerCollectionFactory $salePerPartnerCollectionFactory
    )
    {
        $this->_eventManager = $eventManager;
        $this->_objectManager = $objectManager;
        $this->_customerSession = $customerSession;
        $this->_coreSession = $coreSession;
        $this->_marketplaceHelper = $marketplaceHelper;
        $this->_date = $date;
        $this->salePerPartnerCollectionFactory = $salePerPartnerCollectionFactory;
    }

    public function aroundGetSellerProductData(SalesOrderPlaceAfterObserverSubject $subject, \Closure $proceed, Order $order, $ratesPerCurrency)
    {
        /*
        * Get Global Commission Rate for Admin
        */
        $percent = $this->_marketplaceHelper->getConfigCommissionRate();

        $lastOrderId = $order->getId();

        $sellerProArr = [];
        $sellerTaxArr = [];
        $sellerCouponArr = [];
        $isShippingFlag = [];

        /*
        * Marketplace Credit discount data
        */
        $discountDetails = [];
        $discountDetails = $this->_coreSession->getData('salelistdata');

        foreach ($order->getAllItems() as $item) {
            $itemData = $item->getData();
            $sellerId = $subject->getSellerIdPerProduct($item);
            if ($itemData['product_type'] != 'bundle') {
                $isShippingFlag = $subject->getShippingFlag($item, $sellerId, $isShippingFlag);

                if (strpos($order->getShippingMethod(),'mpfrenet') !== false) {
                    $itemData['base_price'] = $itemData['price'];
                }

                $price = $itemData['base_price'];

                $taxamount = $itemData['base_tax_amount'];
                $qty = $item->getQtyOrdered();

                $totalamount = $qty * $price;

                $advanceCommissionRule = $this->_customerSession->getData(
                    'advancecommissionrule'
                );
                $commission = $subject->getCommission($sellerId, $totalamount, $item, $advanceCommissionRule);

                $actparterprocost = $totalamount - $commission;
            } else {
                if (empty($isShippingFlag[$sellerId])) {
                    $isShippingFlag[$sellerId] = 0;
                }
                $price = 0;
                $taxamount = 0;
                $qty = $item->getQtyOrdered();
                $totalamount = 0;
                $commission = 0;
                $actparterprocost = 0;
            }

            $collectionsave = $this->_objectManager->create(
                'Webkul\Marketplace\Model\Saleslist'
            );
            $collectionsave->setMageproductId($item->getProductId());
            $collectionsave->setOrderItemId($item->getItemId());
            $collectionsave->setParentItemId($item->getParentItemId());
            $collectionsave->setOrderId($lastOrderId);
            $collectionsave->setMagerealorderId($order->getIncrementId());
            $collectionsave->setMagequantity($qty);
            $collectionsave->setSellerId($sellerId);
            $collectionsave->setCpprostatus(\Webkul\Marketplace\Model\Saleslist::PAID_STATUS_PENDING);
            $collectionsave->setMagebuyerId($this->_customerSession->getCustomerId());
            $collectionsave->setMageproPrice($price);
            $collectionsave->setMageproName($item->getName());
            if ($totalamount != 0) {
                $collectionsave->setTotalAmount($totalamount);
                $commissionRate = ($commission * 100) / $totalamount;
            } else {
                $collectionsave->setTotalAmount($price);
                $commissionRate = $percent;

                $collection = $this->salePerPartnerCollectionFactory->create()
                    ->addFieldToFilter('seller_id', $sellerId);

                /** @var Saleperpartner $item */
                foreach ($collection as $item) {
                    $commissionRate = $item->getCommissionRate();
                }
            }
            $collectionsave->setTotalTax($taxamount);
            if (!$this->_marketplaceHelper->isSellerCouponModuleInstalled()) {
                if (isset($itemData['base_discount_amount'])) {
                    $baseDiscountAmount = $itemData['base_discount_amount'];
                    $collectionsave->setIsCoupon(1);
                    $collectionsave->setAppliedCouponAmount($baseDiscountAmount);

                    if (!isset($sellerCouponArr[$sellerId])) {
                        $sellerCouponArr[$sellerId] = 0;
                    }
                    $sellerCouponArr[$sellerId] = $sellerCouponArr[$sellerId] + $baseDiscountAmount;
                }
            }
            $collectionsave->setTotalCommission($commission);
            $collectionsave->setActualSellerAmount($actparterprocost);
            $collectionsave->setCommissionRate($commissionRate);
            $collectionsave->setCurrencyRate($ratesPerCurrency);
            if (isset($isShippingFlag[$sellerId])) {
                $collectionsave->setIsShipping($isShippingFlag[$sellerId]);
            }
            $collectionsave->setCreatedAt($this->_date->gmtDate());
            $collectionsave->setUpdatedAt($this->_date->gmtDate());
            $collectionsave->save();
            $qty = 0;
            if (!isset($sellerTaxArr[$sellerId])) {
                $sellerTaxArr[$sellerId] = 0;
            }
            $sellerTaxArr[$sellerId] = $sellerTaxArr[$sellerId] + $taxamount;
            if ($price != 0.0000) {
                if (!isset($sellerProArr[$sellerId])) {
                    $sellerProArr[$sellerId] = [];
                }
                array_push($sellerProArr[$sellerId], $item->getProductId());
            } else {
                if (!$item->getParentItemId()) {
                    if (!isset($sellerProArr[$sellerId])) {
                        $sellerProArr[$sellerId] = [];
                    }
                    array_push($sellerProArr[$sellerId], $item->getProductId());
                }
            }
        }

        return [
            'seller_pro_arr' => $sellerProArr,
            'seller_tax_arr' => $sellerTaxArr,
            'seller_coupon_arr' => $sellerCouponArr
        ];
    }
}
