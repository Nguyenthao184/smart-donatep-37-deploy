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
        Schema::create('chien_dich_gay_quy', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('to_chuc_id');
            $table->unsignedBigInteger('danh_muc_id');
            $table->unsignedBigInteger('tai_khoan_gay_quy_id');
            $table->string('ten_chien_dich');
            $table->text('mo_ta');
            $table->json('hinh_anh');
            $table->decimal('muc_tieu_tien', 15, 2);
            $table->decimal('so_tien_da_nhan', 15, 2)->default(0);
            $table->date('ngay_ket_thuc');
            $table->string('vi_tri');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('ma_noi_dung_ck', 50)->unique();;
            $table->enum('trang_thai', [
                    'CHO_XU_LY',
                    'HOAT_DONG',
                    'HOAN_THANH',
                    'TU_CHOI',
                    'TAM_DUNG',
                    'DA_KET_THUC'
                ]);
            $table->foreign('tai_khoan_gay_quy_id')
                ->references('id')->on('tai_khoan_gay_quy');

            $table->foreign('to_chuc_id')
                ->references('id')->on('to_chuc');

            $table->foreign('danh_muc_id')
                ->references('id')->on('danh_muc');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chien_dich_gay_quy');
    }
};
