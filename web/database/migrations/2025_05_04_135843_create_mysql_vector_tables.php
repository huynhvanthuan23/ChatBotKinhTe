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
        if (!Schema::hasTable('vector_entries')) {
            Schema::create('vector_entries', function (Blueprint $table) {
                $table->id();
                $table->string('item_id');
                $table->text('content');
                $table->json('metadata')->nullable();
                $table->binary('embedding');
                $table->string('collection', 100)->index();
                $table->timestamps();
                
                // Tạo index cho item_id và collection để tìm kiếm nhanh
                $table->index(['item_id', 'collection']);
            });
        }
        
        // Xóa bảng tạm mysql_vector_tables nếu có
        Schema::dropIfExists('mysql_vector_tables');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vector_entries');
    }
};
