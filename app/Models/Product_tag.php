<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Products;
use App\Models\Tags;
use App\Models\PrintfulProduct;

class Product_tag extends Model
{
    /** @use HasFactory<\Database\Factories\ProductTagFactory> */
    use HasFactory;
    protected $fillable = [
        'product_id',
        'printful_product_id',
        'tag_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class);
    }
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tags::class);
    }

    public function printfulProduct(): BelongsTo
    {
        return $this->belongsTo(PrintfulProduct::class);
    }

}
