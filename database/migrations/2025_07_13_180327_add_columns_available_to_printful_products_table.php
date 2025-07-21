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
        Schema::table('printful_products', function (Blueprint $table) {
            $table->string('instock_status')->default('available')->after('thumbnail');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('printful_products', function (Blueprint $table) {
            $table->dropColumn('instock_status');
        });
    }
};
