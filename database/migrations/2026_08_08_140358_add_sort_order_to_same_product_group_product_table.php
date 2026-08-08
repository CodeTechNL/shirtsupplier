<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('same_product_group_product', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('product_id');

            $table->index(['same_product_group_id', 'sort_order'], 'group_sort_order_index');
        });

        $this->backfillSortOrder();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('same_product_group_product', function (Blueprint $table) {
            $table->dropIndex('group_sort_order_index');
            $table->dropColumn('sort_order');
        });
    }

    /**
     * Give every existing group a deterministic starting order so appended
     * products land at the end instead of tying on the default of 0.
     */
    protected function backfillSortOrder(): void
    {
        $currentGroupId = null;
        $position = 0;

        $rows = DB::table('same_product_group_product')
            ->select(['id', 'same_product_group_id'])
            ->orderBy('same_product_group_id')
            ->orderBy('product_id')
            ->cursor();

        foreach ($rows as $row) {
            if ($row->same_product_group_id !== $currentGroupId) {
                $currentGroupId = $row->same_product_group_id;
                $position = 0;
            }

            DB::table('same_product_group_product')
                ->where('id', $row->id)
                ->update(['sort_order' => ++$position]);
        }
    }
};
