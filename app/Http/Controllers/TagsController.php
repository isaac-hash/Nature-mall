<?php

namespace App\Http\Controllers;

use App\Models\Tags;
use App\Http\Requests\StoreTagsRequest;
use App\Http\Requests\UpdateTagsRequest;

class TagsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tags = Tags::all();
        return response()->json($tags);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTagsRequest $request)
    {
        $validated = $request->validated();

        // Normalize the name for case-insensitive comparison
        $normalized = strtolower($validated['name']);

        // Check for existing tag ignoring case
        $existingTag = Tags::whereRaw('LOWER(name) = ?', [$normalized])->first();

        if ($existingTag) {
            return response()->json([
                'message' => 'Tag already exists (case-insensitive match).',
                'tag' => $existingTag
            ], 200);
        }

        // Save the original casing as inputted by user
        $tag = Tags::create([
            'name' => $validated['name'],
        ]);

        return response()->json($tag, 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(Tags $tags)
    {
        return response()->json($tags);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreTagsRequest $request, Tags $tags)
    {
        $tags->update($request->validated());
        return response()->json($tags);
    }

    /**
     * Remove the specified resource from storage.
     */
   public function destroy(Tags $tags)
{
    
    $tags->delete();

    return response()->json(["message" => "Tag deleted successfully"], 200);
}



}
