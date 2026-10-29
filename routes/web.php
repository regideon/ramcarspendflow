<?php

use App\Http\Controllers\Rfp\ReceiptPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/rfp/receipt-preview', ReceiptPreviewController::class)
    ->middleware('signed')
    ->name('rfp.receipt-preview');
