<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaperController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectSetupController;
use App\Http\Controllers\TitleAssistantController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
| Authentication (Breeze-style controllers using the design's auth pages)
*/
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
});

/*
| The application
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('profile/photo', [ProfileController::class, 'uploadPhoto'])->middleware('throttle:uploads')->name('profile.photo.store');
    Route::delete('profile/photo', [ProfileController::class, 'removePhoto'])->name('profile.photo.destroy');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Project wizard: step 1 (research type), step 2 (payment), step 3 (title & details, title assistant), step 4 (sources), step 5 (review & create)
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/new', [ProjectController::class, 'chooseLevel'])->name('projects.new');
    Route::post('projects/new', [ProjectController::class, 'storeLevel'])->name('projects.new.store');
    Route::get('projects/payment', [PaymentController::class, 'show'])->name('projects.payment');
    Route::post('projects/payment', [PaymentController::class, 'store'])->middleware('throttle:10,1')->name('projects.payment.store');
    Route::get('payments/callback', [PaymentController::class, 'callback'])->name('payments.callback');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    // Title suggestions: the only AI feature
    Route::get('projects/title-assistant', [TitleAssistantController::class, 'show'])->name('title-assistant');
    Route::post('projects/title-assistant', [TitleAssistantController::class, 'suggest'])->middleware('throttle:title-suggestions')->name('title-assistant.suggest');
    Route::post('projects/title-assistant/more', [TitleAssistantController::class, 'more'])->middleware('throttle:title-suggestions')->name('title-assistant.more');
    Route::post('projects/title-assistant/choose', [TitleAssistantController::class, 'choose'])->name('title-assistant.choose');
    Route::post('projects/title-assistant/reset', [TitleAssistantController::class, 'reset'])->name('title-assistant.reset');

    Route::prefix('projects/{project}')->group(function () {
        Route::get('/', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::get('progress', [ProjectController::class, 'progress'])->name('projects.progress');

        Route::get('setup/sources', [ProjectSetupController::class, 'sources'])->name('projects.setup.sources');
        Route::get('setup/review', [ProjectSetupController::class, 'review'])->name('projects.setup.review');
        Route::post('submit', [ProjectSetupController::class, 'submit'])->name('projects.submit');
        Route::post('respond', [ProjectSetupController::class, 'respond'])->name('projects.respond');

        Route::get('sources', [PaperController::class, 'index'])->name('projects.sources');
        Route::post('papers', [PaperController::class, 'store'])->middleware('throttle:uploads')->name('papers.store');

        // Stage 2: the researcher's data (unlocked once Stage 1 is delivered)
        Route::get('data', [DatasetController::class, 'index'])->name('projects.data');
        Route::post('datasets', [DatasetController::class, 'store'])->middleware('throttle:uploads')->name('datasets.store');
        Route::get('datasets/status', [DatasetController::class, 'status'])->name('datasets.status');
        Route::post('data/submit', [DatasetController::class, 'submit'])->name('data.submit');

        // Documents delivered by the team
        Route::get('documents/{document}', [DeliverableController::class, 'download'])->name('deliverables.download');
    });

    Route::delete('papers/{paper}', [PaperController::class, 'destroy'])->name('papers.destroy');
    Route::get('papers/{paper}/file', [PaperController::class, 'file'])->name('papers.file');

    Route::delete('datasets/{dataset}', [DatasetController::class, 'destroy'])->name('datasets.destroy');
    Route::get('datasets/{dataset}/file', [DatasetController::class, 'file'])->name('datasets.file');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
});

/*
| Administrator dashboard
*/
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
    Route::post('users/{user}/admin', [Admin\UserController::class, 'toggleAdmin'])->name('users.admin');
    Route::post('users/{user}/suspend', [Admin\UserController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{user}/unsuspend', [Admin\UserController::class, 'unsuspend'])->name('users.unsuspend');
    Route::post('users/{user}/verify', [Admin\UserController::class, 'verify'])->name('users.verify');
    Route::post('users/{user}/password-reset', [Admin\UserController::class, 'sendPasswordReset'])->middleware('throttle:6,1')->name('users.password-reset');
    Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

    // Editing workflow: the work queue and everything the admin does with drafts and edited versions
    Route::get('queue', [Admin\WorkQueueController::class, 'index'])->name('queue');

    Route::get('projects', [Admin\ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [Admin\ProjectController::class, 'show'])->name('projects.show');
    Route::delete('projects/{project}', [Admin\ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::prefix('projects/{project}')->name('workflow.')->controller(Admin\WorkflowController::class)->group(function () {
        Route::get('papers/{paper}/download', 'paper')->name('paper');
        Route::get('sources.zip', 'sources')->name('sources');
        Route::get('datasets/{dataset}/download', 'dataset')->name('dataset');
        Route::get('data.zip', 'data')->name('data');
        Route::get('documents/{document}/download', 'download')->name('download');
        Route::post('in-progress', 'markInProgress')->name('in-progress');
        Route::post('documents', 'upload')->middleware('throttle:uploads')->name('upload');
        Route::post('deliver', 'deliver')->name('deliver');
        Route::post('request-info', 'requestInfo')->name('request-info');
        Route::post('hold', 'hold')->name('hold');
        Route::post('resume', 'resume')->name('resume');
    });

    Route::get('payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/export', [Admin\PaymentController::class, 'export'])->name('payments.export');
    Route::post('payments/{payment}/verify', [Admin\PaymentController::class, 'verify'])->name('payments.verify');

    Route::get('usage', Admin\UsageController::class)->name('usage');
    Route::get('usage/export', [Admin\UsageController::class, 'export'])->name('usage.export');

    Route::get('jobs', [Admin\JobController::class, 'index'])->name('jobs.index');
    Route::post('jobs/{uuid}/retry', [Admin\JobController::class, 'retry'])->name('jobs.retry');
    Route::delete('jobs/{uuid}', [Admin\JobController::class, 'destroy'])->name('jobs.destroy');
    Route::delete('jobs', [Admin\JobController::class, 'flush'])->name('jobs.flush');

    Route::get('activity', Admin\AuditLogController::class)->name('activity');

    Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings');
    Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
});

// Paystack server-to-server notifications (signed; exempt from CSRF in bootstrap/app.php).
Route::post('webhooks/paystack', [PaymentController::class, 'webhook'])->middleware('throttle:120,1')->name('webhooks.paystack');
