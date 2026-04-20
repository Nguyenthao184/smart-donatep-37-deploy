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
        Schema::create('nguoi_dung_vai_tro', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('nguoi_dung_id')
                ->constrained('nguoi_dung')
                ->cascadeOnDelete();

            $table->foreignId('vai_tro_id')
                ->constrained('vai_tro')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nguoi_dung_vai_tro');
    }
};
