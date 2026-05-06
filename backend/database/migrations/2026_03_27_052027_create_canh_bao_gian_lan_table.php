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
        
            $table->string('target_type', 20)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
        
            $table->string('source', 20)->nullable();
        
            $table->string('loai_canh_bao', 255)->nullable();
        
            $table->float('diem_rui_ro')->default(0);
            $table->string('muc_rui_ro', 10)->nullable();
        
            $table->string('loai_gian_lan', 255)->nullable();
            $table->string('mo_ta', 255)->nullable();
            $table->text('ly_do')->nullable();
        
            $table->enum('trang_thai', ['CHO_XU_LY', 'DA_XU_LY'])
                ->default('CHO_XU_LY');
        
            $table->enum('decision', ['CHO_XU_LY', 'VI_PHAM', 'KHONG_VI_PHAM'])
                ->default('CHO_XU_LY');
        
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('nguoi_dung')
                ->nullOnDelete();
        
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        
            $table->timestamps();
        
            $table->index(['source', 'trang_thai']);
            $table->index(['target_type', 'target_id']);
            $table->index(['muc_rui_ro', 'created_at']);
            $table->index(['source', 'decision', 'created_at']);
            $table->index(['admin_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canh_bao_gian_lan');
    }
};
