<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) cuoc_tro_chuyen
        if (!Schema::hasTable('cuoc_tro_chuyen')) {
            Schema::create('cuoc_tro_chuyen', function (Blueprint $table) {
                $table->id();
                $table->string('khoa_1_1', 50)->nullable()->unique();
                $table->unsignedBigInteger('tin_nhan_cuoi_id')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('cuoc_tro_chuyen', function (Blueprint $table) {
                if (!Schema::hasColumn('cuoc_tro_chuyen', 'khoa_1_1')) {
                    $table->string('khoa_1_1', 50)->nullable()->unique();
                }
                if (!Schema::hasColumn('cuoc_tro_chuyen', 'tin_nhan_cuoi_id')) {
                    $table->unsignedBigInteger('tin_nhan_cuoi_id')->nullable();
                }
                if (!Schema::hasColumn('cuoc_tro_chuyen', 'updated_at')) {
                    $table->timestamps();
                }
            });
        }

        // 2) thanh_vien_tro_chuyen
        if (!Schema::hasTable('thanh_vien_tro_chuyen')) {
            Schema::create('thanh_vien_tro_chuyen', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cuoc_tro_chuyen_id');
                $table->unsignedBigInteger('nguoi_dung_id');
                $table->timestamp('lan_cuoi_xem_luc')->nullable();
                $table->unsignedBigInteger('sau_tin_nhan_id')->nullable();
                $table->timestamps();

                $table->unique(['cuoc_tro_chuyen_id', 'nguoi_dung_id'], 'tvttc_unique');
                $table->index(['nguoi_dung_id', 'cuoc_tro_chuyen_id'], 'tvttc_user_conv_idx');
            });
        } else {
            Schema::table('thanh_vien_tro_chuyen', function (Blueprint $table) {
                if (!Schema::hasColumn('thanh_vien_tro_chuyen', 'lan_cuoi_xem_luc')) {
                    $table->timestamp('lan_cuoi_xem_luc')->nullable();
                }
                if (!Schema::hasColumn('thanh_vien_tro_chuyen', 'sau_tin_nhan_id')) {
                    $table->unsignedBigInteger('sau_tin_nhan_id')->nullable()->after('lan_cuoi_xem_luc');
                }
                if (!Schema::hasColumn('thanh_vien_tro_chuyen', 'updated_at')) {
                    $table->timestamps();
                }
            });
        }

        // 3) tin_nhan
        if (!Schema::hasTable('tin_nhan')) {
            Schema::create('tin_nhan', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cuoc_tro_chuyen_id');
                $table->unsignedBigInteger('nguoi_gui_id');
                $table->text('noi_dung')->nullable();
                $table->enum('loai_tin', ['VAN_BAN', 'ANH', 'VIDEO'])->default('VAN_BAN');
                // boolean OK cho chat 1:1, nhưng vẫn giữ lan_cuoi_xem_luc để tính unread chuẩn.
                $table->boolean('da_xem')->default(false);
                $table->boolean('da_thu_hoi')->default(false);
                $table->string('tep_dinh_kem')->nullable();
                $table->timestamps();

                $table->index(['cuoc_tro_chuyen_id', 'id'], 'tn_conv_id_idx');
                $table->index(['nguoi_gui_id', 'id'], 'tn_sender_id_idx');
            });
        } else {
            Schema::table('tin_nhan', function (Blueprint $table) {
                if (!Schema::hasColumn('tin_nhan', 'da_thu_hoi')) {
                    $table->boolean('da_thu_hoi')->default(false);
                }
                if (!Schema::hasColumn('tin_nhan', 'tep_dinh_kem')) {
                    $table->string('tep_dinh_kem')->nullable();
                }
                if (!Schema::hasColumn('tin_nhan', 'updated_at')) {
                    $table->timestamps();
                }
            });
            if (Schema::hasTable('tin_nhan') && Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `tin_nhan` MODIFY `loai_tin` ENUM('VAN_BAN','ANH','VIDEO') NOT NULL DEFAULT 'VAN_BAN'");
            }
        }
    }

    public function down(): void
    {
        // Không drop bảng nếu đây là DB đang dùng; chỉ rollback khi bảng do migration tạo.
        // Bạn có thể tự chỉnh lại nếu muốn rollback mạnh tay.
    }
};

