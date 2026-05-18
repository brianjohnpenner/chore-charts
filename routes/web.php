<?php

use App\Models\ChoreChart;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/privacy', 'privacy')->name('privacy');

Route::get('/c/{chart}', function (ChoreChart $chart) {
    return view('home', ['chart' => $chart]);
})->middleware('signed')->name('chart.show');
