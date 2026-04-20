<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bao_cao_bai_dang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bai_dang_id')
                ->constrained('bai_dang')
                ->cascadeOnDelete();
            $table->foreignId('nguoi_to_cao_id')
                ->constrained('nguoi_dung')
                ->cascadeOnDelete();
            $table->enum('ly_do', ['SPAM', 'LUA_DAO', 'NOI_DUNG_XAU', 'KHAC']);
            $table->string('mo_ta', 1000)->nullable();
            $table->enum('trang_thai', ['CHO_XU_LY', 'DA_XU_LY', 'TU_CHOI'])
                ->default('CHO_XU_LY');
            $table->timestamps();

            $table->index(['trang_thai', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bao_cao_bai_dang');
    }
};
