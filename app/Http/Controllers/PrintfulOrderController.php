<?php

namespace App\Http\Controllers;

use App\Models\PrintfulOrder;
use App\Services\PrintfulService;
use Illuminate\Http\Request;

class PrintfulOrderController extends Controller
{
    protected $printful;

    public function __construct(PrintfulService $printful)
    {
        $this->printful = $printful;
    }

    public function index()
    {
        $orders = PrintfulOrder::with('user')->latest()->get();
        return response()->json($orders);
    }

    public function show($id)
    {
        $order = $this->printful->getOrder($id);
        return response()->json($order);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'recipient' => 'required|array',
            'items' => 'required|array',
        ]);

        $response = $this->printful->submitOrder($validated);

        PrintfulOrder::create([
            'user_id' => auth()->id,
            'printful_order_id' => $response['result']['id'],
            'status' => $response['result']['status'],
        ]);

        return response()->json(['message' => 'Order placed successfully', 'order' => $response]);
    }
}

