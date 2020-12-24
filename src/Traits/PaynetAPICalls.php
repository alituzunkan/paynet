<?php

namespace Innovia\Paynet\Traits;


use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;


trait PaynetAPICalls
{

    protected $baseUri = "https://pts-api.paynet.com.tr/v2/";
    protected $secretKey = "sck_mEjMxR0W-OKHERvFQLH6LrB2jFBI";
    protected $publicKey = "pbk_Hru6LL8bxBjyy3ZCR3gNW-lFhWIW";

    protected function client()
    {
        return new Client([
            "base_uri" => $this->baseUri,
            'headers' => ['Authorization' => "Basic " . $this->secretKey]
        ]);
    }


    protected function post(string $uri, array $data = [], array $options = []): ResponseInterface
    {

        $params = array_merge(
            [
                "form_params" => $data
            ],
            $options
        );

        return $this->client()->post($uri, $params);
    }


    protected function get(string $uri, array $options = []): ResponseInterface
    {
        return $this->client()->get($uri, $options);
    }


    protected function put(string $uri, array $data = [], array $options = []): ResponseInterface
    {

        $params = array_merge(
            [
                "form_params" => $data
            ],
            $options
        );

        return $this->client()->put($uri, $params);
    }

    protected function delete(string $uri, array $options = []): ResponseInterface
    {

        return $this->client()->delete($uri, $options);
    }
}
