<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product_images extends Model
{
    /** @use HasFactory<\Database\Factories\ProductImagesFactory> */
    use HasFactory;
    protected $fillable = [
        'product_id',
        'image_url',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class);
    }
}
