<?php

namespace Genesisoft\MpMoipExt\Controller\Notification;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\Order\StatusFactory;

class Authorized extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $_order;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Webkul\MpMoip\Helper\Data
     */
    protected $_moipHelper;

    /**
     * @var StatusFactory
     */
    protected $statusFactory;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Webkul\MpMoip\Helper\Data $moipHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Webkul\MpMoip\Helper\Data $moipHelper,
        StatusFactory $statusFactory,
        JsonFactory $resultJsonFactory
    )
    {
        $this->_order = $order;
        $this->jsonHelper = $jsonHelper;
        $this->_moipHelper = $moipHelper;
        $this->statusFactory = $statusFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    /**
     * Execute
     */
    public function execute()
    {
        $moip = $this->_moipHelper->initializeMoip();
        $response = file_get_contents('php://input');
        $data = $this->jsonHelper->jsonDecode($response, true);
        $token = $this->getRequest()->getHeader('Authorization');
        $type = "payment_authorized";

        if ($this->_moipHelper->isValidToken($token, $type)) {
            $orderHref = $data['resource']['payment']['_links']['multiorder']['href'];
            $moipOrderId = explode("/", $orderHref);
            $moipOrderId = end($moipOrderId);

            if ($moipOrder = $this->_moipHelper->getOrderData($moipOrderId)) {
                $incrementId = $moipOrder->getMoipOrderOwnId();
                if ($order = $this->_moipHelper->getOrderByIncrementId($incrementId)) {
                    $collection = $this->statusFactory->create()->getCollection()
                        ->addFieldToFilter('status', ['eq' => 'approved']);

                    if ($collection->getSize() == 0) {
                        $data = [
                            'status' => 'approved',
                            'label' => 'Aprovado'
                        ];
                        $status = $this->statusFactory->create()->setData($data)->save();
                        $status->assignState('processing', 0, true);
                    }

                    $this->_moipHelper->processInvoice($order, $moipOrder);

                    $order->setStatus('approved');
                    $order->save();
                } else {
                    $this->_moipHelper->updateItem($moipOrder, ['webhook_status' => 1]);
                }
            }
        }

        return $this->resultJsonFactory->create()->setData(['success' => 1]);
    }
}
