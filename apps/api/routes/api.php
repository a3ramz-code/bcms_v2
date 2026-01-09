<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SubscriptionNetworkStatusController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1')->middleware(['auth:sanctum'])->group(function () {
    // Subscription Network Status - requires subscriptions.read permission
    Route::get('/subscriptions/{id}/network-status', [SubscriptionNetworkStatusController::class, 'show'])
        ->middleware('permission:subscriptions.read');
});
