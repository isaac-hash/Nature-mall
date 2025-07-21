<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('printful_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('printful_product_id');
            $table->unsignedBigInteger('variant_id')->unique();
            $table->string('name');
            $table->decimal('retail_price');
            $table->string('size');
            $table->string('color');
            $table->timestamps();

            $table->foreign('printful_product_id')
                ->references('id')
                ->on('printful_products')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printful_variants');
    }
};
