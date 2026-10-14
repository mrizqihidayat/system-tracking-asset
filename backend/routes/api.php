<?php

use App\Http\Controllers\AssetTypeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // Master & lookup (SPEC §14)
    Route::apiResource('departments', DepartmentController::class)->only(['index', 'store']);
    Route::apiResource('buildings', BuildingController::class)->only(['index', 'store']);
    Route::get('/buildings/{building}/rooms', [RoomController::class, 'indexForBuilding']);
    Route::post('/buildings/{building}/rooms', [RoomController::class, 'store']);
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'store']);
    Route::apiResource('asset-types', AssetTypeController::class)->only(['index', 'store', 'update']);
    Route::get('/lookups/borrower-units', [LookupController::class, 'borrowerUnits']);
});
