<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\PrintfulService;


class AdminOrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('items.variant') // Include variants for each order item
            // ->whereHas('items.variant', function ($query) {
            //     $query->whereNotNull('variant_id'); // Ensure variants are linked
            // })
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function syncStatus($id)
    {
        $order = Order::where('id', $id)->firstOrFail();

        if (! $order->printful_order_id) {
            return response()->json(['message' => 'No Printful order ID found for this order.'], 400);
        }

        $printful = app(PrintfulService::class);
        $response = $printful->getOrder($order->printful_order_id);

        if (!isset($response['result'])) {
            return response()->json([
                'message' => 'Unable to fetch status from Printful',
                'error' => $response
            ], 500);
        }

        $printfulStatus = $response['result']['status']; // e.g., "pending", "fulfilled", etc.

        // 🧭 Map Printful status to your internal status
        $statusMap = [
            'draft'       => 'draft',
            'pending'     => 'processing',
            'inprocess'   => 'pickup',
            'fulfilled'   => 'transit',
            'shipped'     => 'completed',
            'cancelled'   => 'cancelled',
            'failed'      => 'failed'
        ];

        $localStatus = $statusMap[$printfulStatus] ?? $printfulStatus;

        // Update local order status
        $order->status = $localStatus;
        $order->save();


        return response()->json([
            'message' => 'Order status synced with Printful',
            'laravel_status' => $order->status,
            'printful_status' => $printfulStatus,
            'printful_order' => $response['result'] // include for debugging or UI
        ]);
    }

    public function show($id)
    {
        $order = Order::with('items.variant')
            // ->whereHas('items.variant', function ($query) {
            //     $query->whereNotNull('variant_id'); // Ensure variants are linked
            // })
            ->findOrFail($id);
        $this->syncStatus($id);


        return response()->json($order);
    }

    
}
