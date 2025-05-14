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
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'retry_count')) {
                $table->integer('retry_count')->default(0)->after('vector_data');
            }
            
            if (!Schema::hasColumn('documents', 'is_integrated')) {
                $table->boolean('is_integrated')->default(false)->after('retry_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'is_integrated']);
        });
    }
};
