<?php

namespace App\Http\Controllers;

// use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Products;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // return "index";
        return Products::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        //
        // return "store";
        // $product = Products::create($request->validated());
        $validated = $request->validated();
        // dd($validated);
        $product = Products::create($validated);
        // return response()->json($product, 201);
        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Products $product)
    {
        //
        // return "show";
        $product = Products::findOrFail($product->id);
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Products $product)
    {
        //
        // return "update";
        $validated = $request->validated();
        $product = Products::findOrFail($product->id);
        // Assuming you want to update the product with the validated data

        // $product->fill($validated);
        // $product->save();


        // Alternatively, if you want to use the update method:
        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product
        ], 200);

        
        
        // $product->update($request->validated());
        // return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Products $product)
    {
        $product = Products::findorFail($product->id);
        $product->delete();
        return response()->json(['message' => 'Deleted successfully'], 200);

        // return "destroy";
        
    }
}
