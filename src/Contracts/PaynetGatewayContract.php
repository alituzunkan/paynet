<?php

namespace Innovia\Paynet\Contracts;

interface PaynetGatewayContract
{
    public function charge($data);

    public function prepare($data);
}
