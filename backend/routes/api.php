<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetEventController;
use App\Http\Controllers\AssetFileController;
use App\Http\Controllers\AssetLabelController;
use App\Http\Controllers\AssetScanController;
use App\Http\Controllers\AssetTypeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConsumableController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\PublicAssetController;
use App\Http\Controllers\RequestCatalogController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomReadinessController;
use App\Http\Controllers\RoomStandardController;
use App\Http\Controllers\SupplyRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Halaman publik QR: tanpa auth, read-only, rate limit per IP (SPEC §6)
Route::get('/public/assets/{token}', [PublicAssetController::class, 'show'])->middleware('throttle:public-assets');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::get('/dashboard', DashboardController::class);

    // Master & lookup (SPEC §14)
    Route::apiResource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('buildings', BuildingController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/buildings/{building}/rooms', [RoomController::class, 'indexForBuilding']);
    Route::post('/buildings/{building}/rooms', [RoomController::class, 'store']);
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->whereNumber('room');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->whereNumber('room');
    Route::apiResource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('asset-types', AssetTypeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/lookups/borrower-units', [LookupController::class, 'borrowerUnits']);
    Route::get('/lookups/request-methods', [LookupController::class, 'requestMethods']);

    // Aset (SPEC §5, §14). Hapus = permanen lewat AssetService (cek delete-impact dulu).
    Route::get('/assets', [AssetController::class, 'index']);
    Route::post('/assets', [AssetController::class, 'store']);
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->whereNumber('asset');
    Route::get('/assets/{asset}/delete-impact', [AssetController::class, 'deleteImpact'])->whereNumber('asset');
    Route::get('/assets/{asset}/code-preview', [AssetController::class, 'codePreview'])->whereNumber('asset');
    Route::put('/assets/{asset}', [AssetController::class, 'update'])->whereNumber('asset');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->whereNumber('asset');
    Route::get('/assets/{asset}/events', [AssetEventController::class, 'index'])->whereNumber('asset');
    Route::post('/assets/{asset}/report', [AssetController::class, 'report'])->whereNumber('asset');
    Route::post('/assets/{asset}/found', [AssetController::class, 'markFound'])->whereNumber('asset');
    Route::post('/assets/{asset}/services', [AssetController::class, 'storeService'])->whereNumber('asset');
    Route::patch('/assets/{asset}/location', [AssetController::class, 'moveLocation'])->whereNumber('asset');

    // Foto & dokumen aset (SPEC §5): slot per jenis (kecuali Lainnya), stream lewat backend
    Route::get('/assets/{asset}/files', [AssetFileController::class, 'index'])->whereNumber('asset');
    Route::post('/assets/{asset}/files', [AssetFileController::class, 'store'])->whereNumber('asset');
    Route::delete('/asset-files/{assetFile}', [AssetFileController::class, 'destroy'])->whereNumber('assetFile');
    Route::get('/asset-files/{assetFile}/download', [AssetFileController::class, 'download'])->whereNumber('assetFile')->name('asset-files.download');

    // Scan & label QR (SPEC §6)
    Route::get('/assets/scan/{value}', [AssetScanController::class, 'show']);
    Route::get('/assets/{asset}/label', [AssetLabelController::class, 'show'])->whereNumber('asset');
    Route::post('/assets/labels', [AssetLabelController::class, 'store']);

    // Peminjaman (SPEC §7)
    Route::get('/loans', [LoanController::class, 'index']);
    Route::post('/loans', [LoanController::class, 'store']);
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->whereNumber('loan');
    Route::patch('/loans/{loan}/return', [LoanController::class, 'giveBack'])->whereNumber('loan');
    Route::patch('/loans/{loan}/cancel', [LoanController::class, 'cancel'])->whereNumber('loan');
    Route::patch('/loans/{loan}/confirm-damage', [LoanController::class, 'confirmDamage'])->whereNumber('loan');

    // Kelengkapan ruangan (SPEC §8); standar diisi manual per ruangan (tanpa salin)
    Route::get('/room-readiness', RoomReadinessController::class);
    Route::get('/rooms/{room}/checklist', [RoomStandardController::class, 'checklist'])->whereNumber('room');
    Route::post('/rooms/{room}/standards', [RoomStandardController::class, 'store'])->whereNumber('room');
    Route::post('/rooms/{room}/check', [RoomStandardController::class, 'check'])->whereNumber('room');
    Route::put('/room-standards/{roomStandard}', [RoomStandardController::class, 'update'])->whereNumber('roomStandard');
    Route::delete('/room-standards/{roomStandard}', [RoomStandardController::class, 'destroy'])->whereNumber('roomStandard');
    Route::post('/room-standards/{roomStandard}/fill-from-stock', [RoomStandardController::class, 'fillFromStock'])->whereNumber('roomStandard');

    // Permintaan ke unit lain (SPEC §9); tanpa hapus permintaan (batal lewat status)
    Route::apiResource('request-catalog', RequestCatalogController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['request-catalog' => 'requestCatalog'])
        ->whereNumber('requestCatalog');
    Route::get('/supply-requests/suggestions', [SupplyRequestController::class, 'suggestions']);
    Route::get('/supply-requests', [SupplyRequestController::class, 'index']);
    Route::post('/supply-requests', [SupplyRequestController::class, 'store']);
    Route::put('/supply-requests/{supplyRequest}', [SupplyRequestController::class, 'update'])->whereNumber('supplyRequest');
    Route::patch('/supply-requests/{supplyRequest}/status', [SupplyRequestController::class, 'updateStatus'])->whereNumber('supplyRequest');

    // Stok habis pakai (SPEC §10)
    Route::get('/consumables', [ConsumableController::class, 'index']);
    Route::post('/consumables', [ConsumableController::class, 'store']);
    Route::get('/consumables/{consumable}', [ConsumableController::class, 'show'])->whereNumber('consumable');
    Route::get('/consumables/{consumable}/usage', [ConsumableController::class, 'usage'])->whereNumber('consumable');
    Route::put('/consumables/{consumable}', [ConsumableController::class, 'update'])->whereNumber('consumable');
    Route::patch('/consumables/{consumable}/quantity', [ConsumableController::class, 'updateQuantity'])->whereNumber('consumable');
    Route::delete('/consumables/{consumable}', [ConsumableController::class, 'destroy'])->whereNumber('consumable');
});
