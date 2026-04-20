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
        Schema::create('to_chuc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nguoi_dung_id')
                  ->constrained('nguoi_dung')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('xac_minh_to_chuc_id')->nullable();
            $table->string('ten_to_chuc');
            $table->text('mo_ta')->nullable();
            $table->string('dia_chi')->nullable();
            $table->string('so_dien_thoai', 15)->nullable();
             $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->integer('so_cd_dang_hd')->nullable();
            $table->enum('trang_thai', ['HOAT_DONG', 'KHOA'])
                  ->default('HOAT_DONG');
            $table->integer('diem_uy_tin')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('to_chuc');
    }
};
