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
        Schema::create('chi_tieu_chien_dich', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chien_dich_gay_quy_id')
                ->constrained('chien_dich_gay_quy')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('giao_dich_quy_id');
            $table->foreign('giao_dich_quy_id')
                ->references('id')
                ->on('giao_dich_quy')
                ->cascadeOnDelete();
            $table->string('ten_hoat_dong'); 
            $table->text('mo_ta')->nullable();
            $table->decimal('so_tien', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chi_tieu_chien_dich');
    }
};
