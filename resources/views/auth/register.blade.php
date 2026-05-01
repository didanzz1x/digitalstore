@extends('layouts.app')

@section('title', 'Daftar Akun')

@section('content')
<section class="py-10 md:py-14">
    <div class="max-w-md mx-auto px-4">
        <div class="rounded-2xl bg-white border border-slate-200 shadow-card p-6 md:p-8">
            <h1 class="text-2xl font-extrabold tracking-tight">Daftar Akun</h1>
            @if (! empty($paywallEnabled))
                <p class="text-sm text-slate-500 mt-1">{{ $paywallDescription }}</p>
                <div class="mt-3 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm">
                    <span class="font-semibold text-amber-900">{{ $paywallLabel }}:</span>
                    <span class="text-amber-800 font-extrabold">Rp {{ number_format($paywallPrice, 0, ',', '.') }}</span>
                    <p class="text-xs text-amber-700 mt-1">Akun akan otomatis aktif begitu pembayaran terkonfirmasi.</p>
                </div>
            @else
                <p class="text-sm text-slate-500 mt-1">Gratis & cepat. History pesanan kamu langsung tersimpan.</p>
            @endif

            @php $refCookie = request()->cookie(\App\Services\AffiliateService::COOKIE_NAME) ?: request()->query('ref'); @endphp
            @if (! empty($refCookie))
                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                    Daftar via referral kode: <b>{{ $refCookie }}</b>
                </div>
            @endif

            <form method="POST" action="{{ route('register.attempt') }}" class="mt-6 space-y-4">
                @csrf
                @if (! empty($refCookie))
                    <input type="hidden" name="ref" value="{{ $refCookie }}">
                @endif
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" required autofocus value="{{ old('name') }}"
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand">
                    @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="email">Email</label>
                    <input type="email" id="email" name="email" required value="{{ old('email') }}"
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand"
                           placeholder="kamu@email.com">
                    <p class="mt-1 text-[11px] text-slate-400">Pakai email yang sama dengan order sebelumnya supaya history otomatis tergabung.</p>
                    @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="phone">Nomor HP / WhatsApp <span class="text-rose-500">*</span></label>
                    <input type="text" id="phone" name="phone" required value="{{ old('phone') }}"
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand"
                           placeholder="08xxxxxxxxxx">
                    @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand">
                    <p class="mt-1 text-[11px] text-slate-400">Minimal 8 karakter.</p>
                    @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="password_confirmation">Konfirmasi Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand">
                </div>
                @if (! empty($paywallEnabled))
                    <div class="border-t border-slate-100 pt-4">
                        @include('_partials.gateway-selector', [
                            'availableGateways' => array_filter($gateways ?? [], fn ($k) => $k !== 'wallet', ARRAY_FILTER_USE_KEY),
                            'defaultGateway' => $defaultGateway ?? null,
                            'walletEligible' => false,
                            'walletBalance' => 0,
                            'walletAmountRequired' => 0,
                            'walletTopupEnabled' => false,
                        ])
                        @error('gateway')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                <button type="submit" class="w-full rounded-xl btn-brand font-extrabold py-3 text-base">
                    @if (! empty($paywallEnabled))
                        Daftar & Bayar Rp {{ number_format($paywallPrice, 0, ',', '.') }}
                    @else
                        Daftar Sekarang
                    @endif
                </button>
            </form>

            <p class="mt-5 text-center text-sm text-slate-500">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="text-brand font-semibold hover:underline">Masuk di sini</a>
            </p>
        </div>
    </div>
</section>
@endsection
