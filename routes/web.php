<?php

use App\Http\Controllers\MagicLinkController;
use App\Models\Chart;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    if ($user) {
        $chart = $user->charts()->latest()->first()
            ?? Chart::createDefault($user->id);
    } else {
        $chartId = session('chart_id');
        $chart = $chartId ? Chart::find($chartId) : null;
        if (! $chart) {
            $chart = Chart::createDefault();
            session(['chart_id' => $chart->id]);
        }
    }

    return view('chart', ['chartId' => $chart->id]);
})->name('home');

Route::get('/magic/login/{user}', [MagicLinkController::class, 'login'])
    ->middleware('signed')
    ->name('magic.login');
Route::post('/logout', [MagicLinkController::class, 'logout'])->name('logout');
