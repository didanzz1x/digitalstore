@extends('layouts.app')

@section('title', 'Keranjang')

@section('content')
@php
    $subtotal = 0;
    foreach ($items as $i) {
        $subtotal += ($i->variant?->effectivePrice() ?? 0) * max(1, (int) $i->quantity);
    }
@endphp
<section class="py-10 md:py-14">
    <div class="max-w-3xl mx-auto px-4">
        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Keranjang Belanja</h1>
        <p class="text-sm text-slate-500 mt-1">Bayar semua sekaligus dalam 1 transaksi.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 p-3 text-sm">
                @foreach ($errors->all() as $err)<div>• {{ $err }}</div>@endforeach
            </div>
        @endif

        @if ($items->isEmpty())
            <div class="mt-6 rounded-2xl bg-white border border-slate-200 p-10 text-center">
                <p class="text-3xl mb-2">🛒</p>
                <p class="text-slate-600">Keranjang kamu masih kosong.</p>
                <a href="{{ route('home') }}" class="inline-block mt-4 text-brand font-semibold hover:underline">Mulai belanja →</a>
            </div>
        @else
            <div class="mt-6 space-y-4">
                @foreach ($items as $item)
                    @php
                        $eff = $item->variant?->effectivePrice() ?? 0;
                        $line = $eff * max(1, (int) $item->quantity);
                    @endphp
                    <div class="rounded-2xl bg-white border border-slate-200 p-4 md:p-5 flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex-1">
                            <div class="font-bold text-slate-900">{{ $item->product?->name ?? '—' }}</div>
                            <div class="text-xs text-slate-500">Paket: {{ $item->variant?->name ?? '—' }}</div>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @if ($item->variant?->warranty_days)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5 text-[10px] font-bold">🛡 Garansi {{ $item->variant->warranty_days }} Hari</span>
                                @endif
                                @if ($item->variant?->shareTypeLabel())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 text-violet-700 px-2 py-0.5 text-[10px] font-bold">{{ $item->variant->shareTypeLabel() }}</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-500 mt-1">Rp {{ number_format($eff, 0, ',', '.') }} / unit</div>
                        </div>
                        <form method="POST" action="{{ route('cart.update', $item) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <label class="text-xs text-slate-500">Qty</label>
                            <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="10"
                                   class="w-16 rounded-lg border border-slate-200 px-2 py-1 text-sm">
                            <button class="text-xs font-semibold text-brand hover:underline">Update</button>
                        </form>
                        <div class="text-right min-w-[140px]">
                            <div class="font-extrabold text-slate-900">Rp {{ number_format($line, 0, ',', '.') }}</div>
                            <form method="POST" action="{{ route('cart.destroy', $item) }}" class="mt-2">
                                @csrf @method('DELETE')
                                <button class="text-xs text-rose-600 hover:underline">Hapus</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('cart.checkout') }}" class="mt-6 rounded-2xl bg-white border border-slate-200 p-5 md:p-6 space-y-4">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-slate-700">Email</label>
                        <input type="email" name="customer_email" required
                               value="{{ old('customer_email', auth()->user()?->email) }}"
                               class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-700">Nomor HP / WhatsApp</label>
                        <input type="text" name="customer_phone"
                               value="{{ old('customer_phone', auth()->user()?->phone) }}"
                               placeholder="08xxxxxxxxxx"
                               class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-slate-700">Kode Voucher (opsional)</label>
                    <input type="text" name="voucher_code" value="{{ old('voucher_code') }}"
                           placeholder="Misal: HEMAT10"
                           class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm uppercase">
                </div>

                @include('_partials.gateway-selector', [
                    'availableGateways' => $gateways ?? [],
                    'defaultGateway' => $defaultGateway ?? null,
                    'walletEligible' => $walletEligible ?? false,
                    'walletBalance' => (int) (auth()->user()->balance ?? 0),
                    'walletAmountRequired' => (int) $subtotal,
                    'walletTopupEnabled' => (bool) ($site->wallet_topup_enabled ?? true),
                ])

                <div class="border-t border-slate-100 pt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div class="text-xs text-slate-500">Total ({{ $items->count() }} item)</div>
                        <div class="text-2xl font-extrabold text-slate-900">Rp {{ number_format($subtotal, 0, ',', '.') }}</div>
                        <div class="text-[11px] text-slate-500">Diskon voucher (jika ada) akan dihitung saat klik Bayar Semua.</div>
                    </div>
                    <button type="submit"
                            class="btn-brand rounded-xl px-6 py-3 text-sm font-extrabold tracking-tight">
                        Bayar Semua →
                    </button>
                </div>
            </form>

            <p class="mt-3 text-[11px] text-slate-500 text-center">
                Pembayaran 1x untuk semua item. Setelah lunas, akun untuk tiap item dikirim otomatis (manual untuk varian tertentu) ke email & WhatsApp.
            </p>
        @endif
    </div>
</section>
@endsection
