<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // 🔥 Drop old foreign key constraint on product_id
            $table->dropForeign(['product_id']);

            // ✅ Rename column to match Printful model
            $table->renameColumn('product_id', 'printful_product_id');
        });

        Schema::table('carts', function (Blueprint $table) {
            // 🔁 Re-add foreign key constraint to new table
            $table->foreign('printful_product_id')
                  ->references('id')
                  ->on('printful_products')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['printful_product_id']);
            $table->renameColumn('printful_product_id', 'product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }
};
