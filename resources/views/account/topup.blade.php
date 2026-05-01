@extends('layouts.app')

@section('title', 'Top Up Saldo')

@section('content')
@section('account_content')
    <div class="rounded-2xl bg-white border border-slate-200 p-6">
        <h1 class="text-xl md:text-2xl font-extrabold tracking-tight">💳 Top Up Saldo</h1>
        <p class="text-sm text-slate-600 mt-1">
            Tambah saldo akun untuk pembayaran cepat tanpa fee. Minimum
            Rp {{ number_format($min, 0, ',', '.') }} ·
            Maksimum Rp {{ number_format($max, 0, ',', '.') }}.
        </p>

        <div class="mt-3 rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-sm">
            <span class="font-semibold text-emerald-800">Saldo saat ini:</span>
            <span class="text-emerald-700">Rp {{ number_format((int) auth()->user()->balance, 0, ',', '.') }}</span>
        </div>

        <form method="POST" action="{{ route('account.topup.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Nominal Top Up</label>
                <div class="grid grid-cols-3 md:grid-cols-5 gap-2 mb-2">
                    @foreach ([10000, 25000, 50000, 100000, 250000] as $preset)
                        @if ($preset >= $min && $preset <= $max)
                            <button type="button" onclick="document.getElementById('amount').value={{ $preset }}"
                                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold hover:border-brand hover:bg-brand-soft transition">
                                Rp {{ number_format($preset, 0, ',', '.') }}
                            </button>
                        @endif
                    @endforeach
                </div>
                <input type="number" id="amount" name="amount"
                       value="{{ old('amount', $min) }}"
                       min="{{ $min }}" max="{{ $max }}"
                       required
                       class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-brand focus:outline-none focus:ring-2 ring-brand/30"
                       placeholder="Masukkan nominal (angka)">
                @error('amount')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            @include('_partials.gateway-selector', [
                'availableGateways' => array_filter($gateways, fn ($k) => $k !== 'wallet', ARRAY_FILTER_USE_KEY),
                'defaultGateway' => $defaultGateway,
                'walletEligible' => false,
                'walletBalance' => 0,
                'walletAmountRequired' => 0,
                'walletTopupEnabled' => false,
            ])

            @error('gateway')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror

            <button type="submit" class="w-full rounded-xl btn-brand font-extrabold py-3 text-base">
                Lanjutkan Pembayaran →
            </button>
            <p class="text-xs text-slate-500 text-center">Saldo akan otomatis bertambah begitu pembayaran terkonfirmasi.</p>
        </form>
    </div>
@endsection

@include('account._layout')
@endsection
