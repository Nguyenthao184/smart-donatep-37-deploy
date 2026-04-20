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
        Schema::create('xac_minh_to_chuc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nguoi_dung_id')->constrained('nguoi_dung');
            $table->string('ten_to_chuc');
            $table->string('ma_so_thue');
            $table->string('nguoi_dai_dien');
            $table->string('giay_phep');
            $table->text('mo_ta');
            $table->string('dia_chi');
            $table->string('so_dien_thoai');
            $table->string('logo')->nullable();
            $table->enum('trang_thai', ['CHO_XU_LY','CHAP_NHAN','TU_CHOI'])->default('CHO_XU_LY');
            $table->enum('loai_hinh', [
                'NHA_NUOC',
                'QUY_TU_THIEN',
                'DOANH_NGHIEP'
            ])->default('QUY_TU_THIEN');
            $table->unsignedBigInteger('duyet_boi')->nullable();
            $table->timestamp('duyet_luc')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xac_minh_to_chuc');
    }
};
