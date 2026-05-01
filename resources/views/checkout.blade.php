@extends('layouts.app')

@section('title', 'Checkout — ' . $product->name)

@section('content')
<section class="py-10 md:py-14">
    <div class="max-w-xl mx-auto px-4">
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 text-center">Checkout</h1>
        <p class="text-slate-500 mt-1 text-center text-sm">Lengkapi data kontak, lalu lanjut bayar via QRIS / VA / E-Wallet.</p>

        <div class="mt-6 bg-white rounded-2xl border border-slate-200 p-6 shadow-card">
            @php
                $fs = $variant->activeFlashsale();
                $effective = $variant->effectivePrice();
            @endphp
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 rounded-xl bg-slate-100 overflow-hidden flex items-center justify-center text-2xl">
                        @if ($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="" class="w-full h-full object-cover">
                        @else
                            🎬
                        @endif
                    </div>
                    <div>
                        <div class="text-[10px] text-brand font-bold uppercase tracking-wide">{{ $product->category?->name }}</div>
                        <div class="font-bold text-slate-800 leading-tight">{{ $product->name }}</div>
                        <div class="text-xs text-slate-500">Paket: {{ $variant->name }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] text-slate-400 uppercase">Total</div>
                    @if ($fs)
                        <div class="text-rose-600 font-extrabold text-xl">Rp {{ number_format($effective, 0, ',', '.') }}</div>
                        <div class="price-strike">Rp {{ number_format($variant->price, 0, ',', '.') }}</div>
                    @else
                        <div class="text-xl font-extrabold text-slate-900">Rp {{ number_format($effective, 0, ',', '.') }}</div>
                    @endif
                </div>
            </div>

            @if ($errors->any())
                <div class="mt-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl px-4 py-3 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @guest
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs flex items-center justify-between gap-3">
                    <span class="text-slate-600">Sudah punya akun? Login dulu biar history tersimpan otomatis.</span>
                    <a href="{{ route('login') }}" class="font-semibold text-brand whitespace-nowrap hover:underline">Masuk →</a>
                </div>
            @endguest

            <form method="POST" action="{{ route('checkout.store') }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="product_variant_id" value="{{ $variant->id }}">

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Email <span class="text-rose-500">*</span></label>
                    <input type="email" name="customer_email" required
                           value="{{ old('customer_email', auth()->user()->email ?? '') }}"
                           placeholder="kamu@email.com"
                           @auth readonly @endauth
                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-brand focus:outline-none focus:ring-2 ring-brand/30 @auth bg-slate-50 text-slate-600 @endauth">
                    <p class="text-xs text-slate-500 mt-1">Kredensial akun akan dikirim ke email ini & tampil di halaman invoice.</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Nomor WhatsApp <span class="text-slate-400 font-normal">(opsional)</span></label>
                    <input type="text" name="customer_phone"
                           value="{{ old('customer_phone', auth()->user()->phone ?? '') }}"
                           placeholder="08xxxxxxxxxx"
                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-brand focus:outline-none focus:ring-2 ring-brand/30">
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Kode Voucher <span class="text-slate-400 font-normal">(opsional)</span></label>
                    <input type="text" name="voucher_code"
                           value="{{ old('voucher_code') }}"
                           placeholder="Misal: HEMAT10"
                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm uppercase focus:border-brand focus:outline-none focus:ring-2 ring-brand/30">
                    <p class="text-xs text-slate-500 mt-1">Punya kode promo? Masukkan untuk dapat diskon.</p>
                </div>

                @php
                    $availableGateways = collect($gateways ?? [])->filter(fn ($g) => ! empty($g['enabled']) && ! empty($g['configured']));
                    $effectiveDefault = old('gateway', $defaultGateway ?? $availableGateways->keys()->first());
                @endphp
                @if ($availableGateways->isNotEmpty())
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Metode Pembayaran</label>
                        <div class="grid grid-cols-1 gap-2">
                            @if ($availableGateways->has('pakasir'))
                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 cursor-pointer">
                                    <input type="radio" name="gateway" value="pakasir" @checked($effectiveDefault === 'pakasir') class="text-brand">
                                    <span class="text-sm"><b>Pakasir</b> — QRIS / VA / E-Wallet</span>
                                </label>
                            @endif
                            @if ($availableGateways->has('eqris'))
                                @php $eqrisMethods = $availableGateways['eqris']['methods'] ?? []; @endphp
                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 cursor-pointer">
                                    <input type="radio" name="gateway" value="eqris" @checked($effectiveDefault === 'eqris') class="text-brand">
                                    <span class="text-sm"><b>Eqris</b> — QRIS Multi-Bank</span>
                                </label>
                                @if (count($eqrisMethods) > 1)
                                    <div class="ml-7 grid grid-cols-2 gap-2">
                                        @foreach ($eqrisMethods as $m)
                                            <label class="flex items-center gap-2 text-xs">
                                                <input type="radio" name="eqris_method" value="{{ $m }}" @checked(old('eqris_method', $eqrisMethods[0] ?? null) === $m)>
                                                {{ strtoupper($m) }}
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif (count($eqrisMethods) === 1)
                                    <input type="hidden" name="eqris_method" value="{{ $eqrisMethods[0] }}">
                                @endif
                            @endif
                            @auth
                                @if ($walletEligible ?? false)
                                    <label class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 cursor-pointer">
                                        <input type="radio" name="gateway" value="wallet" @checked(old('gateway') === 'wallet') class="text-emerald-600">
                                        <span class="text-sm"><b>Saldo Akun</b> — bebas fee (saldo: Rp {{ number_format((int) auth()->user()->balance, 0, ',', '.') }})</span>
                                    </label>
                                @endif
                            @endauth
                        </div>
                    </div>
                @endif

                <button type="submit" class="w-full rounded-xl btn-brand font-extrabold text-base px-4 py-3 transition">
                    Bayar Sekarang
                </button>
                <p class="text-xs text-slate-500 text-center">Kamu akan diarahkan ke halaman pembayaran yang aman.</p>
            </form>
        </div>

        <div class="mt-6 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-xl bg-white border border-slate-200 p-3 text-xs">
                <div class="text-lg">⚡</div><div class="font-semibold mt-1">Instant Delivery</div>
            </div>
            <div class="rounded-xl bg-white border border-slate-200 p-3 text-xs">
                <div class="text-lg">🛡️</div><div class="font-semibold mt-1">Garansi Penuh</div>
            </div>
            <div class="rounded-xl bg-white border border-slate-200 p-3 text-xs">
                <div class="text-lg">🔒</div><div class="font-semibold mt-1">Pembayaran Aman</div>
            </div>
        </div>
    </div>
</section>
@endsection
