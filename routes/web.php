<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\GoogleDriveController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/oauth2callback', function (Request $request) {
    return $request->all(); // Temporary for debugging
});

Route::get('/drive-csv-files', [GoogleDriveController::class, 'readCSVFromDrive']);
Route::get('/store-csv/{fileId}', [GoogleDriveController::class, 'storeCSVInDatabase']);