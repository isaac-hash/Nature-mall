<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PrintfulService
{
    protected $client;

    public function __construct()
    {
        $this->client = Http::withToken(config('printful.api_key'))
            ->baseUrl(config('printful.base_url'));
    }

    public function getProducts()
    {
        return $this->client->get('/store/products')->json();
    }

    public function getProduct($id)
    {
        return $this->client->get("/store/products/{$id}")->json();
    }

    public function syncProduct($data)
    {
        return $this->client->post('/store/products', $data)->json();
    }

    public function getShippingRates(array $data)
    {
        return $this->client->post('/shipping/rates', $data)->json();
    }


    public function submitOrder($order)
    {
        return $this->client->post('/orders', $order)->json();
    }

    public function confirmOrder($id)
    {
        return $this->client->post("/orders/{$id}/confirm")->json();
    }


    public function getOrder($id)
    {
        return $this->client->get("/orders/{$id}")->json();
    }

    public function getOrders()
    {
        return $this->client->get('/orders')->json();
    }
}
