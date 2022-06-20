<?php
namespace Genesisoft\MpMoipExt\Model;

class MoipCcConfigProvider extends \Webkul\MpMoip\Model\MoipCcConfigProvider
{
    public function getConfig()
    {
        $config = [];
        foreach ($this->methodCodes as $code) {
            $config['payment'][$code]['ccavailabletypes'] = $this->getCcAvailableTypes();
            $config['payment'][$code]['years'] = $this->getYears();
            $config['payment'][$code]['months'] = $this->getMonths();
            $config['payment'][$code]['icons'] = $this->getIcons();
            $config['payment'][$code]['currency'] = $this->getCurrencyData();
            $config['payment'][$code]['interest_type'] = $this->getInterestType();
            $config['payment'][$code]['info_interest'] = $this->getInstallments();
            $config['payment'][$code]['publickey'] = $this->getPublicKey();
            $config['payment'][$code]['image_cvv'] = $this->getCvvImg();
            $config['payment'][$code]['allow_installments'] = $this->allowInstallments();
            $config['payment'][$code]['sprite_url'] = $this->getSpriteUrl();
            $config['payment'][$code]['allowed_months'] = $this->getAllowedMonths();
        }
        return $config;
    }

}
