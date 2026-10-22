<?php

use App\Http\Controllers\DocumentImportController;
use App\Http\Controllers\DocumentPdfController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::post('/documents/pdf', DocumentPdfController::class)
    ->middleware('throttle:pdf')
    ->name('documents.pdf');

Route::post('/documents/import', DocumentImportController::class)
    ->middleware('throttle:30,1')
    ->name('documents.import');
