<?php
namespace Genesisoft\MpMoipExt\Model;

class MoipBoletoConfigProvider Extends \Webkul\MpMoip\Model\MoipBoletoConfigProvider
{
    public function getConfig()
    {
        return [
            'payment' => [
                'mpmoipboleto' => [
                    'checkout_instruction' =>  $this->getCheckoutInstruction(),
                    'expiration_message' => $this->getExpirationMessage(),
                ],
            ],
        ];
    }
}
