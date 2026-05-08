<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DonateController;
use App\Http\Controllers\FraudController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\TroChuyenController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\PostReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WithdrawRequestController;

Route::post('/register', [AuthController::class,'register']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/login', [AuthController::class,'login']);
Route::get('/auth/google', [GoogleController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/map/campaigns', [CampaignController::class, 'map']);

// Feed - guest có thể xem danh sách/chi tiết
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{id}', [PostController::class, 'show'])->whereNumber('id');
Route::get('/posts/{id}/comments', [PostCommentController::class, 'index'])->whereNumber('id');
Route::get('/dashboard/community-stats', [DashboardController::class, 'communityStats']);

// ds danh mục
Route::get('/categories', [CampaignController::class, 'getDanhMuc']);
Route::get('/posts/search', [PostController::class, 'search']);

Route::middleware('auth:sanctum')->group(function(){
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout',[AuthController::class,'logout']);
    Route::post('/broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    });
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/posts/{id}/like', [PostController::class, 'toggleLike'])->whereNumber('id');
    Route::post('/posts/{id}/comments', [PostCommentController::class, 'store'])->whereNumber('id');
    Route::post('/comments/{id}', [PostCommentController::class, 'destroy'])->whereNumber('id');
    Route::post('/posts/{id}/report', [PostReportController::class, 'store'])->whereNumber('id');

    Route::middleware('role:ADMIN')->group(function(){
        Route::prefix('/admin')->group(function () {
            // ADMIN - nguoi dung
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users/{id}/lock', [AdminUserController::class, 'lock']);
            Route::post('/users/{id}/unlock', [AdminUserController::class, 'unlock']);
            Route::get('/users/organizations/pending/{id}', [AdminUserController::class, 'showLicense']);

            // ADMIN - dashboard
            Route::get('/dashboard/summary', [AdminDashboardController::class, 'summary']);
            Route::get('/dashboard/fundraising-by-month', [AdminDashboardController::class, 'fundraisingByMonth']);
            Route::get('/dashboard/recent-activities', [AdminDashboardController::class, 'recentActivities']);
            Route::get('/dashboard/featured-campaigns', [AdminDashboardController::class, 'featuredCampaigns']);

            // ADMIN - danh sach / chi tiet
            Route::get('/organizations', [OrganizationController::class, 'adminIndex']);
            Route::get('/organizations/{id}', [OrganizationController::class, 'show']);
            Route::get('/campaigns', [CampaignController::class, 'index']);
            Route::get('/campaigns/{id}', [CampaignController::class, 'show']);
            Route::get('/campaigns/{id}/violations', [PostReportController::class, 'campaignViolations'])->whereNumber('id');

            Route::get('/posts', [PostController::class, 'index']);
            Route::get('/posts/{id}', [PostController::class, 'show']);
            Route::get('/posts/{id}/violations', [PostReportController::class, 'postViolations'])->whereNumber('id');
            // ADMIN - duyet to chuc
            Route::post('/organization/{id}/approve', [OrganizationController::class, 'approve']);
            Route::post('/organization/{id}/reject', [OrganizationController::class, 'reject']);

            // ADMIN - khoa tai khoan gay quy
            Route::post('/fund-accounts/{id}/lock', [OrganizationController::class, 'lock']);

            // ADMIN - duyet chien dich
            Route::post('/campaigns/{id}/approve', [CampaignController::class, 'approveCampaign']);
            Route::post('/campaigns/{id}/reject', [CampaignController::class, 'rejectCampaign']);
            Route::post('/campaigns/{id}/suspend', [CampaignController::class, 'suspendCampaign']);

            // ADMIN - fraud
            Route::post('/fraud-check/auto', [FraudController::class, 'autoCheck']);
            Route::post('/fraud-check/campaigns/auto', [FraudController::class, 'autoCheckCampaigns']);
            Route::get('/fraud-alerts', [FraudController::class, 'getAlerts']);
            Route::post('/fraud-alerts/{canhBao}', [FraudController::class, 'updateAlert']);

             Route::get('/violation-reasons', [PostReportController::class, 'violationReasons']);
            Route::post('/post-reports/{id}', [PostReportController::class, 'adminUpdate'])->whereNumber('id');
            Route::get('/post-reports', [PostReportController::class, 'adminIndex']);
            Route::post('/posts/{id}/suspend', [PostController::class, 'suspendByAdmin'])->whereNumber('id');

            // ADMIN - duyet rut tien
            Route::get('/withdraw-requests', [WithdrawRequestController::class, 'adminIndex']);
            Route::put('/withdraw-requests/{id}/confirm', [WithdrawRequestController::class, 'confirm']);
            Route::put('/withdraw-requests/{id}/reject', [WithdrawRequestController::class, 'reject']);
        });

       
    });
    
    Route::middleware(['role:TO_CHUC','update.campaign'])->group(function(){
        //chiến dịch
        Route::post('/campaigns', [CampaignController::class, 'store']);
        Route::get('/campaigns/me', [CampaignController::class, 'myCampaigns']);
        Route::get('/campaigns/update/{id}', [CampaignController::class, 'edit']);
        Route::post('/campaigns/update/{id}', [CampaignController::class, 'update']);

        //thống kê
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/financial-summary', [DashboardController::class, 'financialSummary']);
        Route::get('/dashboard/monthly-statistics', [DashboardController::class, 'monthlyStatistics']);
        Route::get('/dashboard/active-campaigns', [DashboardController::class, 'activeCampaigns']);
        Route::get('/campaigns/others', [DashboardController::class, 'otherCampaigns']);
        Route::get('/dashboard/recent-activities', [DashboardController::class, 'recentActivities']);

        //hoạt động
        Route::get('/campaigns/{id}/withdraw-expenses', [CampaignController::class, 'getWithdrawWithExpenses']);
        Route::post('/campaigns/{id}/expenses', [CampaignController::class, 'storeExpense']);
        Route::get('/campaigns/{id}/withdraw-transactions', [CampaignController::class, 'getWithdrawTransactions']);
        
        //yêu cầu rút tiền
        Route::get('/withdraw-requests', [WithdrawRequestController::class, 'index']);
        Route::post('/withdraw-requests', [WithdrawRequestController::class, 'store']);
        Route::get('/withdraw-requests/campaigns', [WithdrawRequestController::class, 'campaigns']);
      
   });

    // Feed - user và tổ chức: đăng/cập nhật/xóa 
    Route::middleware('role:NGUOI_DUNG,TO_CHUC')->group(function () {
        // CRUD posts
        Route::post('/posts', [PostController::class, 'store']);
        Route::get('/posts/me', [PostController::class, 'me']);
        Route::get('/posts/{id}/related', [PostController::class, 'related']);
        Route::post('/posts/{id}', [PostController::class, 'update'])->whereNumber('id');
        Route::delete('/posts/{id}', [PostController::class, 'destroy'])->whereNumber('id');
        
        
        // AI matching
        Route::get('/posts/{id}/matches', [PostController::class, 'matches'])->whereNumber('id');
       
        Route::prefix('/tro-chuyen')->group(function () {
            Route::post('/tao-hoac-lay', [TroChuyenController::class, 'taoHoacLay']);
            Route::get('/', [TroChuyenController::class, 'danhSach']);
            Route::get('/{id}/tin-nhan', [TroChuyenController::class, 'layTinNhan']);
            Route::post('/{id}/tin-nhan', [TroChuyenController::class, 'guiTinNhan']);
            Route::delete('/{id}/tin-nhan', [TroChuyenController::class, 'xoaHetTinNhan'])->whereNumber('id');
            Route::post('/{id}/tin-nhan/{tinNhanId}', [TroChuyenController::class, 'xoaTinNhan'])
                ->whereNumber('id')
                ->whereNumber('tinNhanId');
            Route::post('/{id}/da-xem', [TroChuyenController::class, 'danhDauDaXem']);
        });
       
        //ttcn
        Route::get('/user/profile',[UserProfileController::class,'getProfile']);
        Route::post('/user/profile',[UserProfileController::class,'updateProfile']);
        Route::post('/user/change-password',[UserProfileController::class,'changePassword']);
        Route::post('/user/update-diachi',[UserProfileController::class,'updateDiaChi']);

        //đăng ký tổ chức
        Route::post('/organization/register', [OrganizationController::class, 'register']);
        Route::get('/organization/status', [OrganizationController::class, 'status']);

        //ủng hộ
        Route::post('/donate', [DonateController::class, 'donate']);
        Route::get('/donate/history', [DonateController::class, 'donateHistory']);
        Route::get('/donate/{id}', [DonateController::class, 'getDonateDetail']);
    });
});
Route::post('/upload-image', [PostController::class, 'uploadImage']);

//xem tổ chức
Route::get('/organization', [OrganizationController::class, 'index']);
Route::get('/organization/{id}', [OrganizationController::class, 'show']);

//xem chiến dịch
Route::middleware('update.campaign')->group(function () {
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::get('/campaigns/featured', [CampaignController::class, 'featured']);
    Route::get('/campaigns/ending-soon', [CampaignController::class, 'endingSoon']);
    Route::get('/campaigns/{id}', [CampaignController::class, 'show']);
});

Route::post('/momo/ipn', [DonateController::class, 'momoIpn']);
Route::get('/momo/return', [DonateController::class, 'momoReturn']);

//xem profile người dùng khác
Route::get('/profile/{id}', [UserProfileController::class, 'show']);