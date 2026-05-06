<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bai_dang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nguoi_dung_id')
                ->constrained('nguoi_dung')
                ->cascadeOnDelete();

            $table->enum('loai_bai', ['CHO', 'NHAN']);

            $table->string('tieu_de', 255);
            $table->text('mo_ta');
            $table->json('hinh_anh')->nullable();
            $table->string('dia_diem', 255);
            $table->float('lat')->nullable();
            $table->float('lng')->nullable();
            $table->string('region', 50)->nullable()->index();
            $table->integer('so_luong');

            $table->enum('trang_thai', ['CON_NHAN', 'CON_TANG', 'DA_NHAN', 'DA_TANG', 'TAM_DUNG']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bai_dang');
    }
};

