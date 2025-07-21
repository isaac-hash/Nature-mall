<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Traits\LoggableController; // Add this import


class StripePaymentController extends Controller
{
    use LoggableController; // Add this line

    /**
     * Initiates a Stripe Checkout session for an order.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkout(Request $request)
    {
        try {
            // Validate the incoming request for order_id
            $request->validate([
                'order_id' => 'required|exists:orders,id',
            ]);

            // Get the authenticated user
            $user = Auth::user();
            if (!$user) {
                $this->logWarning('Stripe checkout attempt by unauthenticated user.', [
                    'ip_address' => $request->ip(),
                    'order_id_attempted' => $request->order_id,
                ]);
                return response()->json(['message' => 'Authentication required.'], 401);
            }

            // Retrieve the order with its items and variants
            $order = Order::with('items.variant')->findOrFail($request->order_id);

            // Log if using fake Stripe for testing
            if (env('USE_STRIPE_FAKE', false)) {
                $this->logInfo('Using fake Stripe checkout for order.', [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'url' => route('mock.stripe.checkout', ['order_id' => $order->id])
                ]);
            }

            // Set Stripe API key
            Stripe::setApiKey(config('services.stripe.secret'));

            // Prepare line items for Stripe Checkout
            $lineItems = $order->items->map(function ($item) {
                return [
                    'price_data' => [
                        'currency' => 'usd', // Ensure this matches your Stripe currency
                        'product_data' => ['name' => $item->variant->name ?? 'Product Item'], // Fallback name
                        'unit_amount' => intval($item->retail_price * 100), // Amount in cents
                    ],
                    'quantity' => $item->quantity,
                ];
            })->toArray();

            // Check if line items are empty (e.g., if order items were somehow invalid)
            if (empty($lineItems)) {
                $this->logError('Stripe checkout failed: No valid line items for order.', [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                ]);
                return response()->json(['message' => 'Cannot create Stripe session: No valid items in order.'], 400);
            }

            // Create Stripe Checkout Session
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                // Use absolute URLs for success and cancel to avoid issues
                'success_url' => url('/payment-success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/payment-cancelled'),
                'metadata' => [
                    'order_id' => $order->id,
                    'user_id' => $user->id, // Add user_id to metadata for easier tracking
                ]
            ]);

            // Log successful Stripe session creation
            $this->logInfo('Stripe Checkout session created.', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'stripe_session_id' => $session->id,
                'checkout_url' => $session->url,
            ]);

            return response()->json(['url' => $session->url]);

        } catch (ValidationException $e) {
            // Log validation errors
            $this->logWarning('Stripe checkout validation failed.', [
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Log Stripe API specific errors
            $this->logError('Stripe API error during checkout session creation.', [
                'order_id' => $request->order_id ?? 'N/A',
                'user_id' => Auth::id() ?? 'N/A',
                'stripe_error_code' => $e->getError() ? $e->getError()->code : null,
                'stripe_error_message' => $e->getMessage(),
                'http_status' => $e->getHttpStatus(),
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Payment initiation failed due to Stripe error. Please try again.',
                'error_details' => $e->getMessage()
            ], 500);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Log if order not found
            $this->logError('Stripe checkout failed: Order not found.', [
                'order_id_attempted' => $request->order_id,
                'error_message' => $e->getMessage(),
                'ip_address' => $request->ip(),
            ]);
            return response()->json(['message' => 'Order not found.'], 404);
        } catch (\Exception $e) {
            // Log any other unexpected server errors
            $this->logError('Stripe checkout failed due to server error.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'order_id' => $request->order_id ?? 'N/A',
                'user_id' => Auth::id() ?? 'N/A',
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Server error during payment initiation. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handles Stripe webhook events.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function webhook(Request $request)
    {
        // Log the raw webhook payload for debugging
        $this->logDebug('Stripe Webhook received.', [
            'payload' => $request->all(),
            'ip_address' => $request->ip(),
            'signature' => $request->header('Stripe-Signature'),
        ]);

        // Handle fake Stripe webhook for testing
        if (env('USE_STRIPE_FAKE', false)) {
            $order = Order::find($request->order_id);

            if ($order && $order->payment_status !== 'paid') {
                $order->payment_status = 'paid';
                $order->save();
                $this->logInfo('Fake Stripe webhook processed: Order marked as paid.', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                ]);
            } else if ($order && $order->payment_status === 'paid') {
                $this->logInfo('Fake Stripe webhook received for already paid order.', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                ]);
            } else {
                $this->logWarning('Fake Stripe webhook received for non-existent or unidentifiable order.', [
                    'order_id_in_request' => $request->order_id,
                ]);
            }

            return response()->json(['message' => 'Fake webhook successful']);
        }

        // Real webhook logic
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
            $this->logInfo('Stripe Webhook event constructed successfully.', [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'object_id' => $event->data->object->id ?? 'N/A',
            ]);
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            $this->logError('Stripe Webhook Error: Invalid payload.', [
                'error_message' => $e->getMessage(),
                'payload_start' => substr($payload, 0, 200), // Log beginning of payload
                'ip_address' => $request->ip(),
            ]);
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            $this->logError('Stripe Webhook Error: Invalid signature.', [
                'error_message' => $e->getMessage(),
                'signature_header' => $sigHeader,
                'ip_address' => $request->ip(),
            ]);
            return response('Invalid signature', 400);
        } catch (\Exception $e) {
            // Catch any other general exceptions during event construction
            $this->logError('Stripe Webhook Error: Unexpected error during event construction.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip_address' => $request->ip(),
            ]);
            return response('Internal server error during webhook processing', 500);
        }

        // Handle specific event types
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $orderId = $session->metadata->order_id ?? null;
            $stripeSessionId = $session->id;

            $this->logInfo('Stripe checkout.session.completed event received.', [
                'stripe_session_id' => $stripeSessionId,
                'order_id_from_metadata' => $orderId,
                'payment_status' => $session->payment_status, // Should be 'paid'
            ]);

            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    if ($order->payment_status !== 'paid') {
                        $order->payment_status = 'paid';
                        $order->save();
                        $this->logInfo('Order payment status updated to paid via Stripe webhook.', [
                            'order_id' => $order->id,
                            'user_id' => $order->user_id,
                            'stripe_session_id' => $stripeSessionId,
                        ]);
                    } else {
                        $this->logInfo('Stripe webhook received for already paid order (checkout.session.completed).', [
                            'order_id' => $order->id,
                            'user_id' => $order->user_id,
                            'stripe_session_id' => $stripeSessionId,
                        ]);
                    }
                } else {
                    $this->logWarning('Stripe webhook (checkout.session.completed): Order not found for ID from metadata.', [
                        'order_id_from_metadata' => $orderId,
                        'stripe_session_id' => $stripeSessionId,
                    ]);
                }
            } else {
                $this->logWarning('Stripe webhook (checkout.session.completed): No order_id in session metadata.', [
                    'stripe_session_id' => $stripeSessionId,
                ]);
            }
        }
        // You can add more event types here as needed, e.g., 'payment_intent.succeeded', 'charge.succeeded' etc.

        return response()->json(['received' => true]);
    }
}

