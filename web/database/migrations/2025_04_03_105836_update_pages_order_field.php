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
        // Cập nhật các giá trị order hiện tại để bắt đầu từ 1
        $pages = DB::table('pages')->get();
        foreach ($pages as $page) {
            if ($page->order === 0) {
                DB::table('pages')
                    ->where('id', $page->id)
                    ->update(['order' => 1]);
            }
        }
        
        // Thêm ràng buộc min=1 cho cột order
        Schema::table('pages', function (Blueprint $table) {
            DB::statement('ALTER TABLE pages MODIFY COLUMN `order` INT NOT NULL DEFAULT 1 CHECK (`order` >= 1)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Khôi phục ràng buộc về mặc định
        Schema::table('pages', function (Blueprint $table) {
            DB::statement('ALTER TABLE pages MODIFY COLUMN `order` INT NOT NULL DEFAULT 0');
        });
    }
};
