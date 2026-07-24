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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lightspeed_id')->nullable()->unique();
            $table->string('item_group');
            $table->string('item_action');
            $table->string('language');
            $table->string('address');
            $table->string('format')->default('json');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['item_group', 'item_action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
