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
use App\Http\Controllers\SettingController;
use App\Http\Controllers\MaterialPlanningController;
use App\Http\Controllers\MaterialPlanning\AreaController;
use App\Http\Controllers\MaterialPlanning\CatalogItemController;
use App\Http\Controllers\MaterialPlanning\ChangeOrderController;
use App\Http\Controllers\MaterialPlanning\ChangeOrderLineController;
use App\Http\Controllers\MaterialPlanning\DomainController;
use App\Http\Controllers\MaterialPlanning\ItemGroupController;
use App\Http\Controllers\MaterialPlanning\ItemSubgroupController;
use App\Http\Controllers\MaterialPlanning\MaterialRequestController;
use App\Http\Controllers\MaterialPlanning\RequestLineController;
use App\Http\Controllers\MaterialPlanning\ServiceOptionController;
use App\Http\Controllers\MaterialPlanning\ServiceOptionItemController;
use App\Http\Controllers\MaterialPlanning\SpaceController;
use App\Http\Controllers\MaterialPlanning\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserExportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('Auth/Login', 'Auth/Login')->name('mylogin');
Route::inertia('Auth/Register', 'Auth/Register')->name('myregister');
Route::inertia('Auth/ForgotPassword', 'Auth/ForgotPassword')->name('myforgotpassword');

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
    return Inertia::render('Auth/Confirmation', [
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
        return redirect()->route('material-planning.index');
    })->name('home');

    Route::get('/statuses', [GlobalStatusController::class, 'getStatuses'])->name('global.statuses.get');

    // Route::post('forget-password', [PasswordResetLinkController::class, 'submitForgetPasswordForm'])->name('forgot.password.post');

    // Read endpoints shared with non-admin users (event switcher, general Settings'
    // "Users & roles" tab) stay outside the admin gate below — everything else in
    // Security / EMS Settings / App Setups is admin-only, both in the UI (Index.vue
    // hides these sidebar sections for non-admins) and here at the route level.
    Route::get('/api/events', [EventController::class, 'data'])->name('events.data');
    Route::get('/api/users', [UserController::class, 'data'])->name('users.data');

    Route::middleware('role:admin')->group(function () {
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

        Route::get('/users/export', [UserExportController::class, 'export'])->name('users.export');

        Route::controller(UserController::class)->group(function () {
            Route::get('/users', 'index')->name('users.index'); // Inertia page
            Route::get('/api/users/{user}/roles', 'roles')->name('users.roles');
            Route::get('/api/users/{user}/functional-areas', 'functionalAreas')->name('users.functional-areas');
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
    });

    Route::inertia('mypage', 'Mypage')->name('mypage');

    Route::get('/material-planning', [MaterialPlanningController::class, 'index'])->name('material-planning.index');
    Route::put('/api/material-planning/active-event', [MaterialPlanningController::class, 'setActiveEvent'])->name('material-planning.active-event.update');

    Route::put('/api/settings/approvals-enabled', [SettingController::class, 'updateApprovalsEnabled'])->name('settings.approvals-enabled.update');
    Route::put('/api/settings/show-item-values', [SettingController::class, 'updateShowItemValues'])->name('settings.show-item-values.update');

    Route::controller(CatalogItemController::class)->group(function () {
        Route::post('/api/mp/catalog-items', 'store')->name('mp.catalog-items.store');
        Route::put('/api/mp/catalog-items/{catalogItem}', 'update')->name('mp.catalog-items.update');
        Route::delete('/api/mp/catalog-items/{catalogItem}', 'destroy')->name('mp.catalog-items.destroy');
    });

    Route::controller(ServiceOptionController::class)->group(function () {
        Route::post('/api/mp/service-options', 'store')->name('mp.service-options.store');
        Route::put('/api/mp/service-options/{serviceOption}', 'update')->name('mp.service-options.update');
        Route::delete('/api/mp/service-options/{serviceOption}', 'destroy')->name('mp.service-options.destroy');
    });

    Route::controller(ServiceOptionItemController::class)->group(function () {
        Route::post('/api/mp/service-option-items', 'store')->name('mp.service-option-items.store');
        Route::put('/api/mp/service-option-items/{serviceOptionItem}', 'update')->name('mp.service-option-items.update');
        Route::delete('/api/mp/service-option-items/{serviceOptionItem}', 'destroy')->name('mp.service-option-items.destroy');
    });

    // App Setups — admin-only, both hidden in Index.vue's sidebar and gated here.
    Route::middleware('role:admin')->group(function () {
        Route::controller(SupplierController::class)->group(function () {
            Route::get('/api/mp/suppliers', 'data')->name('mp.suppliers.data');
            Route::post('/api/mp/suppliers', 'store')->name('mp.suppliers.store');
            Route::put('/api/mp/suppliers/{supplier}', 'update')->name('mp.suppliers.update');
            Route::delete('/api/mp/suppliers/{supplier}', 'destroy')->name('mp.suppliers.destroy');
        });

        Route::controller(DomainController::class)->group(function () {
            Route::get('/api/mp/domains', 'data')->name('mp.domains.data');
            Route::post('/api/mp/domains', 'store')->name('mp.domains.store');
            Route::put('/api/mp/domains/{domain}', 'update')->name('mp.domains.update');
            Route::delete('/api/mp/domains/{domain}', 'destroy')->name('mp.domains.destroy');
        });

        Route::controller(AreaController::class)->group(function () {
            Route::get('/api/mp/areas', 'data')->name('mp.areas.data');
            Route::post('/api/mp/areas', 'store')->name('mp.areas.store');
            Route::put('/api/mp/areas/{area}', 'update')->name('mp.areas.update');
            Route::delete('/api/mp/areas/{area}', 'destroy')->name('mp.areas.destroy');
        });

        Route::controller(SpaceController::class)->group(function () {
            Route::get('/api/mp/spaces', 'data')->name('mp.spaces.data');
            Route::post('/api/mp/spaces', 'store')->name('mp.spaces.store');
            Route::put('/api/mp/spaces/{space}', 'update')->name('mp.spaces.update');
            Route::delete('/api/mp/spaces/{space}', 'destroy')->name('mp.spaces.destroy');
        });

        Route::controller(ItemGroupController::class)->group(function () {
            Route::get('/api/mp/item-groups', 'data')->name('mp.item-groups.data');
            Route::post('/api/mp/item-groups', 'store')->name('mp.item-groups.store');
            Route::put('/api/mp/item-groups/{itemGroup}', 'update')->name('mp.item-groups.update');
            Route::delete('/api/mp/item-groups/{itemGroup}', 'destroy')->name('mp.item-groups.destroy');
        });

        Route::controller(ItemSubgroupController::class)->group(function () {
            Route::get('/api/mp/item-subgroups', 'data')->name('mp.item-subgroups.data');
            Route::post('/api/mp/item-subgroups', 'store')->name('mp.item-subgroups.store');
            Route::put('/api/mp/item-subgroups/{itemSubgroup}', 'update')->name('mp.item-subgroups.update');
            Route::delete('/api/mp/item-subgroups/{itemSubgroup}', 'destroy')->name('mp.item-subgroups.destroy');
        });
    });

    Route::controller(MaterialRequestController::class)->group(function () {
        Route::get('/api/mp/requests/{code}', 'show')->name('mp.requests.show');
        Route::post('/api/mp/requests', 'store')->name('mp.requests.store');
        Route::put('/api/mp/requests/{materialRequest}', 'update')->name('mp.requests.update');
        Route::post('/api/mp/requests/{materialRequest}/submit', 'submit')->name('mp.requests.submit');
        Route::delete('/api/mp/requests/{materialRequest}', 'destroy')->name('mp.requests.destroy');
        Route::delete('/api/mp/requests', 'bulkDestroy')->name('mp.requests.bulk-destroy');
    });

    Route::controller(RequestLineController::class)->group(function () {
        Route::post('/api/mp/requests/{materialRequest}/lines', 'store')->name('mp.request-lines.store');
        Route::put('/api/mp/request-lines/{requestLine}', 'update')->name('mp.request-lines.update');
        Route::delete('/api/mp/request-lines/{requestLine}', 'destroy')->name('mp.request-lines.destroy');
        Route::put('/api/mp/request-lines', 'bulkAssignServiceOption')->name('mp.request-lines.bulk-assign');
    });

    Route::controller(ChangeOrderController::class)->group(function () {
        Route::post('/api/mp/change-orders', 'store')->name('mp.change-orders.store');
        Route::put('/api/mp/change-orders/{changeOrder}', 'update')->name('mp.change-orders.update');
        Route::delete('/api/mp/change-orders/{changeOrder}', 'destroy')->name('mp.change-orders.destroy');
    });

    Route::controller(ChangeOrderLineController::class)->group(function () {
        Route::post('/api/mp/change-orders/{changeOrder}/lines', 'store')->name('mp.change-order-lines.store');
        Route::put('/api/mp/change-order-lines/{changeOrderLine}', 'update')->name('mp.change-order-lines.update');
        Route::delete('/api/mp/change-order-lines/{changeOrderLine}', 'destroy')->name('mp.change-order-lines.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
