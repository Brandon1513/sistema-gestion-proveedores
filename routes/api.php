<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProviderTypeController;
use App\Http\Controllers\Api\ProviderProfileController;
use App\Http\Controllers\Api\ProviderVehicleController;
use App\Http\Controllers\Api\ProviderDocumentController;
use App\Http\Controllers\Api\QualityDashboardController;
use App\Http\Controllers\Api\ProviderDashboardController;
use App\Http\Controllers\Api\DocumentValidationController;
use App\Http\Controllers\Api\ProviderInvitationController;
use App\Http\Controllers\Api\ProviderCertificationController;
use App\Http\Controllers\Api\ProviderDocumentUploadController;
use App\Http\Controllers\Api\ProfileController;
 
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProviderAccountController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register-provider', [AuthController::class, 'registerProvider']);
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password',  [PasswordResetController::class, 'resetPassword']);
Route::get('/invitations/verify/{token}', [ProviderInvitationController::class, 'verify']);

// Esta ruta maneja su propia autenticación mediante el token en query parameter
Route::get('/providers/{provider}/documents/{document}/view', [ProviderDocumentController::class, 'view'])
    ->name('providers.documents.view');

// Rutas protegidas por autenticación
Route::middleware('auth:sanctum')->group(function () {
    
    // ===============================
    // AUTH
    // ===============================
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ===============================
    // PERFIL DE USUARIO
    // ===============================
    Route::get('/me/profile',          [ProfileController::class, 'show']);
    Route::put('/me/profile',          [ProfileController::class, 'update']);
    Route::patch('/me/password',       [ProfileController::class, 'updatePassword']);
    // ===============================
    // Administración de cuentas de proveedores (solo para super_admin y admin)
    // ===============================
    Route::middleware(['role:super_admin,admin'])->prefix('provider-accounts')->group(function () {
    Route::get('/',                        [ProviderAccountController::class, 'index']);
    Route::patch('/{id}/toggle-status',    [ProviderAccountController::class, 'toggleStatus']);
    Route::patch('/{id}/reset-password',   [ProviderAccountController::class, 'resetPassword']);
    Route::post('/{id}/send-reset',        [ProviderAccountController::class, 'sendReset']);
    });
    
    // ===============================
    // DASHBOARD
    // ===============================
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics']);
    Route::get('/documents/expiring', [DashboardController::class, 'expiringDocuments']);
    // ===============================
    // TIPOS DE PROVEEDORES
    // ===============================
    Route::get('/provider-types', [ProviderTypeController::class, 'index']);
    Route::get('/provider-types/{providerType}', [ProviderTypeController::class, 'show']);
    Route::get('/provider-types/{providerType}/required-documents', [ProviderTypeController::class, 'requiredDocuments']);
    
    // ===============================
    // PROVEEDORES
    // ===============================
    Route::apiResource('providers', ProviderController::class);
    Route::patch('/providers/{provider}/status', [ProviderController::class, 'updateStatus']);

    // Rutas específicas para proveedores
    Route::prefix('provider')->middleware('role:proveedor')->group(function () {
        Route::get('/dashboard/stats', [ProviderDashboardController::class, 'stats']);
        Route::get('/documents', [ProviderDashboardController::class, 'documents']);
        Route::get('/documents/required', [ProviderDashboardController::class, 'requiredDocuments']);
        Route::get('/documents/expiring', [ProviderDashboardController::class, 'expiringDocuments']);
        //  PROVEEDOR RUTAS DE UPLOAD
        Route::post('/documents/upload', [ProviderDocumentUploadController::class, 'upload']);
        Route::delete('/documents/{id}', [ProviderDocumentUploadController::class, 'delete']);
        Route::get('/documents/{id}/download', [ProviderDocumentUploadController::class, 'download']);

        // RUTAS DE PERFIL
        Route::get('/profile', [ProviderProfileController::class, 'show']);
        Route::put('/profile', [ProviderProfileController::class, 'update']);
        
        // Contactos
        Route::get('/contacts', [ProviderProfileController::class, 'contacts']);
        Route::post('/contacts', [ProviderProfileController::class, 'storeContact']);
        Route::delete('/contacts/{id}', [ProviderProfileController::class, 'deleteContact']);
        
        // Vehículos
        Route::get('/vehicles', [ProviderProfileController::class, 'vehicles']);
        Route::post('/vehicles', [ProviderProfileController::class, 'storeVehicle']);
        Route::delete('/vehicles/{id}', [ProviderProfileController::class, 'deleteVehicle']);
        
        // Personal
        Route::get('/personnel', [ProviderProfileController::class, 'personnel']);
        Route::post('/personnel', [ProviderProfileController::class, 'storePersonnel']);
        Route::delete('/personnel/{id}', [ProviderProfileController::class, 'deletePersonnel']);

        //  CERTIFICACIONES DEL PROVEEDOR
    Route::get('/certifications', [ProviderCertificationController::class, 'myIndex']);
    Route::post('/certifications', [ProviderCertificationController::class, 'myStore']);
    Route::put('/certifications/{certification}', [ProviderCertificationController::class, 'myUpdate']);
    Route::delete('/certifications/{certification}', [ProviderCertificationController::class, 'myDestroy']);
    });

    
    // Vehículos de proveedores
    Route::get('/providers/{provider}/vehicles', [ProviderVehicleController::class, 'index']);
    Route::post('/providers/{provider}/vehicles', [ProviderVehicleController::class, 'store']);
    Route::put('/providers/{provider}/vehicles/{vehicle}', [ProviderVehicleController::class, 'update']);
    Route::delete('/providers/{provider}/vehicles/{vehicle}', [ProviderVehicleController::class, 'destroy']);
    
    // Certificaciones de proveedores
    Route::get('/providers/{provider}/certifications', [ProviderCertificationController::class, 'index']);
    Route::post('/providers/{provider}/certifications', [ProviderCertificationController::class, 'store']);
    Route::put('/providers/{provider}/certifications/{certification}', [ProviderCertificationController::class, 'update']);
    Route::delete('/providers/{provider}/certifications/{certification}', [ProviderCertificationController::class, 'destroy']);
    
    // ===============================
    // DOCUMENTOS DE PROVEEDORES
    // ===============================
    Route::get('/providers/{provider}/documents', [ProviderDocumentController::class, 'index']);
    Route::post('/providers/{provider}/documents', [ProviderDocumentController::class, 'store']);
    Route::get('/providers/{provider}/documents/required', [ProviderDocumentController::class, 'required']);
    
    // ⭐ IMPORTANTE: Ruta de descarga - debe estar ANTES del delete
    
    Route::get('/providers/{provider}/documents/{document}/download', [ProviderDocumentController::class, 'download']);
    // Eliminar documento
    Route::delete('/providers/{provider}/documents/{document}', [ProviderDocumentController::class, 'destroy']);
    
    // ===============================
    // VALIDACIÓN DE DOCUMENTOS
    // Solo accesible para: super_admin, admin, calidad
    // ===============================
    Route::middleware(['role:super_admin,admin,calidad'])->group(function () {
        // Obtener documentos pendientes de validación
        Route::get('/documents/pending', [DocumentValidationController::class, 'pending']);
        
        // Validar documento específico (aprobar/rechazar)
        Route::post('/providers/{provider}/documents/{document}/validate', [DocumentValidationController::class, 'validate']);
    });
    
    // ⭐ HISTORIAL DE VALIDACIONES - ACCESIBLE PARA TODOS (con control interno)
    // Esta ruta debe estar FUERA del middleware role porque el método history()
    // ya controla los permisos internamente
    Route::get('/documents/{document}/history', [DocumentValidationController::class, 'history']);


    Route::middleware(['role:super_admin,admin,calidad'])->group(function () {
    Route::get('/quality/dashboard/stats', [QualityDashboardController::class, 'stats']);
    Route::get('/quality/dashboard/activity', [QualityDashboardController::class, 'recentActivity']);
});
     // ===============================
    // USUARIOS
    // ===============================
    Route::middleware(['role:super_admin'])->prefix('users')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\UserManagementController::class, 'index']);
    Route::get('/roles', [App\Http\Controllers\Api\UserManagementController::class, 'getRoles']);
    Route::post('/', [App\Http\Controllers\Api\UserManagementController::class, 'store']);
    Route::get('/{id}', [App\Http\Controllers\Api\UserManagementController::class, 'show']);
    Route::put('/{id}', [App\Http\Controllers\Api\UserManagementController::class, 'update']);
    Route::patch('/{id}/password', [App\Http\Controllers\Api\UserManagementController::class, 'updatePassword']);
    Route::patch('/{id}/toggle-status', [App\Http\Controllers\Api\UserManagementController::class, 'toggleStatus']);
    Route::delete('/{id}', [App\Http\Controllers\Api\UserManagementController::class, 'destroy']);
});

    
    // ===============================
    // INVITACIONES
    // ===============================
    Route::get('/invitations', [ProviderInvitationController::class, 'index']);
    Route::post('/invitations', [ProviderInvitationController::class, 'store']);
    Route::post('/invitations/{invitation}/resend', [ProviderInvitationController::class, 'resend']);
    Route::delete('/invitations/{invitation}', [ProviderInvitationController::class, 'cancel']);
});