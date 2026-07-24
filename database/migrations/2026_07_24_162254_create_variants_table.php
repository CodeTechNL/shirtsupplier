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
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->index();
            $table->string('title')->nullable();
            $table->string('sku')->nullable();
            $table->string('ean')->nullable();
            $table->string('article_code')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->integer('sort_order')->default(0);
            $table->decimal('price_excl', 10, 2)->default(0);
            $table->decimal('price_incl', 10, 2)->default(0);
            $table->decimal('old_price_excl', 10, 2)->default(0);
            $table->decimal('old_price_incl', 10, 2)->default(0);
            $table->string('stock_tracking')->nullable();
            $table->integer('stock_level')->default(0);
            $table->integer('weight')->default(0);
            $table->json('image')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
