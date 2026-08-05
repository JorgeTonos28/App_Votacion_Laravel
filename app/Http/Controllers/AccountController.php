<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\QrCodeService;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function __construct(private readonly TotpService $totp, private readonly QrCodeService $qr) {}

    public function login(Request $request)
    {
        return view('account.login', ['returnUrl' => $request->query('returnUrl')]);
    }

    public function authenticate(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'remember_me' => 'nullable|boolean', 'return_url' => 'nullable|string']);
        $user = User::query()->whereRaw('LOWER(email)=?', [strtolower($data['email'])])->first();
        if ($user?->locked_until?->isFuture()) {
            return back()->withInput($request->except('password'))->withErrors(['Acceso bloqueado temporalmente por intentos fallidos.']);
        }
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            if ($user) {
                $attempts = $user->failed_attempts + 1;
                $user->update(['failed_attempts' => $attempts, 'locked_until' => $attempts >= 5 ? now()->addMinutes(15) : null]);
            }

            return back()->withInput($request->except('password'))->withErrors(['Correo o contraseña incorrectos.']);
        }
        $user->update(['failed_attempts' => 0, 'locked_until' => null]);
        $remember = (bool) ($data['remember_me'] ?? false);
        $return = $this->safeReturn($data['return_url'] ?? null);
        $trusted = $request->cookie('innovamente_2fa_trusted');
        if ($user->two_factor_enabled && ! $this->trusted($user, $trusted)) {
            $request->session()->put('pending_2fa', ['userId' => $user->id, 'remember' => $remember, 'return' => $return, 'expires' => now()->addMinutes(5)->timestamp]);

            return redirect()->route('admin.verify');
        }
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->to($return);
    }

    public function verify(Request $request)
    {
        if (! $request->session()->has('pending_2fa')) {
            return redirect()->route('admin.login');
        }

return view('account.verify-two-factor');
    }

    public function verifyCode(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|min:6|max:8', 'remember_device' => 'nullable|boolean']);
        $pending = $request->session()->get('pending_2fa');
        if (! $pending || $pending['expires'] < time()) {
            return redirect()->route('admin.login')->withErrors(['La verificación expiró. Inicia sesión nuevamente.']);
        }
        $user = User::query()->find($pending['userId']);
        if (! $user || ! $this->totp->verify((string) $user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['El código de verificación no es válido.']);
        }
        Auth::login($user, (bool) $pending['remember']);
        $request->session()->forget('pending_2fa');
        $request->session()->regenerate();
        $response = redirect()->to($pending['return']);
        if ($request->boolean('remember_device')) {
            $response->cookie('innovamente_2fa_trusted', $this->trustToken($user), 43200, null, null, $request->isSecure(), true, false, 'Lax');
        }

        return $response;
    }

    public function mfa(Request $request)
    {
        $user = $request->user();
        if (! $user->two_factor_secret) {
            $user->update(['two_factor_secret' => $this->totp->secret()]);
        }
        $uri = $this->totpUri($user);
        $qrDataUri = 'data:image/png;base64,'.base64_encode($this->qr->png($uri, 300));
        $sharedKey = trim(chunk_split(strtolower($user->two_factor_secret), 4, ' '));
        $recoveryCodes = session('recoveryCodes', []);

        return view('account.mfa', compact('user', 'uri', 'qrDataUri', 'sharedKey', 'recoveryCodes'));
    }

    public function enableMfa(Request $request)
    {
        $data = $request->validate(['verification_code' => 'required|string|min:6|max:8']);
        $user = $request->user();
        if (! $this->totp->verify((string) $user->two_factor_secret, $data['verification_code'])) {
            return back()->withErrors(['verification_code' => 'El código no coincide. Verifica la hora del dispositivo.']);
        }
        $codes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))->all();
        $user->update(['two_factor_enabled' => true, 'two_factor_recovery_codes' => $codes]);

        return redirect()->route('admin.mfa')->with('success', 'Segundo factor activado.')->with('recoveryCodes', $codes);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function denied()
    {
        return view('account.access-denied');
    }

    private function safeReturn(?string $url): string
    {
        return $url && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : '/admin';
    }

    private function trustToken(User $user): string
    {
        return hash_hmac('sha256', $user->id.'|'.$user->two_factor_secret, config('app.key'));
    }

    private function trusted(User $user, ?string $token): bool
    {
        return $token && hash_equals($this->trustToken($user), $token);
    }

    private function totpUri(User $user): string
    {
        return 'otpauth://totp/'.rawurlencode('InnovaMente').':'.rawurlencode($user->email).'?secret='.$user->two_factor_secret.'&issuer='.rawurlencode('InnovaMente').'&digits=6';
    }
}
