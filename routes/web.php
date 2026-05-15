<?php

use App\Http\Controllers\Auth\MicrosoftController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Ems\EventController;
use App\Http\Controllers\Ems\FunctionalAreaController;
use App\Http\Controllers\Ems\VenueController;
use App\Http\Controllers\GlobalStatusController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolesPermissionController;
use App\Http\Controllers\MaterialPlanningController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserExportController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('MyAuth/Login', 'MyAuth/Login')->name('mylogin');
Route::inertia('MyAuth/Register', 'MyAuth/Register')->name('myregister');
Route::inertia('MyAuth/ForgotPassword', 'MyAuth/ForgotPassword')->name('myforgotpassword');

Route::middleware('otp.pending')->group(function () {
    Route::get('/otp', [OtpController::class, 'show'])->name('otp.show');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->name('otp.resend');
});

Route::controller(MicrosoftController::class)->group(function () {
    Route::get('auth/microsoft/redirect', 'redirectToMicrosoft')->name('auth.microsoft');
    Route::get('auth/microsoft/callback', 'handleMicrosoftCallback');
});


Route::get('password/confirmed', function () {
    return Inertia::render('MyAuth/Confirmation', [
        'icon'          => 'bx bx-check-circle',
        'iconColor'     => 'text-success',
        'title'         => 'Password Changed!',
        'message'       => session('status'),
        'buttonText'    => 'Sign In',
        'buttonHref'    => route('mylogin'),
        'buttonVariant' => 'primary',
    ]);
})->name('password.confirmed');

// check email route
// Route::get('/send-mail', [SendMailController::class, 'index']);

// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/', function () {
        return Inertia::render('Mypage', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    })->name('home');

    Route::get('/users/export', [UserExportController::class, 'export'])->name('users.export');
    Route::get('/statuses', [GlobalStatusController::class, 'getStatuses'])->name('global.statuses.get');

    // Route::post('forget-password', [PasswordResetLinkController::class, 'submitForgetPasswordForm'])->name('forgot.password.post');

    Route::controller(PermissionController::class)->group(function () {
        Route::get('/permissions', 'index')->name('permissions.index');
        Route::get('/api/permissions', 'data')->name('permissions.data');
        Route::post('/permissions', 'store')->name('permissions.store');
        Route::get('/permissions/{permission}', 'show')->name('permissions.show');
        Route::put('/permissions/{permission}', 'update')->name('permissions.update');
        Route::delete('/permissions/{permission}', 'destroy')->name('permissions.destroy');
    });

    Route::controller(EventController::class)->group(function () {
        Route::get('/events', 'index')->name('events.index');
        Route::get('/api/events', 'data')->name('events.data');
        Route::post('/events', 'store')->name('events.store');
        Route::get('/events/{event}', 'show')->name('events.show');
        Route::match(['put', 'post'], '/events/{event}', 'update')->name('events.update');
        Route::delete('/events/{event}', 'destroy')->name('events.destroy');
    });

    Route::controller(VenueController::class)->group(function () {
        Route::get('/venues', 'index')->name('venues.index');
        Route::get('/api/venues', 'data')->name('venues.data');
        Route::get('/api/venues/all', 'all')->name('venues.all');
        Route::post('/venues', 'store')->name('venues.store');
        Route::get('/venues/{venue}', 'show')->name('venues.show');
        Route::match(['put', 'post'], '/venues/{venue}', 'update')->name('venues.update');
        Route::delete('/venues/{venue}', 'destroy')->name('venues.destroy');
    });

    Route::controller(FunctionalAreaController::class)->group(function () {
        Route::get('/functional-areas', 'index')->name('functional-areas.index');
        Route::get('/api/functional-areas', 'data')->name('functional-areas.data');
        Route::get('/api/functional-areas/all', 'all')->name('functional-areas.all');
        Route::post('/functional-areas', 'store')->name('functional-areas.store');
        Route::get('/functional-areas/{functionalArea}', 'show')->name('functional-areas.show');
        Route::match(['put', 'post'], '/functional-areas/{functionalArea}', 'update')->name('functional-areas.update');
        Route::delete('/functional-areas/{functionalArea}', 'destroy')->name('functional-areas.destroy');
    });

    Route::controller(RolesPermissionController::class)->group(function () {
        Route::get('/roles-permissions', 'index')->name('roles-permissions.index');
        Route::get('/api/roles-permissions', 'data')->name('roles-permissions.data');
        // Flat lists for the assign modal (must be before /{role} routes)
        Route::get('/api/roles-permissions/all-permissions', 'allPermissions')->name('roles-permissions.all-permissions');
        Route::get('/api/roles-permissions/all-roles', 'allRoles')->name('roles-permissions.all-roles');
        // CRUD
        Route::put('/api/roles-permissions/{role}', 'syncPermissions')->name('roles-permissions.sync');
        Route::delete('/api/roles-permissions/{role}', 'destroy')->name('roles-permissions.destroy');
    });

    Route::controller(UserController::class)->group(function () {
        Route::get('/users', 'index')->name('users.index'); // Inertia page
        Route::get('/api/users', 'data')->name('users.data'); // Bootstrap Table endpoint
        // Route::post('/users/table', 'table')->name('users.table');
        // Route::match(['get','post'], '/users', 'index')->name('users.index');
        // Route::get('/api/users', 'data')->name('users.data'); // AJAX data endpoint

        // Route::get('/users', 'index')->name('users.index');
        Route::get('/api/users/{user}/roles', 'roles')->name('users.roles');
        Route::post('/users', 'store')->name('users.store');
        Route::put('/users/{user}', 'update')->name('users.update');
        Route::delete('/users/{user}', 'destroy')->name('users.destroy');
    });

    Route::controller(RoleController::class)->group(function () {
        Route::get('/roles', 'index')->name('roles.index');
        Route::get('/api/roles', 'data')->name('roles.data');
        Route::post('/roles', 'store')->name('roles.store');
        Route::put('/roles/{role}', 'update')->name('roles.update');
        Route::delete('/roles/{role}', 'destroy')->name('roles.destroy');
    });

    Route::inertia('mypage', 'Mypage')->name('mypage');

    Route::get('/material-planning', [MaterialPlanningController::class, 'index'])->name('material-planning.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
