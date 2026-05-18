<?php

namespace App\Http\Controllers;

use App\Models\ChoreChart;
use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MagicLoginController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $magicToken = MagicLoginToken::where('token_hash', hash('sha256', $token))->firstOrFail();

        abort_unless($magicToken->isUsable(), 403);

        $user = User::firstOrCreate(
            ['email' => $magicToken->email],
            [
                'name' => str($magicToken->email)->before('@')->headline()->toString(),
                'email_verified_at' => now(),
            ],
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        if ($magicToken->chart_data) {
            ChoreChart::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'title' => ($magicToken->chart_data['children'][0]['childName'] ?? 'Chore').' Chart',
                    'data' => $magicToken->chart_data,
                ],
            );
        }

        $magicToken->forceFill(['used_at' => now()])->save();

        return redirect()->route('home')->with('status', 'You are signed in and your chart is saved.');
    }
}
