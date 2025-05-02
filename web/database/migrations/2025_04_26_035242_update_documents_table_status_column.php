<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Kiểm tra xem bảng documents đã tồn tại chưa
        if (Schema::hasTable('documents')) {
            // Kiểm tra xem cột status có tồn tại không
            if (Schema::hasColumn('documents', 'status')) {
                // Thay đổi cột status từ enum thành varchar
                Schema::table('documents', function (Blueprint $table) {
                    $table->string('status', 20)->change();
                });
            }
            
            // Kiểm tra xem cột vector_status có tồn tại không
            if (Schema::hasColumn('documents', 'vector_status')) {
                // Thay đổi cột vector_status từ enum thành varchar
                Schema::table('documents', function (Blueprint $table) {
                    $table->string('vector_status', 20)->change();
                });
            }
        } else {
            // Tạo bảng documents nếu chưa tồn tại
            Schema::create('documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('file_path');
                $table->string('file_name');
                $table->bigInteger('file_size');
                $table->string('file_type');
                $table->string('status', 20); // Sử dụng string thay vì enum
                $table->string('vector_status', 20); // Sử dụng string thay vì enum
                $table->string('vector_path')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Không cần rollback vì chúng ta muốn giữ các thay đổi
    }
};
