<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tin_nhan')) {
            return;
        }

        Schema::table('tin_nhan', function (Blueprint $table) {
            if (Schema::hasColumn('tin_nhan', 'an_phia_nguoi_gui')) {
                $table->dropColumn('an_phia_nguoi_gui');
            }
        });

        Schema::table('tin_nhan', function (Blueprint $table) {
            if (!Schema::hasColumn('tin_nhan', 'da_thu_hoi')) {
                $table->boolean('da_thu_hoi')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tin_nhan')) {
            return;
        }

        Schema::table('tin_nhan', function (Blueprint $table) {
            if (Schema::hasColumn('tin_nhan', 'da_thu_hoi')) {
                $table->dropColumn('da_thu_hoi');
            }
        });

        Schema::table('tin_nhan', function (Blueprint $table) {
            if (!Schema::hasColumn('tin_nhan', 'an_phia_nguoi_gui')) {
                $table->boolean('an_phia_nguoi_gui')->default(false);
            }
        });
    }
};
