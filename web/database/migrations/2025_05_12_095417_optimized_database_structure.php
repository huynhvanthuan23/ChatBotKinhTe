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
        // KHÔNG THAY ĐỔI CÁC MIGRATION GỐC
        // Migration này chỉ đảm bảo rằng tất cả cấu trúc cần thiết được tạo ra

        // 1. Thêm cột user_id vào bảng messages nếu chưa tồn tại
        if (Schema::hasTable('messages') && !Schema::hasColumn('messages', 'user_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('conversation_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }

        // 2. Thêm cột citations vào bảng messages nếu chưa tồn tại
        if (Schema::hasTable('messages') && !Schema::hasColumn('messages', 'citations')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->json('citations')->nullable()->after('content')
                      ->comment('Lưu trữ thông tin trích dẫn dưới dạng JSON');
            });
        }

        // 3. Tạo bảng settings nếu chưa tồn tại
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group')->default('general');
                $table->string('type')->default('string');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        // 4. Thêm cột retry_count vào bảng documents nếu chưa tồn tại
        if (Schema::hasTable('documents') && !Schema::hasColumn('documents', 'retry_count')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->integer('retry_count')->default(0)->after('vector_data');
            });
        }

        // 5. Thêm cột is_integrated vào bảng documents nếu chưa tồn tại
        if (Schema::hasTable('documents') && !Schema::hasColumn('documents', 'is_integrated')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->boolean('is_integrated')->default(false)->after('retry_count');
            });
        }

        // 6. Tạo bảng vector_entries nếu chưa tồn tại (cho MySQL vector store)
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Không rollback các thay đổi này vì chúng chỉ là kiểm tra và sửa chữa
    }
};
