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
        Schema::create('giao_dich_quy', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tai_khoan_gay_quy_id');
            $table->unsignedBigInteger('chien_dich_gay_quy_id')->nullable();
            $table->unsignedBigInteger('ung_ho_id')->nullable();
            $table->decimal('so_tien', 15, 2);
            $table->enum('loai_giao_dich', ['UNG_HO', 'RUT']);
            $table->string('mo_ta', 255)->nullable();
            $table->enum('trang_thai', ['CHO_DUYET', 'DA_DUYET', 'TU_CHOI'])->nullable();
            $table->string('ma_giao_dich_ngan_hang')->nullable();
            $table->text('ghi_chu_admin')->nullable();
            $table->timestamp('ngay_giao_dich')->nullable();
            $table->foreign('ung_ho_id')->references('id')->on('ung_ho');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('giao_dich_quy');
    }
};
