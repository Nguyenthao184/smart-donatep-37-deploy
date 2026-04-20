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
        Schema::create('tai_khoan_gay_quy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('to_chuc_id')->constrained('to_chuc')->cascadeOnDelete();

            $table->string('ten_quy');
            $table->string('ngan_hang')->default('MB Bank');

            $table->string('so_tai_khoan')->nullable()->unique();
            $table->string('chu_tai_khoan')->nullable();

            $table->decimal('so_du', 15, 2)->default(0);
            $table->text('qr_code')->nullable();

            $table->enum('trang_thai', ['CHO_DUYET', 'HOAT_DONG', 'KHOA'])
              ->default('CHO_DUYET');

            $table->string('ma_yeu_cau_mb')->nullable();  

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tai_khoan_gay_quy');
    }
};
