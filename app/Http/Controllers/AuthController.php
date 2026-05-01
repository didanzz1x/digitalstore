<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AffiliateService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Halaman form login */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('account.index');
        }

        return view('auth.login');
    }

    /** POST /login */
    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt(
            ['email' => $data['email'], 'password' => $data['password']],
            (bool) ($data['remember'] ?? false)
        )) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email atau password salah.']);
        }

        // Cek banned: kalau user di-ban, langsung logout + tampilkan alasan.
        if (Auth::user()->is_banned) {
            $reason = Auth::user()->ban_reason ?: 'Akun dinonaktifkan oleh admin.';
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Akun di-banned: '.$reason]);
        }

        $request->session()->regenerate();

        // Update last_login_at (untuk monitoring di User Management).
        Auth::user()->forceFill(['last_login_at' => now()])->save();

        // Pastikan history order guest dengan email yang sama tergabung.
        $linked = Auth::user()->linkGuestOrders();
        if ($linked > 0) {
            Audit::log('user.linked_guest_orders', Auth::user(), ['count' => $linked]);
        }

        return redirect()->intended(route('account.index'))
            ->with('success', 'Selamat datang kembali, '.Auth::user()->name.'!');
    }

    /** Halaman form register */
    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('account.index');
        }

        return view('auth.register');
    }

    /** POST /register */
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\- ]+$/'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'ref' => ['nullable', 'string', 'max:32'],
        ]);

        // Resolve referrer dari (1) form 'ref' field kalau ada, (2) cookie/query yang sudah disimpan
        // saat user klik link referral sebelum daftar.
        $referrer = null;
        if (AffiliateService::isEnabled()) {
            if (! empty($data['ref'])) {
                $referrer = User::where('referral_code', strtoupper(trim($data['ref'])))->first();
            }
            $referrer ??= AffiliateService::resolveReferrerFromRequest($request, null);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'referred_by_id' => $referrer?->id,
        ]);

        // Generate referral code unik untuk user baru — langsung tersedia di dashboard.
        $user->ensureReferralCode();

        if ($referrer) {
            Audit::log('user.referred', $user, [
                'referrer_id' => $referrer->id,
                'referrer_email' => $referrer->email,
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $linked = $user->linkGuestOrders();
        if ($linked > 0) {
            Audit::log('user.linked_guest_orders', $user, ['count' => $linked]);

            return redirect()->route('account.orders.index')
                ->with('success', "Akun berhasil dibuat. Kami menemukan {$linked} pesanan kamu sebelumnya — semua sudah masuk ke history.");
        }

        return redirect()->route('account.index')
            ->with('success', 'Akun berhasil dibuat. Selamat berbelanja!');
    }

    /** POST /logout */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Berhasil keluar.');
    }

    /** Halaman lupa password */
    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    /** POST /lupa-password */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // Selalu balas dengan pesan generic supaya tidak bocorin email mana
        // yang terdaftar (user enumeration).
        Password::sendResetLink($data);

        return back()->with('success', 'Kalau email kamu terdaftar, link reset sudah dikirim. Cek inbox/spam.');
    }

    /** Halaman form reset password */
    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /** POST /reset-password */
    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password berhasil diubah, silakan login.');
        }

        return back()->withErrors(['email' => trans($status)]);
    }
}
