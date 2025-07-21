<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'printful_product_id',
        'printful_variant_id',
        'quantity',
        'retail_price'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(PrintfulVariant::class, 'printful_variant_id');
    }

    public function product()
    {
        return $this->belongsTo(PrintfulProduct::class, 'printful_product_id');
    }
}

