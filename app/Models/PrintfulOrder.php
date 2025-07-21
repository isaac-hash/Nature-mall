<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class PrintfulOrder extends Model
{
    protected $fillable = ['user_id', 'printful_order_id', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
