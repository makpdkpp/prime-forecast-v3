<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyRequestController;
use App\Http\Controllers\Admin\IndustryController;
use App\Http\Controllers\Admin\MigrationController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\PriorityController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SourceController;
use App\Http\Controllers\Admin\StepController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\McpAuditLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\McpDemoTokenController;
use App\Http\Controllers\McpOAuthController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\TeamAdminController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

// Auth routes (no middleware required)
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetPasswordLink'])->middleware('throttle:3,1')->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('password.update');
Route::get('/post-login-loading', [AuthController::class, 'postLoginLoading'])->name('postlogin.loading');
Route::get('/logout', [AuthController::class, 'logout']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/.well-known/oauth-protected-resource', [McpOAuthController::class, 'protectedResource'])->name('mcp.oauth.protected-resource');
Route::get('/.well-known/oauth-authorization-server', [McpOAuthController::class, 'authorizationServer'])->name('mcp.oauth.authorization-server');
Route::post('/oauth/register', [McpOAuthController::class, 'register'])->middleware('throttle:10,1')->name('mcp.oauth.register');
Route::get('/oauth/authorize', [McpOAuthController::class, 'authorizeRequest'])->middleware('throttle:30,1')->name('mcp.oauth.authorize');
Route::post('/oauth/token', [McpOAuthController::class, 'token'])->middleware('throttle:60,1')->name('mcp.oauth.token');

// Registration routes (user invitation)
Route::get('/register/{token}', [RegistrationController::class, 'showRegistrationForm'])->name('register');
Route::post('/register/{token}', [RegistrationController::class, 'register'])->name('register.submit');

// 2FA Routes (no auth middleware)
Route::get('/2fa/verify', [AuthController::class, 'showTwoFactorVerify'])->name('2fa.verify');
Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:5,1')->name('2fa.verify.submit');
Route::post('/2fa/resend', [AuthController::class, 'resendTwoFactorCode'])->middleware('throttle:2,1')->name('2fa.resend');

// Protected routes with auth middleware
Route::middleware(['auth'])->group(function () {

    // Demo-only MCP token management. The controller also requires 2FA to be enabled.
    Route::get('/mcp-demo-token', [McpDemoTokenController::class, 'show'])
        ->name('mcp-demo-token.show');
    Route::post('/mcp-demo-token', [McpDemoTokenController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('mcp-demo-token.store');
    Route::delete('/mcp-demo-token', [McpDemoTokenController::class, 'destroy'])
        ->name('mcp-demo-token.destroy');

    // Admin routes (role_id = 1)
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/table', [AdminController::class, 'dashboardTable'])->name('dashboard.table');
        Route::get('/dashboard/table/data', [AdminController::class, 'dashboardTableData'])->name('dashboard.table.data');
        Route::get('/dashboard/win-projects/{userId}', [AdminController::class, 'winProjectsByUser'])->name('dashboard.winProjects');
        Route::get('/dashboard/chart-detail', [AdminController::class, 'chartDetail'])->name('dashboard.chartDetail');
        Route::get('/sales/{id}/edit', [AdminController::class, 'editSales'])->name('sales.edit');
        Route::put('/sales/{id}', [AdminController::class, 'updateSales'])->middleware('throttle:30,1')->name('sales.update');
        Route::delete('/sales/{id}', [AdminController::class, 'deleteSales'])->middleware('throttle:30,1')->name('sales.delete');
        Route::get('/sales/{id}/transfer', [AdminController::class, 'transferSales'])->name('sales.transfer');
        Route::post('/sales/{id}/transfer', [AdminController::class, 'processTransfer'])->name('sales.transfer.process');
        Route::get('/sales/{id}/transfer-history', [AdminController::class, 'getTransferHistory'])->name('sales.transfer.history');
        Route::get('/profile', [AdminController::class, 'profile'])->name('profile');
        Route::put('/profile', [AdminController::class, 'updateProfile'])->name('profile.update');
        Route::post('/profile/toggle-2fa', [AdminController::class, 'toggleTwoFactor'])->name('profile.toggle-2fa');
        Route::get('/mcp-audit-logs', [McpAuditLogController::class, 'index'])->name('mcp-audit-logs.index');
        Route::get('/mcp-audit-logs/export', [McpAuditLogController::class, 'export'])->name('mcp-audit-logs.export');

        // Company Request Management
        Route::get('/company-requests', [CompanyRequestController::class, 'index'])->name('company-requests.index');
        Route::post('/company-requests/{id}/approve', [CompanyRequestController::class, 'approve'])->name('company-requests.approve');
        Route::post('/company-requests/{id}/reject', [CompanyRequestController::class, 'reject'])->name('company-requests.reject');
        Route::delete('/company-requests/{id}', [CompanyRequestController::class, 'destroy'])->name('company-requests.destroy');

        // Master Data Management
        Route::resource('companies', CompanyController::class);
        Route::resource('products', ProductController::class);
        Route::resource('industries', IndustryController::class);
        Route::resource('sources', SourceController::class);
        Route::resource('steps', StepController::class);
        Route::resource('priorities', PriorityController::class);
        Route::resource('teams', TeamController::class);
        Route::resource('positions', PositionController::class);
        Route::resource('users', UserManagementController::class);
        Route::patch('/users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');

        // Reports
        Route::get('/reports', [AdminController::class, 'reportsIndex'])->name('reports.index');
        Route::get('/reports/bidding', [AdminController::class, 'reportBidding'])->name('reports.bidding');
        Route::post('/reports/bidding/data', [AdminController::class, 'reportBiddingData'])->name('reports.bidding.data');
        Route::get('/reports/contract', [AdminController::class, 'reportContract'])->name('reports.contract');
        Route::post('/reports/contract/data', [AdminController::class, 'reportContractData'])->name('reports.contract.data');
        Route::get('/reports/windate', [AdminController::class, 'reportWindate'])->name('reports.windate');
        Route::post('/reports/windate/data', [AdminController::class, 'reportWindateData'])->name('reports.windate.data');

        // Migration management is deliberately absent from production routes.
        if (! app()->isProduction()) {
            Route::get('/migration', [MigrationController::class, 'index'])->name('migration.index');
            Route::get('/migration/status', [MigrationController::class, 'status'])->name('migration.status');
            Route::post('/migration/run', [MigrationController::class, 'run'])->name('migration.run');
            Route::post('/migration/run-single/{migration}', [MigrationController::class, 'runSingle'])->name('migration.run-single');
            Route::post('/migration/rollback', [MigrationController::class, 'rollback'])->name('migration.rollback');
            Route::get('/migration/schema', [MigrationController::class, 'schema'])->name('migration.schema');
            Route::get('/migration/logs', [MigrationController::class, 'logs'])->name('migration.logs');
        }
    });

    // Team Admin routes (role_id = 2)
    Route::middleware(['teamadmin'])->prefix('teamadmin')->name('teamadmin.')->group(function () {
        Route::get('/dashboard', [TeamAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/table', [TeamAdminController::class, 'dashboardTable'])->name('dashboard.table');
        Route::get('/dashboard/table/data', [TeamAdminController::class, 'dashboardTableData'])->name('dashboard.table.data');
        Route::get('/dashboard/chart-detail', [TeamAdminController::class, 'chartDetail'])->name('dashboard.chartDetail');
        Route::get('/sales/{id}/edit', [TeamAdminController::class, 'editSales'])->name('sales.edit');
        Route::put('/sales/{id}', [TeamAdminController::class, 'updateSales'])->middleware('throttle:30,1')->name('sales.update');
        Route::get('/profile', [TeamAdminController::class, 'profile'])->name('profile');
        Route::put('/profile', [TeamAdminController::class, 'updateProfile'])->name('profile.update');
        Route::post('/profile/toggle-2fa', [TeamAdminController::class, 'toggleTwoFactor'])->name('profile.toggle-2fa');

        // Reports
        Route::get('/reports/bidding', [TeamAdminController::class, 'reportBidding'])->name('reports.bidding');
        Route::post('/reports/bidding/data', [TeamAdminController::class, 'reportBiddingData'])->name('reports.bidding.data');
        Route::get('/reports/contract', [TeamAdminController::class, 'reportContract'])->name('reports.contract');
        Route::post('/reports/contract/data', [TeamAdminController::class, 'reportContractData'])->name('reports.contract.data');
        Route::get('/reports/windate', [TeamAdminController::class, 'reportWindate'])->name('reports.windate');
        Route::post('/reports/windate/data', [TeamAdminController::class, 'reportWindateData'])->name('reports.windate.data');
    });

    // User routes (role_id = 3)
    Route::middleware(['user'])->prefix('user')->name('user.')->group(function () {
        Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/table', [UserController::class, 'dashboardTable'])->name('dashboard.table');
        Route::get('/dashboard/table/data', [UserController::class, 'dashboardTableData'])->name('dashboard.table.data');
        Route::get('/dashboard/chart-detail', [UserController::class, 'chartDetail'])->name('dashboard.chartDetail');
        Route::get('/dashboard/win-projects', [UserController::class, 'winProjects'])->name('dashboard.winProjects');
        Route::get('/sales/create', [UserController::class, 'createSales'])->name('sales.create');
        Route::post('/sales', [UserController::class, 'storeSales'])->middleware('throttle:30,1')->name('sales.store');
        Route::get('/sales/{id}/edit', [UserController::class, 'editSales'])->name('sales.edit');
        Route::get('/sales/{id}/edit-data', [UserController::class, 'getEditDataAjax'])->name('sales.edit.data');
        Route::put('/sales/{id}', [UserController::class, 'updateSales'])->middleware('throttle:30,1')->name('sales.update');
        Route::put('/sales/{id}/ajax', [UserController::class, 'updateSalesAjax'])->middleware('throttle:30,1')->name('sales.update.ajax');
        Route::get('/profile', [UserController::class, 'profile'])->name('profile');
        Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
        Route::post('/profile/toggle-2fa', [UserController::class, 'toggleTwoFactor'])->name('profile.toggle-2fa');
        Route::post('/company-request', [UserController::class, 'requestCompany'])->name('company.request');
    });
});
