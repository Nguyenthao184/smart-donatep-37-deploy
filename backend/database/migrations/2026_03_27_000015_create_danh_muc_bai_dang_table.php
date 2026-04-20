<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('danh_muc_bai_dang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bai_dang_id')
                ->constrained('bai_dang')
                ->cascadeOnDelete();

            $table->string('danh_muc_code', 50);
            $table->boolean('is_primary')->default(false);
            $table->double('confidence', 15, 8)->nullable();

            $table->timestamps();

            $table->unique(['bai_dang_id', 'danh_muc_code'], 'uniq_bai_dang_danh_muc_code');
            $table->index('danh_muc_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('danh_muc_bai_dang');
    }
};

