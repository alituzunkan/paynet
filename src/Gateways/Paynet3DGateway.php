<?php

namespace Innovia\Paynet\Gateways;

use Illuminate\Support\Str;
use Innovia\Paynet\Traits\PaynetAPICalls;
use Innovia\Paynet\Contracts\PaynetGatewayContract;

class Paynet3DGateway implements PaynetGatewayContract
{
    use PaynetAPICalls;

    public function charge($data)
    {
        $tokenId = $data["token_id"];
        $sessionId = $data["session_id"];

        $res = $this->post("transaction/tds_charge", [
            "token_id" => $tokenId,
            "session_id" => $sessionId
        ]);

        return $res;
    }

    public function prepare($data)
    {
        $dataBefore = [
            "return_url" => "http://localhost:8000/paynet/standard/charge",
            "domain" => "www.cee.test",
            "reference_no" => Str::uuid()->toString()
        ];

        $dataAfter = array_merge($dataBefore, $data);
        $res = $this->post('transaction/tds_initial', $dataAfter);
        return $res;
    }
}
