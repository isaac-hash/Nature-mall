<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintfulProduct extends Model
{
    protected $fillable = ['printful_id', 'name', 'thumbnail', 'category_id', 'instock_status'];

    public function variants()
    {
        return $this->hasMany(PrintfulVariant::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tags::class, 'product_tag', 'printful_product_id', 'tag_id');
    }

    // app/Models/PrintfulProduct.php

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

}
