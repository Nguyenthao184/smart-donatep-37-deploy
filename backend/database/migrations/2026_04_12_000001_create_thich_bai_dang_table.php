<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thich_bai_dang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bai_dang_id')
                ->constrained('bai_dang')
                ->cascadeOnDelete();
            $table->foreignId('nguoi_dung_id')
                ->constrained('nguoi_dung')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bai_dang_id', 'nguoi_dung_id'], 'uniq_thich_bai_dang_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thich_bai_dang');
    }
};
