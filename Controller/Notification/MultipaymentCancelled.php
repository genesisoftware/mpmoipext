<?php

namespace Genesisoft\MpMoipExt\Controller\Notification;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class MultipaymentCancelled extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $_logger;
    protected $_moipHelper;
    protected $_orderCommentSender;
    protected $resultJsonFactory;

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Sales\Model\Order $_order,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Sales\Model\Order\Payment\Transaction $transaction,
        \Magento\Sales\Model\Order\Payment\Transaction\Repository $transactionRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Webkul\MpMoip\Helper\Data $moipHelper,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_logger = $logger;
        $this->order = $order;
        $this->_order = $_order;
        $this->orderManagement = $orderManagement;
        $this->transaction = $transaction;
        $this->transactionRepository = $transactionRepository;
        $this->_orderFactory = $orderFactory;
        $this->_moipHelper = $moipHelper;
        $this->_orderCommentSender = $orderCommentSender;

    }

    public function execute()
    {
        $moip = $this->_moipHelper->initializeMoip();
        $response = file_get_contents('php://input');
        $data = json_decode($response, true);
        $token = $this->getRequest()->getHeader('Authorization');
        $type = "payment_cancelled";

        if (!$this->_moipHelper->isValidToken($token, $type)) {
            $this->_logger->debug("Authorization Invalida " . $token);
            return $this->resultJsonFactory->create()->setData(['success' => 0, 'message' => 'Authorization Invalida']);
        } else {
            $orderHref = $data['resource']['payment']['_links']['multiorder']['href'];
            $moipOrderId = explode("/", $orderHref);
            $order_id = end($moipOrderId);
            if ($moipOrder = $this->_moipHelper->getOrderData($order_id)) {
                $incrementId = $moipOrder->getMoipOrderOwnId();
                $this->_logger->debug("Autoriza pagamento do pedido " . $incrementId);
                if ($order = $this->_moipHelper->getOrderByIncrementId($incrementId)) {
                    $order->setStatus('canceled');
                    $order->save();

                    $payment = $order->getPayment();
                    $transactionId = $payment->getLastTransId();
                    $method = $payment->getMethodInstance();
                    try {

                        $description_for_customer = [];
                        foreach ($data['resource']['payment']['payments'] as $payment_item){
                            if (isset($payment_item['cancellationDetails'])) {
                                $description_by = $payment_item['cancellationDetails']['cancelledBy'];
                                $description_code = $payment_item['cancellationDetails']['code'];
                                $description_description = $payment_item['cancellationDetails']['description'];
                                $description_for_customer[] = __($description_description);
                            }
                        }

                        if (empty($description_for_customer)) {
                            $description_cancel = "Prazo limite de pagamento excedido";
                            $description_for_customer[] = __($description_cancel);
                        }

                        $description_for_customer = implode(' | ',$description_for_customer);

                        $method->fetchTransactionInfo($payment, $transactionId, $description_for_customer);
                        $order->save();
                        $this->addCancelDetails($description_for_customer, $order);
                    } catch (\Exception $e) {
                        return $this->resultJsonFactory->create()->setData(['success' => 0]);
                    }
                    return $this->resultJsonFactory->create()->setData(['success' => 1]);
                }
            }
        }
    }

    private function addCancelDetails($comment, $order)
    {
        $status = $this->orderManagement->getStatus($order->getEntityId());
        $history = $order->addStatusHistoryComment($comment, $status);
        $history->setIsVisibleOnFront(1);
        $history->setIsCustomerNotified(1);
        $history->save();
        $comment = trim(strip_tags($comment));
        $order->save();
        $this->_orderCommentSender->send($order, 1, $comment);
        return $this;
    }
}
