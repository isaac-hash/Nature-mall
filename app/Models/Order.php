<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'printful_order_id',
        'total_price',
        'printful_price',
        'shipping_details',
        'shipping_method',
        'status',
        'payment_status',
    ];

    protected $casts = [
        'shipping_details' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

