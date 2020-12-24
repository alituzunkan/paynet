<?php

namespace Innovia\Paynet\Gateways;

use Innovia\Paynet\Contracts\PaynetGatewayContract;
use Innovia\Paynet\Traits\PaynetAPICalls;

class PaynetPaymentGateway implements PaynetGatewayContract
{
    use PaynetAPICalls;

    public function charge($data)
    {
    }

    public function prepare($data)
    {
    }
}
