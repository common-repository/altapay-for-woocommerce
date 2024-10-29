<?php

namespace Altapay\Api\Ecommerce;

use Altapay\Response\Embeds\Address;
class ResponseInfo extends \Altapay\Api\Ecommerce\Callback
{
    /**
     * @return Address|null
     * @throws \Exception
     */
    public function getRegisteredAddress()
    {
        $response = $this->call();
        $registeredAddress = null;
        if (isset($response->Transactions[0]->CustomerInfo)) {
            $registeredAddress = $response->Transactions[0]->CustomerInfo->RegisteredAddress;
        }
        return $registeredAddress;
    }
}
