<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Services\PrintfulService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\LoggableController;
use Illuminate\Validation\ValidationException;


class OrderController extends Controller
{
    use LoggableController;

    public function index()
    {
        $orders = Order::with('items.variant') // Include variants for each order item
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function show($id)
    {
        $order = Order::with('items.variant')
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json($order);
    }

     public function checkout(Request $request)
    {
        try {
            // 1. Validate the incoming request data
            $validated = $request->validate([
                'address1'        => 'required|string',
                'city'            => 'required|string',
                'zip'             => 'required|string',
                'country_code'    => 'required|string|size:2',
                'shipping_method' => 'required|string',
            ]);

            // 2. Get the authenticated user
            $user = Auth::user();

            // Check if user is authenticated
            if (!$user) {
                $this->logWarning('Checkout attempt by unauthenticated user.', [
                    'ip_address' => $request->ip(),
                    'request_data' => $request->all()
                ]);
                return response()->json(['message' => 'Authentication required.'], 401);
            }

            // 3. Retrieve cart items for the user, ensuring product and variant are loaded
            // Ensure 'product' and 'variant' relationships are correctly defined and loaded.
            $cartItems = Cart::with(['variant', 'product'])->where('user_id', $user->id)->get();

            // 4. Check if the cart is empty
            if ($cartItems->isEmpty()) {
                $this->logWarning('Checkout failed: Cart is empty.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return response()->json(['message' => 'Cart is empty.'], 400);
            }

            // 5. Map cart items to Printful compatible format for Printful API payload
            $items = $cartItems->map(function ($item) {
                // Ensure the variant relationship is loaded and has the external Printful variant ID
                $printfulExternalVariantId = $item->variant->variant_id ?? null; // Assuming PrintfulVariant has a 'variant_id' column for the external ID

                if (is_null($printfulExternalVariantId)) {
                    $this->logWarning('Cart item missing external Printful variant ID for Printful API payload.', [
                        'cart_item_id' => $item->id,
                        'product_id' => $item->product->id ?? 'N/A',
                        'product_name' => $item->product->name ?? 'N/A',
                        'variant_relationship_loaded' => !is_null($item->variant),
                    ]);
                    return null; // Return null to be filtered out
                }

                return [
                    'sync_variant_id' => $printfulExternalVariantId,
                    'quantity'        => $item->quantity,
                ];
            })->filter()->values()->all(); // Filter out nulls and re-index the array

            // 6. Check if any valid items remain after filtering for Printful payload
            if (empty($items)) {
                $this->logWarning('Checkout failed: No valid Printful items found in cart after filtering for API payload.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'original_cart_item_count' => $cartItems->count(),
                ]);
                return response()->json(['message' => 'Your cart contains items that cannot be processed for Printful. Please review your cart.'], 400);
            }

            // 7. Prepare the payload for Printful order submission (draft order)
            $payload = [
                'recipient' => [
                    'name'         => $user->name ?? 'Customer',
                    'address1'     => $validated['address1'],
                    'city'         => $validated['city'],
                    'zip'          => $validated['zip'],
                    'country_code' => $validated['country_code'],
                ],
                'items'    => $items,
                'shipping' => $validated['shipping_method'],
            ];

            // 8. Initialize Printful Service and submit the draft order
            $printful = app(PrintfulService::class);
            $response = $printful->submitOrder($payload); // This should create a draft order

            // 9. Handle Printful order submission failure
            if (!isset($response['result']['id'])) {
                $this->logError('Printful draft order submission failed.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'printful_response' => $response,
                    'payload_sent' => $payload,
                ]);
                return response()->json([
                    'message' => 'Failed to create draft order with Printful. Please try again later.',
                    'error' => $response
                ], 500);
            }

            // 10. Extract actual cost breakdown from Printful response
            $costs = $response['result']['costs'] ?? [];

            // 11. Create a local order record
            $order = Order::create([
                'user_id'           => $user->id,
                'printful_order_id' => $response['result']['id'], // Store Printful's draft order ID
                'shipping_method'   => $validated['shipping_method'],
                'shipping_details'  => json_encode($payload['recipient']),
                'total_price'       => $costs['total'] ?? 0, // Use actual total from Printful
                'status'            => 'pending', // Order is pending payment/confirmation
                'payment_status'    => 'pending' // Explicitly set payment status
            ]);

            // 12. Create order items for the local order
            foreach ($cartItems as $item) {
                // Ensure the 'product' relationship is loaded and the local 'id' exists
                $localPrintfulProductId = $item->product->id ?? null;

                // Ensure the 'variant' relationship is loaded and the local 'id' exists
                // THIS IS THE CRUCIAL CHANGE FOR THIS ERROR
                $localPrintfulVariantId = $item->variant->id ?? null;

                if (is_null($localPrintfulProductId)) {
                    $this->logError('Critical Checkout Error: Missing local Printful Product ID for a cart item during OrderItem creation.', [
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'cart_item_id' => $item->id,
                        'product_id_on_cart' => $item->printful_product_id ?? 'N/A',
                        'product_relationship_loaded' => !is_null($item->product),
                        'product_name_from_cart' => $item->product->name ?? 'N/A',
                    ]);
                    throw new \Exception("One or more products in your cart are missing required product information (local Printful Product ID). Please contact support.");
                }

                if (is_null($localPrintfulVariantId)) {
                    $this->logError('Critical Checkout Error: Missing local Printful Variant ID for a cart item during OrderItem creation.', [
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'cart_item_id' => $item->id,
                        'variant_id_on_cart' => $item->printful_variant_id ?? 'N/A', // The foreign key on cart
                        'variant_relationship_loaded' => !is_null($item->variant),
                        'product_name_from_cart' => $item->product->name ?? 'N/A',
                    ]);
                    throw new \Exception("One or more products in your cart are missing required variant information (local Printful Variant ID). Please contact support.");
                }

                OrderItem::create([
                    'order_id'            => $order->id,
                    'printful_product_id' => $localPrintfulProductId,
                    'printful_variant_id' => $localPrintfulVariantId, // Use the local 'id' of the PrintfulVariant
                    'quantity'            => $item->quantity,
                    'retail_price'        => $item->variant->retail_price
                ]);
            }

            // 13. Clear the user's cart after successful order creation
            Cart::where('user_id', $user->id)->delete();

            // Log successful checkout
            $this->logInfo('Checkout successful: Draft order created.', [
                'user_id' => $user->id,
                'order_id' => $order->id,
                'printful_order_id' => $order->printful_order_id,
                'total_price' => $order->total_price,
            ]);

            // 14. Return success response
            return response()->json([
                'message' => 'Order created and draft order submitted to Printful successfully. Awaiting payment confirmation.',
                'order_id' => $order->id,
                'printful_order_id' => $order->printful_order_id,
                'costs'             => $costs
            ], 201); // 201 Created

        } catch (ValidationException $e) {
            // Log validation errors
            $this->logWarning('Checkout validation failed.', [
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422); // 422 Unprocessable Entity
        } catch (\Exception $e) {
            // Log any other unexpected server errors
            $this->logError('Checkout failed due to server error.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => Auth::id(),
                'request_data' => $request->all(), // Be cautious with sensitive data here
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Server error during checkout. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirms payment for an order and triggers Printful order confirmation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmPayment(Request $request)
    {
        try {
            // 1. Validate the incoming request
            $request->validate([
                'order_id' => 'required|exists:orders,id',
            ]);

            // 2. Retrieve the order with its related items and variants
            $order = Order::with(['items.variant'])->findOrFail($request->order_id);

            // 3. Check if the order is already paid or submitted
            if ($order->payment_status !== 'pending') {
                $this->logWarning('Payment confirmation failed: Order already processed.', [
                    'order_id' => $order->id,
                    'current_payment_status' => $order->payment_status,
                    'user_id' => $order->user_id,
                ]);
                return response()->json(['message' => 'Order is already paid or submitted'], 400);
            }

            // 4. Update the local order's payment status to 'paid'
            $order->update(['payment_status' => 'paid']);
            $this->logInfo('Local order payment status updated to paid.', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
            ]);

            // 5. Initialize the Printful Service
            $printful = app(PrintfulService::class);

            // 6. Check if a Printful order ID exists for this order.
            if (empty($order->printful_order_id)) {
                $this->logError('Payment confirmation failed: No Printful draft order ID found.', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                ]);
                return response()->json([
                    'message' => 'No Printful draft order ID found for this order. Cannot confirm an order that does not exist on Printful.',
                    'order_id' => $order->id
                ], 400);
            }

            // 7. Attempt to confirm the existing Printful draft order using its ID
            $response = $printful->confirmOrder($order->printful_order_id);

            // 8. Handle the response from Printful
            if (isset($response['error']) || !isset($response['result']['id'])) {
                $this->logError('Printful order confirmation failed.', [
                    'order_id' => $order->id,
                    'printful_order_id' => $order->printful_order_id,
                    'user_id' => $order->user_id,
                    'printful_response' => $response,
                ]);
                // Optionally revert local payment status if Printful confirmation is critical
                $order->update(['payment_status' => 'failed_printful_confirmation']);
                return response()->json([
                    'message' => 'Printful order confirmation failed.',
                    'printful_error' => $response['error'] ?? 'Unknown Printful error',
                    'order_id' => $order->id
                ], 500);
            }

            // 9. If Printful confirmation was successful, update the local order's status
            $order->update([
                'status' => 'submitted_to_printful',
            ]);

            // Log successful Printful confirmation
            $this->logInfo('Printful draft order successfully confirmed.', [
                'order_id' => $order->id,
                'printful_order_id' => $order->printful_order_id,
                'user_id' => $order->user_id,
            ]);

            // 10. Return a success response
            return response()->json([
                'message' => 'Payment confirmed. Printful draft order successfully confirmed.',
                'order' => $order
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log validation errors
            $this->logWarning('Payment confirmation validation failed.', [
                'user_id' => Auth::id(), // Or get from order if available
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Log if order not found
            $this->logError('Payment confirmation failed: Order not found.', [
                'order_id_attempted' => $request->order_id,
                'error_message' => $e->getMessage(),
                'ip_address' => $request->ip(),
            ]);
            return response()->json(['message' => 'Order not found.'], 404);
        } catch (\Exception $e) {
            // Log any other unexpected server errors
            $this->logError('Payment confirmation failed due to server error.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'order_id' => $request->order_id ?? 'N/A',
                'user_id' => Auth::id() ?? 'N/A',
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Server error during payment confirmation. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }



}
