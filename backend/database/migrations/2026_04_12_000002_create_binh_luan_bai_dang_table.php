<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('binh_luan_bai_dang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cha')
                ->nullable()
                ->constrained('binh_luan_bai_dang')
                ->cascadeOnDelete();
                
            $table->foreignId('bai_dang_id')
                ->constrained('bai_dang')
                ->cascadeOnDelete();
            $table->foreignId('nguoi_dung_id')
                ->constrained('nguoi_dung')
                ->cascadeOnDelete();
            $table->text('noi_dung');
            $table->timestamps();

            $table->index(['bai_dang_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binh_luan_bai_dang');
    }
};
