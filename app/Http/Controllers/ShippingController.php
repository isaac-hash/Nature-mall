<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\PrintfulService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Traits\LoggableController;


class ShippingController extends Controller
{
    use LoggableController;
     /**
     * Calculates and returns shipping rates from Printful based on cart items and recipient address.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // 1. Validate the incoming request for recipient address details
            $validated = $request->validate([
                'address1'     => 'required|string',
                'city'         => 'required|string',
                'zip'          => 'required|string',
                'country_code' => 'required|string|size:2', // ISO 3166-1 alpha-2 (e.g., NG, US, GB)
            ]);

            // 2. Get the authenticated user
            $user = Auth::user();

            // Check if user is authenticated
            if (!$user) {
                $this->logWarning('Shipping rate calculation attempt by unauthenticated user.', [
                    'ip_address' => $request->ip(),
                    'request_data' => $request->all()
                ]);
                return response()->json(['message' => 'Authentication required.'], 401);
            }

            // 3. Load user's cart items
            $cartItems = Cart::with('product', 'variant') // Ensure 'variant' is also loaded if printful_variant_id is on it
                ->where('user_id', $user->id)
                ->get();

            // 4. Check if the cart is empty
            if ($cartItems->isEmpty()) {
                $this->logWarning('Shipping rate calculation failed: Cart is empty.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return response()->json(['message' => 'Your cart is empty.'], 400);
            }

            // 5. Prepare shipping API payload items
            // Filter out items without a valid printful_variant_id
            $items = $cartItems->map(function ($item) {
                // Assuming printful_variant_id is directly on the Cart item or its variant relationship
                return [
                    'variant_id' => $item->printful_variant_id ?? $item->variant->variant_id ?? null,
                    'quantity'   => $item->quantity
                ];
            })->filter(fn ($i) => !empty($i['variant_id']))->values()->all(); // Filter out null variant_ids and re-index

            // 6. Check if any valid items remain after filtering
            if (empty($items)) {
                $this->logWarning('Shipping rate calculation failed: No valid Printful variant IDs found in cart.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'cart_item_count' => $cartItems->count(),
                ]);
                return response()->json(['message' => 'No valid products in your cart for shipping calculation.'], 400);
            }

            // 7. Construct the full payload for Printful shipping rate API
            $payload = [
                'recipient' => $validated,
                'items'     => $items,
            ];

            // 8. Call Printful API to get shipping rates
            $printful = app(PrintfulService::class);
            $rates = $printful->getShippingRates($payload);

            // 9. Handle Printful API response
            if (!isset($rates['result'])) {
                $this->logError('Printful shipping rate calculation failed.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'printful_response' => $rates,
                    'payload_sent' => $payload,
                    'ip_address' => $request->ip(),
                ]);
                return response()->json([
                    'message' => 'Failed to retrieve shipping rates from Printful.',
                    'error' => $rates
                ], 500);
            }

            // Log successful shipping rate retrieval
            $this->logInfo('Shipping rates retrieved successfully from Printful.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'shipping_destination' => $validated['country_code'] . ', ' . $validated['city'],
                'number_of_rates' => count($rates['result'] ?? []),
                'ip_address' => $request->ip(),
            ]);

            // 10. Return success response with rates
            return response()->json([
                'mode'  => 'printful', // Indicates the source of the rates
                'rates' => $rates['result'] ?? [],
                'raw'   => $rates // Include raw response for debugging/transparency
            ], 200);

        } catch (ValidationException $e) {
            // Log validation errors
            $this->logWarning('Shipping rate calculation validation failed.', [
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Validation failed for shipping address.',
                'errors' => $e->errors()
            ], 422); // 422 Unprocessable Entity
        } catch (\Exception $e) {
            // Log any other unexpected server errors
            $this->logError('Shipping rate calculation failed due to server error.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => Auth::id(),
                'request_data' => $request->all(), // Be cautious with sensitive data here
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Server error during shipping rate calculation. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
}
