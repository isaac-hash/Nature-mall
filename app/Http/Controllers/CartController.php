<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\PrintfulProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\LoggableController;

class CartController extends Controller
{
    use LoggableController;
    /**
     * Display a list of items in the authenticated user's cart.
     */
     public function index(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();

            // Check if user is authenticated
            if (!$user) {
                $this->logWarning('Cart index access attempt by unauthenticated user.', [
                    'ip_address' => $request->ip(),
                ]);
                return response()->json(['message' => 'Authentication required.'], 401);
            }

            // Load user's cart items, eager-loading the associated 'product' (PrintfulProduct)
            // This 'with('product')' ensures the full product instance is retrieved.
            $cartItems = Cart::with('product')
                ->where('user_id', $user->id)
                ->get();

            // Check if the cart is empty
            if ($cartItems->isEmpty()) {
                $this->logInfo('Cart index accessed: User cart is empty.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return response()->json(['message' => 'Your cart is empty.'], 200); // 200 OK for empty cart is common
            }

            // Log successful retrieval of cart items
            $this->logInfo('Cart items retrieved successfully.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'item_count' => $cartItems->count(),
                'ip_address' => $request->ip(),
            ]);

            // Return the cart items. Laravel will automatically serialize the 'product' relationship
            // as a nested object within each cart item's JSON representation.
            return response()->json($cartItems, 200);

        } catch (\Exception $e) {
            // Log any unexpected server errors
            $this->logError('Failed to retrieve cart items due to server error.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => Auth::id(), // Use Auth::id() as fallback if $user is null in catch
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Server error while retrieving cart. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a product to the cart or update its quantity.
     */
    public function store(Request $request)
    {
        // dd($request->all());

        $validated = $request->validate([
            'printful_product_id' => 'required|exists:printful_products,id',
            'printful_variant_id' => 'required|exists:printful_variants,id',
            'quantity'            => 'required|integer|min:1',
        ]);
        
        // dd($validated);


        $cartItem = Cart::where('user_id', Auth::id())
            ->where('printful_variant_id', $validated['printful_variant_id']) // match by variant!
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $validated['quantity'];
            $cartItem->save();
        } else {
            $cartItem = Cart::create([
                'user_id'             => Auth::id(),
                'printful_product_id' => $validated['printful_product_id'],
                'printful_variant_id' => $validated['printful_variant_id'],
                'quantity'            => $validated['quantity'],
            ]);
        }


        return response()->json([
            'message' => 'Product added to cart successfully.',
            'data'    => $cartItem->load('product'),
        ]);
    }

    /**
     * Update the quantity of a cart item.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cartItem = Cart::where('user_id', Auth::id())->findOrFail($id);
        $cartItem->quantity = $validated['quantity'];
        $cartItem->save();

        return response()->json([
            'message' => 'Cart item updated.',
            'data'    => $cartItem->load('product'),
        ]);
    }

    /**
     * Remove an item from the cart.
     */
    public function destroy($id)
    {
        $cartItem = Cart::where('user_id', Auth::id())->findOrFail($id);
        $cartItem->delete();

        return response()->json(['message' => 'Item removed from cart.']);
    }
}
