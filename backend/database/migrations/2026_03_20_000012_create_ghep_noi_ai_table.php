<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ghep_noi_ai', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bai_dang_nguon_id')
                ->constrained('bai_dang')
                ->cascadeOnDelete();

            $table->foreignId('bai_dang_phu_hop_id')
                ->constrained('bai_dang')
                ->cascadeOnDelete();

            $table->float('diem_phu_hop');

            $table->enum('trang_thai', ['CHO_XU_LY', 'GHEP_NOI', 'HUY_BO'])
                ->default('GHEP_NOI');

            $table->timestamps();

            $table->unique(['bai_dang_nguon_id', 'bai_dang_phu_hop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ghep_noi_ai');
    }
};

