<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Category;
use App\Models\Tags;
use App\Models\Product_images;
use App\Models\Product_tag;

class Products extends Model
{
    /** @use HasFactory<\Database\Factories\ProductsFactory> */
    use HasFactory;
    protected $fillable = [
        "name",
        "description",
        "price",
        "stock",
        "category_id",
        "image_url",

    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsTo
    {
        return $this->belongsTo(Tags::class, 'tag_id');
    }
    public function tag(): HasMany
    {
        return $this->hasMany(Product_tag::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(Product_images::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(Cart::class);
    }
}
