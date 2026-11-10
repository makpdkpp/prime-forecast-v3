<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->boolean('mcp_sales_create_enabled')->default(false);
        });

        // Import existing grants once; subsequent changes are managed by Admin.
        DB::table('user')->whereIn('role_id', [2, 3])
            ->whereIn('user_id', config('services.prime_mcp.sales_create_user_ids', []))
            ->update(['mcp_sales_create_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn('mcp_sales_create_enabled');
        });
    }
};
