<?php

namespace Innovia\Paynet\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Webkul\Paypal\Helpers\Ipn;
use Webkul\Checkout\Facades\Cart;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Innovia\Paynet\Contracts\PaynetGatewayContract;
use Webkul\Sales\Repositories\OrderRepository;

class StandardController extends Controller
{
    /**
     * OrderRepository object
     *
     * @var \Webkul\Sales\Repositories\OrderRepository
     */
    protected $orderRepository;
    protected $paymentGateway;

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Attribute\Repositories\OrderRepository  $orderRepository
     * @param  \Webkul\Paypal\Helpers\Ipn  $ipnHelper
     * @return void
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaynetGatewayContract $paynetGatewayContract
    ) {
        $this->orderRepository = $orderRepository;
        $this->paymentGateway = $paynetGatewayContract;
    }

    /**
     * Redirects to the paypal.
     *
     * @return \Illuminate\View\View
     */
    public function redirect()
    {
        $data = Validator::make(request()->all(), [
            "card_holder" => "required",
            "pan" => "required",
            "month" => "required",
            "year" => "required",
            "cvc" => "required"
        ]);

        if ($data->fails()) {
            return response()->json($data->failed(), 400);
        }

        $cart = Cart::toArray();
        $amount = $this->convertToValidAmount($cart["grand_total"]);


        $res = $this->paymentGateway->prepare([
            "amount" => $amount,
            "card_holder" => request('card_holder'),
            "pan" => request('pan'),
            "month" => request('month'),
            "year" => request('year'),
            "cvc" => request('cvc')
        ]);

        if ($res->getStatusCode() < 300) {
            return response()->json(json_decode($res->getBody())->post_url, 200);
        }

        return response("", 400);
    }

    /**
     * Cancel payment from paypal.
     *
     * @return \Illuminate\Http\Response
     */
    public function cancel()
    {
        session()->flash('error', 'Paynet payment has been canceled.');

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Success payment
     *
     * @return \Illuminate\Http\Response
     */
    public function success()
    {
        $order = $this->orderRepository->create(Cart::prepareDataForOrder());

        Cart::deActivateCart();

        session()->flash('order', $order);

        return redirect()->route('shop.checkout.success');
    }


    public function charge()
    {

        $res = $this->paymentGateway->charge(request()->all());

        if ($res->getStatusCode() >= 400) {
            return "Error";
        }

        $body = json_decode($res->getBody());

        if ($body->is_succeed) {
            return redirect()->route('paynet.standard.success');
        }

        return redirect()->route('paynet.standard.cancel');
    }

    private function convertToValidAmount($amount)
    {
        return number_format($amount, 2, ",", "");
    }
}
