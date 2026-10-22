<?php

use App\Http\Controllers\DocumentPdfController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::post('/documents/pdf', DocumentPdfController::class)
    ->middleware('throttle:pdf')
    ->name('documents.pdf');
