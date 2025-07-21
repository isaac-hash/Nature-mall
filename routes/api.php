<?php

use App\Http\Controllers\AdminMetricsController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Products;
use App\Models\Tags;
use App\Models\Category;
use App\Models\Product_tag;
use App\Models\Product_images;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\ProductTagController;
use App\Http\Controllers\ProductImagesController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PrintfulOrderController;
use App\Http\Controllers\PrintfulProductController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Http;



// use App\Http\Controllers\AuthController;



Route::middleware(["auth:sanctum"])->group(function(){
    
    Route::post('/products/create-printful', [PrintfulProductController::class, 'createViaApi']);
    
    Route::post('/logout', [AuthController::class, 'Logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::apiResource('cart', CartController::class);
    Route::get('/products', [PrintfulProductController::class, 'index']);
    Route::post('/products/sync', [PrintfulProductController::class, 'sync']);
    Route::get('/shipping-options', [ShippingController::class, 'index']);

    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::post('/confirm-payment', [OrderController::class, 'confirmPayment']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);

    Route::get('products/{product}/reviews', [ProductReviewController::class, 'index']);
    Route::post('products/{product}/reviews', [ProductReviewController::class, 'store']);
    Route::get('products/{product}/reviews/{review}', [ProductReviewController::class, 'show']);
    Route::put('products/{product}/reviews/{review}', [ProductReviewController::class, 'update']);
    Route::delete('products/{product}/reviews/{review}', [ProductReviewController::class, 'destroy']);

    Route::post('/stripe/checkout', [StripePaymentController::class, 'checkout']);
    Route::post('/stripe/webhook', [StripePaymentController::class, 'webhook']);

    Route::get('/mock/stripe/checkout', function (Request $request) {
        $orderId = $request->order_id;

        // ✅ Call the controller method directly to avoid HTTP timeout
        $controller = new StripePaymentController;
        $fakeRequest = new Request(['order_id' => $orderId]);
        $response = $controller->webhook($fakeRequest);

        return response()->json([
            'message' => 'success',
            'order_id' => $orderId,
            'webhook_response' => $response->getData(true)
        ]);
    })->name('mock.stripe.checkout');

    
});

Route::middleware(['admin', 'auth:sanctum'])->group(function(){
    Route::get('/admin', function (Request $request) {
        return response()->json(['message' => 'Welcome, Admin!']);
    });
    
    Route::put('products/{id}', [PrintfulProductController::class, 'update']);
    
    // Route::get('/admin/metrics/summary', [AdminMetricsController::class, 'summary']);
    // Route::get('/admin/metrics/profit', [AdminMetricsController::class, 'profit']);
    Route::get('/admin/metrics', [AdminMetricsController::class, 'sales']);
    Route::get('/admin/orders', [AdminOrderController::class, 'index']);
    Route::get('/admin/orders/{id}', [AdminOrderController::class, 'show']);
    Route::get('/admin/orders/{id}/status', [AdminOrderController::class, 'syncStatus']);
    
    Route::patch('/users/{id}/make-admin', [UserController::class, 'promote']);
    Route::apiResource('tags', TagsController::class);
    // Route::delete('tags/{id}', TagsController::class, 'destroyTagWithRelations');
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('product-tags', ProductTagController::class);
    Route::apiResource('product-images', ProductImagesController::class);

    Route::get('/users', [AuthController::class, 'User']);
});

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, World!']);
});

Route::post('/register', [AuthController::class, 'Register']);


Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.send');



Route::post('/login', [AuthController::class, 'Login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);



