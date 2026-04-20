<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canh_bao_gian_lan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nguoi_dung_id')
                ->constrained('nguoi_dung')
                ->cascadeOnDelete();
            $table->foreignId('chien_dich_id')
                ->nullable()
                ->constrained('chien_dich_gay_quy')
                ->nullOnDelete();
            $table->string('loai_gian_lan', 255)->nullable();
            $table->float('diem_rui_ro')->default(0);
            $table->string('mo_ta', 255)->nullable();
            $table->enum('trang_thai', ['CHO_XU_LY', 'DA_KIEM_TRA', 'CANH_BAO_SAI'])
                ->default('CHO_XU_LY');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canh_bao_gian_lan');
    }
};
