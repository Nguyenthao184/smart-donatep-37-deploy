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
        Schema::create('ung_ho', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nguoi_dung_id');
            $table->unsignedBigInteger('chien_dich_gay_quy_id');
            $table->decimal('so_tien', 15, 2);
            $table->string('payment_ref', 100)->nullable();          // mã gửi sang VNPAY
            $table->string('gateway_transaction_id', 100)->nullable();   // mã VNPAY trả về
            $table->enum('phuong_thuc_thanh_toan', ['vnpay', 'momo'])->nullable();
            $table->enum('trang_thai', ['CHO_XU_LY', 'THANH_CONG', 'THAT_BAI'])->default('CHO_XU_LY');
            $table->foreign('nguoi_dung_id')->references('id')->on('nguoi_dung');
            $table->foreign('chien_dich_gay_quy_id')->references('id')->on('chien_dich_gay_quy');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ung_ho');
    }
};
