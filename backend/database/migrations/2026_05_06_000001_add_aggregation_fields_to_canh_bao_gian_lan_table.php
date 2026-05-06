<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canh_bao_gian_lan', function (Blueprint $table) {
            if (!Schema::hasColumn('canh_bao_gian_lan', 'violation_code')) {
                $table->string('violation_code', 80)->nullable()->after('source');
            }
            if (!Schema::hasColumn('canh_bao_gian_lan', 'count')) {
                $table->unsignedInteger('count')->default(1)->after('violation_code');
            }
            if (!Schema::hasColumn('canh_bao_gian_lan', 'time_window_seconds')) {
                $table->unsignedInteger('time_window_seconds')->nullable()->after('count');
            }
            if (!Schema::hasColumn('canh_bao_gian_lan', 'window_started_at')) {
                $table->timestamp('window_started_at')->nullable()->after('time_window_seconds');
            }
            if (!Schema::hasColumn('canh_bao_gian_lan', 'details')) {
                $table->json('details')->nullable()->after('window_started_at');
            }
            if (!Schema::hasColumn('canh_bao_gian_lan', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('details');
            }

            $table->index(['source', 'violation_code', 'nguoi_dung_id', 'target_type', 'target_id', 'window_started_at'], 'cbgl_agg_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('canh_bao_gian_lan', function (Blueprint $table) {
            if (Schema::hasColumn('canh_bao_gian_lan', 'last_seen_at')) {
                $table->dropColumn('last_seen_at');
            }
            if (Schema::hasColumn('canh_bao_gian_lan', 'details')) {
                $table->dropColumn('details');
            }
            if (Schema::hasColumn('canh_bao_gian_lan', 'window_started_at')) {
                $table->dropColumn('window_started_at');
            }
            if (Schema::hasColumn('canh_bao_gian_lan', 'time_window_seconds')) {
                $table->dropColumn('time_window_seconds');
            }
            if (Schema::hasColumn('canh_bao_gian_lan', 'count')) {
                $table->dropColumn('count');
            }
            if (Schema::hasColumn('canh_bao_gian_lan', 'violation_code')) {
                $table->dropColumn('violation_code');
            }

            $table->dropIndex('cbgl_agg_key_idx');
        });
    }
};

