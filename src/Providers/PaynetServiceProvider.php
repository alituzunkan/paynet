<?php

namespace Innovia\Paynet\Providers;


use Illuminate\Support\ServiceProvider;
use Innovia\Paynet\Contracts\PaynetGatewayContract;
use Innovia\Paynet\Gateways\Paynet3DGateway;

class PaynetServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        include dirname(__DIR__) . '/Http/Routes/routes.php';

        $this->loadViewsFrom(dirname(__DIR__) . '/Resources/views', 'paynet');
    }

    public function register()
    {

        $this->app->bind(PaynetGatewayContract::class, function ($app) {
            return new Paynet3DGateway;
        });

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/paymentmethods.php',
            'paymentmethods'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/system.php',
            'core'
        );
    }
}
