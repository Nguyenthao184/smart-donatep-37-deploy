<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bai_dang', function (Blueprint $table) {
            $table->float('lat')->nullable();
            $table->float('lng')->nullable();
            $table->string('region', 50)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('bai_dang', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'region']);
        });
    }
};

