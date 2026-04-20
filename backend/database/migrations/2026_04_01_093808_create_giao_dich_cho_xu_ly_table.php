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
        Schema::create('giao_dich_cho_xu_ly', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nguoi_dung_id');
            $table->unsignedBigInteger('chien_dich_gay_quy_id');
            $table->decimal('so_tien', 15, 2);
            $table->string('noi_dung', 255);
            $table->timestamp('thoi_gian');
            $table->enum('trang_thai', ['CHUA_GAN', 'DA_GAN'])->default('CHUA_GAN');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('giao_dich_cho_xu_ly');
    }
};
