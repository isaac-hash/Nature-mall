<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductReviewController extends Controller
{
    /**
     * Display all reviews for a specific product.
     */
    public function index($productId)
    {
        $reviews = ProductReview::with(['user'])
            ->where('product_id', $productId)
            ->get();

        return response()->json($reviews, 200);
    }

    /**
     * Store a newly created review for a product.
     */
    public function store(Request $request, $productId)
    {
        $validated = $request->validate([
            "rating" => "required|integer|min:1|max:5",
            "review" => "nullable|string|max:1000",
        ]);

        $validated['product_id'] = $productId;
        $validated['user_id'] = Auth::id();

        $review = ProductReview::create($validated)->load('user');

        return response()->json([
            'message' => 'Review created successfully',
            'data' => $review
        ], 201);
    }

    /**
     * Display a specific review with product details.
     */
    public function show($productId, $reviewId)
    {
        $review = ProductReview::with(['user'])
            ->where('product_id', $productId)
            ->find($reviewId);

        if (!$review) {
            return response()->json(['error' => 'Review not found'], 404);
        }

        return response()->json($review, 200);
    }

    /**
     * Update the specified review if owned by the authenticated user.
     */
    public function update(Request $request, $productId, $reviewId)
    {
        $review = ProductReview::where('product_id', $productId)
            ->where('id', $reviewId)
            ->firstOrFail();

        if ($review->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            "rating" => "integer|min:1|max:5",
            "review" => "nullable|string|max:1000",
        ]);

        $review->update($validated);
        $review->load('user');
        
        return response()->json([
            'message' => 'Review updated successfully',
            'data' => $review
        ], 200);
    }

    /**
     * Remove the specified review if owned by the authenticated user.
     */
    public function destroy($productId, $reviewId)
    {
        $review = ProductReview::where('product_id', $productId)
            ->where('id', $reviewId)
            ->firstOrFail();

        if ($review->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully'], 200);
    }
}
