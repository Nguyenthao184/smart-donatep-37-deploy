<?php

namespace App\Services;

class ApprovalService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function approve($model)
    {
        if ($model->trang_thai !== 'CHO_XU_LY') {
            throw new \Exception('Không thể duyệt');
        }

        $model->update([
            'trang_thai' => 'HOAT_DONG'
        ]);

        return $model;
    }

    public function reject($model, $reason = null)
    {
        if ($model->trang_thai !== 'CHO_XU_LY') {
            throw new \Exception('Không thể từ chối');
        }

        $model->update([
            'trang_thai' => 'TU_CHOI',
            'ly_do_tu_choi' => $reason
        ]);

        return $model;
    }

    public function lock($model, $reason = null)
    {
        if ($model->trang_thai !== 'HOAT_DONG') {
            throw new \Exception('Không thể khóa');
        }

        $model->update([
            'trang_thai' => 'KHOA',
            'ly_do_khoa' => $reason
        ]);

        return $model;
    }
}
