<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Proprietor\AnnouncementController as ProprietorAnnouncementController;
use App\Http\Controllers\Proprietor\AiSummaryController;
use App\Http\Controllers\Proprietor\ComplaintController as ProprietorComplaintController;
use App\Http\Controllers\Proprietor\FaqController as ProprietorFaqController;
use App\Http\Controllers\Proprietor\FinancialReportController;
use App\Http\Controllers\Proprietor\ProprietorDashboardController;
use App\Http\Controllers\Proprietor\PaymentController as ProprietorPaymentController;
use App\Http\Controllers\Proprietor\RoomAssignmentController;
use App\Http\Controllers\Proprietor\RoomController;
use App\Http\Controllers\Proprietor\RiskDashboardController;
use App\Http\Controllers\Proprietor\TenantController;
use App\Http\Controllers\Tenant\AnnouncementController as TenantAnnouncementController;
use App\Http\Controllers\Tenant\ComplaintController as TenantComplaintController;
use App\Http\Controllers\Tenant\FaqController as TenantFaqController;
use App\Http\Controllers\Tenant\PaymentController as TenantPaymentController;
use App\Http\Controllers\Tenant\ProfileController as TenantProfileController;
use App\Http\Controllers\Tenant\TenantDashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Proprietor\TenantImportController;
use App\Http\Controllers\Proprietor\UtilityBillController;
use App\Http\Controllers\Proprietor\CollectiblesReportController;
use App\Http\Controllers\Proprietor\SmsSettingsController;
use App\Http\Controllers\Proprietor\AiSettingsController;
use App\Http\Controllers\Proprietor\SettingsController;

require __DIR__.'/auth.php'; // Breeze's login/logout/password routes

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
});

Route::middleware('auth')->get('/dashboard', function () {
    return redirect(Auth::user()->role === 'proprietor'
        ? route('proprietor.dashboard')
        : route('tenant.dashboard'));
})->name('dashboard');

Route::middleware(['auth', 'role:proprietor'])
    ->prefix('proprietor')
    ->name('proprietor.')
    ->group(function () {
        Route::get('/dashboard', [ProprietorDashboardController::class, 'index'])->name('dashboard');
        Route::resource('tenants', TenantController::class)->except(['show']);
        Route::resource('rooms', RoomController::class)->except(['show']);
        Route::resource('payments', ProprietorPaymentController::class)->only(['index', 'create', 'store']);
        Route::get('risk', [RiskDashboardController::class, 'index'])->name('risk.index');
        Route::post('risk/recalculate', [RiskDashboardController::class, 'recalculate'])->name('risk.recalculate');
        Route::get('complaints/summarize', [AiSummaryController::class, 'index'])->name('complaints.summarize');
        Route::resource('announcements', ProprietorAnnouncementController::class)->except(['show']);
        Route::resource('faqs', ProprietorFaqController::class)->except(['show']);
        Route::get('reports/financial', [FinancialReportController::class, 'index'])->name('reports.index');
        Route::get('complaints', [ProprietorComplaintController::class, 'index'])->name('complaints.index');
        Route::patch('complaints/{complaint}', [ProprietorComplaintController::class, 'update'])->name('complaints.update');
        Route::post('ai-summaries', [AiSummaryController::class, 'store'])->name('ai-summaries.store');
        Route::post('room-assignments', [RoomAssignmentController::class, 'store'])->name('room-assignments.store');
        Route::patch('room-assignments/{roomAssignment}', [RoomAssignmentController::class, 'update'])->name('room-assignments.update');
        Route::get('tenants/import', [TenantImportController::class, 'create'])->name('tenants.import.create');
        Route::post('tenants/import', [TenantImportController::class, 'store'])->name('tenants.import.store');
        Route::get('tenants/import/results', [TenantImportController::class, 'results'])->name('tenants.import.results');
        Route::get('tenants/import/template', [TenantImportController::class, 'downloadTemplate'])->name('tenants.import.template');

        // Each module below adds its routes inside this group
        Route::resource('utility-bills', UtilityBillController::class)->except(['show']);

        Route::get('reports/collectibles', [CollectiblesReportController::class, 'index'])->name('reports.collectibles');

        Route::get('settings/sms', [SmsSettingsController::class, 'edit'])->name('settings.sms.edit');
        Route::put('settings/sms', [SmsSettingsController::class, 'update'])->name('settings.sms.update');

        Route::get('settings/ai-api', [AiSettingsController::class, 'edit'])->name('settings.ai.edit');
        Route::put('settings/ai-api', [AiSettingsController::class, 'update'])->name('settings.ai.update');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

Route::middleware(['auth', 'role:tenant'])
    ->prefix('portal')
    ->name('tenant.')
    ->group(function () {
        Route::get('/dashboard', [TenantDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [TenantProfileController::class, 'show'])->name('profile.show');
        Route::get('/payments', [TenantPaymentController::class, 'index'])->name('payments.index');
        Route::get('/complaints', [TenantComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/complaints/create', [TenantComplaintController::class, 'create'])->name('complaints.create');
        Route::post('/complaints', [TenantComplaintController::class, 'store'])->name('complaints.store');
        Route::get('/announcements', [TenantAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/faq', [TenantFaqController::class, 'index'])->name('faq.index');
        // Each module below adds its routes inside this group
    });