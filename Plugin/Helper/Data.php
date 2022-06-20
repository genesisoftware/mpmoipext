<?php
namespace Genesisoft\MpMoipExt\Plugin\Helper;

class Data
{
    /**
     * Create Moip Customer
     *
     * @param \Magento\Sales\Model\Order $order
     *
     * @return \Moip\Resource\Customer
     */
    public function beforeGetPhoneNumber($subject, $number, $returnCode = false)
    {
        return [str_replace(['(',')','-','.',''],'',$number), $returnCode];
    }

}

