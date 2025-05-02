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
            // Thêm trường vector_data để lưu thông tin vector
            $table->text('vector_data')->nullable()->after('vector_path');
            
            // Thêm trường is_integrated để đánh dấu tài liệu đã được tích hợp vào vector database chính
            $table->boolean('is_integrated')->default(false)->after('vector_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('vector_data');
            $table->dropColumn('is_integrated');
        });
    }
};
