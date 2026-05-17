<?php

namespace App\Http\Controllers;

use App\Models\Chart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MagicLinkController extends Controller
{
    public function login(Request $request, User $user): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            throw ValidationException::withMessages([
                'magic' => 'This sign-in link is invalid or has expired.',
            ]);
        }

        Auth::login($user, true);

        $sessionChartId = $request->session()->pull('chart_id');
        if ($sessionChartId) {
            $chart = Chart::find($sessionChartId);
            if ($chart && $chart->user_id === null) {
                $chart->user_id = $user->id;
                $chart->save();
            }
        }

        return redirect()->route('home')->with('status', 'You are signed in.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
