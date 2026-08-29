<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetupPasswordRequest;
use App\Models\StaffInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SetupPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        $invitation = $this->resolveValidInvitation($request, $token, 'view');

        if (! $invitation) {
            return Inertia::render('auth/InvitationInvalid');
        }

        $user = $invitation->user;

        return Inertia::render('auth/SetupPassword', [
            'token' => $token,
            'email' => $user->email,
            'submitUrl' => $request->fullUrl(),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ],
        ]);
    }

    public function store(SetupPasswordRequest $request, string $token): RedirectResponse|Response
    {
        $invitation = $this->resolveValidInvitation($request, $token, 'submit');

        if (! $invitation) {
            return Inertia::render('auth/InvitationInvalid');
        }

        $user = $invitation->user;

        $user->forceFill([
            'password' => Hash::make($request->validated()['password']),
            'is_active' => true,
            'email_verified_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        $invitation->forceFill(['used_at' => now()])->save();

        Log::info('staff setup-password succeeded', ['user_id' => $user->id, 'ip' => $request->ip()]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "مرحباً {$user->name}! تم إنشاء حسابك بنجاح.");
    }

    /**
     * Returns the matching, still-valid StaffInvitation, or null on ANY failure
     * (bad signature, unknown/re-hashed token, email mismatch, expired, used,
     * or the account was already activated). Logs every attempt; never logs the token.
     */
    protected function resolveValidInvitation(Request $request, string $token, string $stage): ?StaffInvitation
    {
        $email = (string) $request->query('email', '');
        $ip = $request->ip();

        $fail = function (string $outcome, ?int $userId = null) use ($ip, $stage): null {
            Log::warning('staff setup-password attempt failed', [
                'stage' => $stage,
                'outcome' => $outcome,
                'user_id' => $userId,
                'ip' => $ip,
            ]);

            return null;
        };

        if (! $request->hasValidSignature()) {
            return $fail('invalid_signature');
        }

        $invitation = StaffInvitation::where('token_hash', hash('sha256', $token))->first();
        if (! $invitation) {
            return $fail('unknown_token');
        }

        $user = $invitation->user;
        if (! $user || ! hash_equals($user->email, $email)) {
            return $fail('email_mismatch', $invitation->user_id);
        }
        if ($user->password !== null) {
            return $fail('already_activated', $user->id);
        }
        if ($invitation->isUsed()) {
            return $fail('used', $user->id);
        }
        if ($invitation->isExpired()) {
            return $fail('expired', $user->id);
        }

        return $invitation;
    }
}
