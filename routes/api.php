<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarBlockController;
use App\Http\Controllers\Api\CatalogImportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentStatusController;
use App\Http\Controllers\Api\DocumentTemplateController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\DocumentValidationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProductsServicesCatalogController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProviderAccountController;
use App\Http\Controllers\Api\ProviderCertificationController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\ProviderDashboardController;
use App\Http\Controllers\Api\ProviderDocumentController;
use App\Http\Controllers\Api\ProviderDocumentUploadController;
use App\Http\Controllers\Api\ProviderInvitationController;
use App\Http\Controllers\Api\ProviderProfileController;
use App\Http\Controllers\Api\ProviderTypeController;
use App\Http\Controllers\Api\ProviderVehicleController;
use App\Http\Controllers\Api\QualityDashboardController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
    Route::get('/me/profile',    [ProfileController::class, 'show']);
    Route::put('/me/profile',    [ProfileController::class, 'update']);
    Route::patch('/me/password', [ProfileController::class, 'updatePassword']);

    // ===============================
    // ADMINISTRACIÓN DE CUENTAS DE PROVEEDORES
    // ===============================
    Route::middleware(['role:super_admin,admin'])->prefix('provider-accounts')->group(function () {
        Route::get('/',                      [ProviderAccountController::class, 'index']);
        Route::patch('/{id}/toggle-status',  [ProviderAccountController::class, 'toggleStatus']);
        Route::patch('/{id}/reset-password', [ProviderAccountController::class, 'resetPassword']);
        Route::post('/{id}/send-reset',      [ProviderAccountController::class, 'sendReset']);
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
        Route::get('/products-services-my', [ProviderDashboardController::class, 'myProductsServices']);

        // Upload
        Route::post('/documents/upload', [ProviderDocumentUploadController::class, 'upload']);
        Route::delete('/documents/{id}', [ProviderDocumentUploadController::class, 'delete']);
        Route::get('/documents/{id}/download', [ProviderDocumentUploadController::class, 'download']);

        // Perfil
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

        // Certificaciones
        Route::get('/certifications',                        [ProviderCertificationController::class, 'myIndex']);
        Route::post('/certifications',                       [ProviderCertificationController::class, 'myStore']);
        Route::put('/certifications/{certification}',        [ProviderCertificationController::class, 'myUpdate']);
        Route::delete('/certifications/{certification}',     [ProviderCertificationController::class, 'myDestroy']);
        Route::get('/certifications/{certification}/download',[ProviderCertificationController::class, 'myDownload']);

        // Citas proveedor
        Route::get('/appointments', [AppointmentController::class, 'myIndex']);
        Route::post('/appointments/{appointmentId}/complete', [AppointmentController::class, 'providerComplete']);

        // Catálogo proveedor
        Route::get('/products-services', [ProductsServicesCatalogController::class, 'providerGetCatalog']);
        Route::put('/products-services', [ProductsServicesCatalogController::class, 'providerUpdateSelection']);
    });

    // Vista global certificaciones
    Route::get('/certifications', [ProviderCertificationController::class, 'globalIndex']);
    Route::get('/certifications/pending-count', [ProviderCertificationController::class, 'pendingCount']);

    // Validar certificación
    Route::middleware(['role:super_admin,admin,calidad'])->group(function () {
        Route::post('/providers/{provider}/certifications/{certification}/validate',
            [ProviderCertificationController::class, 'validate']);
        Route::get('/providers/{provider}/certifications/{certification}/download',
            [ProviderCertificationController::class, 'download']);
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
    Route::get('/providers/{provider}/documents/{document}/download', [ProviderDocumentController::class, 'download']);
    Route::delete('/providers/{provider}/documents/{document}', [ProviderDocumentController::class, 'destroy']);
    Route::get('/provider-types/{providerType}/all-documents', [ProviderTypeController::class, 'allDocuments']);

    // ===============================
    // VALIDACIÓN DE DOCUMENTOS
    // ===============================
    Route::middleware(['role:super_admin,admin,calidad'])->group(function () {
        Route::get('/documents/pending', [DocumentValidationController::class, 'pending']);
        Route::post('/providers/{provider}/documents/{document}/validate', [DocumentValidationController::class, 'validate']);
    });

    // ===============================
    // GESTIÓN DE TIPOS DE DOCUMENTOS (Calidad + Admin)
    // ── Estáticas primero para evitar conflictos con {id} ──
    // ===============================
    Route::middleware(['role:calidad,super_admin,admin'])->group(function () {
        // Sin parámetros
        Route::get('/document-types',                       [DocumentTypeController::class, 'index']);
        Route::post('/document-types',                      [DocumentTypeController::class, 'store']);
        Route::post('/document-types/reorder',              [DocumentTypeController::class, 'reorder']);
        Route::get('/document-types/provider-types',        [DocumentTypeController::class, 'providerTypes']);
        Route::get('/document-types/groups',                [DocumentTypeController::class, 'getGroups']);
        Route::post('/document-types/groups',               [DocumentTypeController::class, 'storeGroup']);
        Route::post('/document-types/groups/reorder',       [DocumentTypeController::class, 'reorderGroups']);

        // Con {id} — deben ir después de las estáticas
        Route::put('/document-types/{id}',                  [DocumentTypeController::class, 'update']);
        Route::patch('/document-types/{id}/toggle-active',  [DocumentTypeController::class, 'toggleActive']);
        Route::delete('/document-types/{id}/provider-type', [DocumentTypeController::class, 'removeFromProviderType']);
        Route::put('/document-types/groups/{id}',           [DocumentTypeController::class, 'updateGroup']);
        Route::delete('/document-types/groups/{id}',        [DocumentTypeController::class, 'destroyGroup']);
    });

        // ── Gestión de templates (Calidad + Admin) ────────────────────────────────────
    Route::middleware(['role:calidad,super_admin,admin'])->group(function () {
        Route::get('/document-templates',           [App\Http\Controllers\Api\DocumentTemplateController::class, 'index']);
        Route::get('/document-templates/catalog-products', [DocumentTemplateController::class, 'getCatalogProducts']);
        Route::post('/document-templates',          [App\Http\Controllers\Api\DocumentTemplateController::class, 'store']);
        Route::delete('/document-templates/{id}',   [App\Http\Controllers\Api\DocumentTemplateController::class, 'destroy']);
    });

    // ── Gestión de Tipos de Proveedor (CRUD completo) ─────────────────────────
    Route::middleware(['role:super_admin,admin,calidad'])->group(function () {
        Route::post('/provider-types',                                          [ProviderTypeController::class, 'store']);
        Route::put('/provider-types/{providerType}',                            [ProviderTypeController::class, 'update']);
        Route::patch('/provider-types/{providerType}/toggle-active',            [ProviderTypeController::class, 'toggleActive']);
        Route::delete('/provider-types/{providerType}',                         [ProviderTypeController::class, 'destroy']);
        Route::get('/provider-types/{providerType}/documents',                  [ProviderTypeController::class, 'documents']);
        Route::post('/provider-types/{providerType}/documents',                 [ProviderTypeController::class, 'assignDocument']);
        Route::patch('/provider-types/{providerType}/documents/{docId}/toggle-required', [ProviderTypeController::class, 'toggleRequired']);
        Route::delete('/provider-types/{providerType}/documents/{docId}',       [ProviderTypeController::class, 'removeDocument']);
        Route::patch('/provider-types/{providerType}/documents/{docId}/persona', [ProviderTypeController::class, 'updateDocumentPersona']);
    });
    
    // ── Consulta de template por producto (todos los roles autenticados) ──────────
    Route::get('/document-templates/by-product',   [App\Http\Controllers\Api\DocumentTemplateController::class, 'getByProduct']);
    
    // ── Descarga de template (todos los roles autenticados, incluyendo proveedor) ─
    Route::get('/document-templates/{id}/download', [App\Http\Controllers\Api\DocumentTemplateController::class, 'download']);

    // Historial de validaciones (control interno en el método)
    Route::get('/documents/{document}/history', [DocumentValidationController::class, 'history']);

    // ===============================
    // CALIDAD DASHBOARD
    // ===============================
    Route::middleware(['role:super_admin,admin,calidad'])->group(function () {
        Route::get('/quality/dashboard/stats',    [QualityDashboardController::class, 'stats']);
        Route::get('/quality/dashboard/activity', [QualityDashboardController::class, 'recentActivity']);
    });

    // ===============================
    // USUARIOS
    // ===============================
    Route::middleware(['role:super_admin'])->prefix('users')->group(function () {
        Route::get('/',                     [App\Http\Controllers\Api\UserManagementController::class, 'index']);
        Route::get('/roles',                [App\Http\Controllers\Api\UserManagementController::class, 'getRoles']);
        Route::post('/',                    [App\Http\Controllers\Api\UserManagementController::class, 'store']);
        Route::get('/{id}',                 [App\Http\Controllers\Api\UserManagementController::class, 'show']);
        Route::put('/{id}',                 [App\Http\Controllers\Api\UserManagementController::class, 'update']);
        Route::patch('/{id}/password',      [App\Http\Controllers\Api\UserManagementController::class, 'updatePassword']);
        Route::patch('/{id}/toggle-status', [App\Http\Controllers\Api\UserManagementController::class, 'toggleStatus']);
        Route::delete('/{id}',              [App\Http\Controllers\Api\UserManagementController::class, 'destroy']);
    });

    // ===============================
    // INVITACIONES
    // ===============================
    Route::get('/invitations', [ProviderInvitationController::class, 'index']);
    Route::post('/invitations', [ProviderInvitationController::class, 'store']);
    Route::post('/invitations/{invitation}/resend', [ProviderInvitationController::class, 'resend']);
    Route::delete('/invitations/{invitation}', [ProviderInvitationController::class, 'cancel']);

    // ===============================
    // ESTADO DOCUMENTAL
    // ===============================
    Route::middleware(['role:super_admin,admin,compras,calidad'])->group(function () {
        Route::get('/documents/status', [DocumentStatusController::class, 'index']);
    });

    // ===============================
    // CITAS (CALENDARIO)
    // ===============================

    // Compras / Admin
    Route::middleware(['role:super_admin,admin,compras'])->prefix('appointments')->group(function () {
        Route::get('/',                      [AppointmentController::class, 'index']);
        Route::post('/',                     [AppointmentController::class, 'store']);
        Route::get('/{appointment}',         [AppointmentController::class, 'show']);
        Route::put('/{appointment}',         [AppointmentController::class, 'update']);
        Route::post('/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    });

    // Descarga adjunto
    Route::get('/appointments/{appointment}/attachment', [AppointmentController::class, 'downloadAttachment']);

    // Seguridad
    Route::middleware(['role:super_admin,admin,seguridad'])->prefix('security')->group(function () {
        Route::get('/appointments',                     [AppointmentController::class, 'securityIndex']);
        Route::post('/appointments/{id}/confirm-entry', [AppointmentController::class, 'confirmEntry']);
        Route::post('/appointments/{id}/no-show',       [AppointmentController::class, 'markNoShow']);
    });

    // Productos para citas — Compras/Admin
    Route::middleware(['role:super_admin,admin,compras'])->group(function () {
        Route::get('/providers/{id}/appointment-products', [AppointmentController::class, 'getProviderProducts']);
    });

    // Ingeniero de Alimentos + Seguridad (solo lectura)
    Route::middleware(['role:super_admin,admin,ingeniero_alimentos,seguridad'])->prefix('food-engineer')->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'foodEngineerIndex']);
        Route::post('/appointments/{id}/reception', [AppointmentController::class, 'registerReception']);
    });

    // Config citas físicas
    Route::get('/appointments/{id}/physical-docs-config', [AppointmentController::class, 'getPhysicalDocsConfig']);

    // Unidades de medida
    Route::get('/units', [App\Http\Controllers\Api\UnitController::class, 'index']);

    // ===============================
    // CATÁLOGO DE PRODUCTOS Y SERVICIOS
    // ===============================

    // Lectura global
    Route::get('/catalog', [ProductsServicesCatalogController::class, 'index']);

    // Gestión — Compras/Admin
    Route::middleware(['role:super_admin,admin,compras'])->prefix('catalog')->group(function () {
        Route::get('/categories',      [ProductsServicesCatalogController::class, 'getCategories']);
        Route::post('/categories',     [ProductsServicesCatalogController::class, 'storeCategory']);
        Route::put('/categories/{id}', [ProductsServicesCatalogController::class, 'updateCategory']);
        Route::get('/items',           [ProductsServicesCatalogController::class, 'getItems']);
        Route::post('/items',          [ProductsServicesCatalogController::class, 'storeItem']);
        Route::put('/items/{id}',      [ProductsServicesCatalogController::class, 'updateItem']);
        Route::delete('/items/{id}',   [ProductsServicesCatalogController::class, 'destroyItem']);
        Route::post('/import',         [CatalogImportController::class, 'import']);
    });

    Route::middleware(['role:super_admin,admin,compras'])->group(function () {
        Route::put('/providers/{id}/products-services-sync',
            [ProductsServicesCatalogController::class, 'syncProviderItems']);
    });

    Route::get('/providers/{id}/products-services', [ProductsServicesCatalogController::class, 'providerItems']);


    // ── Bloqueos de calendario ────────────────────────────────────────────────
    // Lectura: compras, seguridad, ingeniero_alimentos
    Route::middleware(['role:super_admin,admin,compras,seguridad,ingeniero_alimentos'])
        ->get('/calendar-blocks', [CalendarBlockController::class, 'index']);

    // Escritura: solo compras y admin
    Route::middleware(['role:super_admin,admin,compras'])->group(function () {
        Route::post('/calendar-blocks',                [CalendarBlockController::class, 'store']);
        Route::put('/calendar-blocks/{calendarBlock}', [CalendarBlockController::class, 'update']);
        Route::delete('/calendar-blocks/{calendarBlock}', [CalendarBlockController::class, 'destroy']);
    });

    //Notificaciones
    Route::get('/notifications',            [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read',  [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all',   [NotificationController::class, 'markAllRead']);
    
    //Reportes
    Route::middleware(['role:super_admin,admin,calidad,compras,ingeniero_alimentos'])->group(function () {
    Route::get('/reports/appointments/preview',          [ReportController::class, 'appointmentsPreview']);
    Route::get('/reports/appointments/export',           [ReportController::class, 'appointmentsExport']);
    Route::get('/reports/providers-compliance/preview',  [ReportController::class, 'providersCompliancePreview']); // ✅ NUEVO
    Route::get('/reports/providers-compliance/export',   [ReportController::class, 'providersComplianceExport']);  // ✅ NUEVO
});




});