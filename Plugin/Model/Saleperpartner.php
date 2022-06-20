<?php
/**
 * Webkul Software.
 *
 * @category  Webkul
 * @package   Webkul_Marketplace
 * @author    Webkul
 * @copyright Copyright (c) Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */

namespace Genesisoft\MpMoipExt\Plugin\Model;

use Psr\Log\LoggerInterface;

/**
 * Marketplace Saleperpartner Model.
 *
 * @method \Webkul\Marketplace\Model\ResourceModel\Saleperpartner _getResource()
 * @method \Webkul\Marketplace\Model\ResourceModel\Saleperpartner getResource()
 */
class Saleperpartner
{
    public function before__call(\Magento\Framework\DataObject $review, $method, $args)
    {
        if ($method == 'setCommissionRate') {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/commission.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info('setCommissionRate start', ['---------------------------------------------------------------------------------------------------------']);
            $logger->info('', ['Method' => $method, 'Args' => $args, 'CommissionRateOld' => $review->getData('commission_rate')]);
            $debugBackTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            foreach ($debugBackTrace as $item) {
                $logger->info('Item', [@$item]);
            }
            $logger->info('setCommissionRate end', ['---------------------------------------------------------------------------------------------------------']);

            return [$method, $args];
        }
        return null;
    }
}
