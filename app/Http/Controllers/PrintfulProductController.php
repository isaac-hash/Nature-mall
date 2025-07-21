<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\PrintfulProduct;
use App\Models\PrintfulVariant;
use App\Services\PrintfulService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Traits\LoggableController;

class PrintfulProductController extends Controller
{
    use LoggableController;
    protected $printful;

    public function __construct(PrintfulService $printful)
    {
        $this->printful = $printful;
    }

    public function index(): JsonResponse
    {
        $syncResponse = $this->sync();
        $message = "null";
        if ($syncResponse->getStatusCode() === 200) {
            $message = 'Products synced successfully';
        }
        $products = PrintfulProduct::with(['variants', 'category', 'tags'])->get();
        return response()->json([
            "products" => $products,
            "message" => $message,
        ]);
    }

    public function show($id): JsonResponse
    {
        $product = PrintfulProduct::with(['variants', 'category', 'tags'])->find($id);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json($product);
    }


    public function sync(): JsonResponse
    {
        $response = $this->printful->getProducts();
        // DEBUG: Dump the response before we proceed
        if (!isset($response['result']) || !is_array($response['result'])) {
            return response()->json([
                'error' => 'Unexpected response from Printful',
                'response' => $response,
            ], 500);
        }

        foreach ($response['result'] as $item) {
            $product = PrintfulProduct::updateOrCreate(
                ['printful_id' => $item['id']],
                ['name' => $item['name'], 'thumbnail' => $item['thumbnail_url']]
            );

            $remoteProduct = $this->printful->getProduct($item['id']);

            foreach ($remoteProduct['result']['sync_variants'] as $variant) {
                $printfulCost = $variant['printProductModel']['printfulPrice'] ?? null;
                PrintfulVariant::updateOrCreate(
                    ['variant_id' => $variant['id']],
                    [
                        'printful_product_id' => $product->id,
                        'name' => $variant['name'],
                        'retail_price' => $variant['retail_price'],
                        'printful_price' => $printfulCost,

                        'size' => $variant['size'] ?? null,
                        'color' => $variant['color'] ?? null,
                    ]
                );
            }
        }

        return response()->json(['message' => 'Products synced successfully', 'products' => PrintfulProduct::with('variants')->get()]);
    }

    public function createViaApi(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'thumbnail' => 'required|url',
            'variant_id' => 'required|integer',
            'retail_price' => 'required',
            'design_url' => 'required|url',
        ]);

        $payload = [
            'sync_product' => [
                'name' => $data['name'],
                'thumbnail' => $data['thumbnail'],
            ],
            'sync_variants' => [
                [
                    'variant_id' => $data['variant_id'],
                    'retail_price' => $data['retail_price'],
                    'files' => [
                        ['url' => $data['design_url']],
                    ]
                ]
            ]
        ];

        $response = $this->printful->syncProduct($payload);

        return response()->json($response);
    }

    // public function deleteViaApi($id)
    // {
    //     $response = $this->printful->deleteProduct($id);

    //     if ($response['code'] === 200) {
    //         return response()->json(['message' => 'Product deleted successfully']);
    //     }

    //     return response()->json(['error' => 'Failed to delete product'], 500);
    // }

    public function update(ProductRequest $request, $id)
    {
        try {
            // 1. Validate the incoming request data
            $validated = $request->validated();

            // 2. Find the product by its ID
            $printfulProduct = PrintfulProduct::find($id);

            // 3. Check if the product exists
            if (!$printfulProduct) {
                $this->logWarning('Product update failed: Product not found.', [
                    'product_id_attempted' => $id,
                    'user_id' => Auth::id(), // Log the user who attempted the update
                    'ip_address' => $request->ip(),
                ]);
                return response()->json(['message' => 'Product not found.'], 404); // 404 Not Found
            }

            // 4. Update product attributes
            $printfulProduct->update($validated);

            // 5. Sync tags if 'tag_ids' are provided in the request
            if (isset($validated['tag_ids'])) {
                $printfulProduct->tags()->sync($validated['tag_ids']);
                $this->logInfo('Product tags synced.', [
                    'product_id' => $printfulProduct->id,
                    'tags_synced' => $validated['tag_ids'],
                ]);
            }

            // Log successful product update
            $this->logInfo('Product updated successfully.', [
                'product_id' => $printfulProduct->id,
                'product_name' => $printfulProduct->name, // Assuming 'name' field exists
                'user_id' => Auth::id(),
                'ip_address' => $request->ip(),
                'updated_fields' => array_keys($validated), // Log which fields were updated
            ]);

            // 6. Return success response with the updated product (loaded with relationships)
            return response()->json([
                'message' => 'Product updated successfully.',
                'product' => $printfulProduct->load(['category', 'tags']),
            ], 200); // 200 OK

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log validation errors
            $this->logWarning('Product update validation failed.', [
                'product_id_attempted' => $id,
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
            $this->logError('Product update failed due to server error.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'product_id_attempted' => $id,
                'user_id' => Auth::id(),
                'request_data' => $request->all(), // Be cautious with sensitive data here
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Server error during product update. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500); // 500 Internal Server Error
        }
    }


   

}

