<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'printful_product_id',
        'printful_variant_id',
        'quantity',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function product()
    {
        return $this->belongsTo(PrintfulProduct::class, 'printful_product_id', 'id');
    }
    public function variant()
    {
        return $this->belongsTo(PrintfulVariant::class, 'printful_variant_id');
    }

}
