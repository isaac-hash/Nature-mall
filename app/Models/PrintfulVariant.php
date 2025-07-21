<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintfulVariant extends Model
{
    protected $fillable = ['printful_product_id', 'variant_id', 'name', 'retail_price', 'printful_price', 'size', 'color'];

    public function product()
    {
        return $this->belongsTo(PrintfulProduct::class);
    }
}
