@extends('layouts.app')

@section('title', $label.' — Membership')

@section('content')
<section class="py-10 md:py-14">
    <div class="max-w-2xl mx-auto px-4">
        <div class="text-center">
            <span class="inline-block px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-bold uppercase tracking-wider">Premium</span>
            <h1 class="mt-3 text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900">{{ $label }}</h1>
            <p class="mt-2 text-slate-500">
                Rp <span class="font-bold text-slate-900">{{ number_format($price, 0, ',', '.') }}</span>
                <span class="text-sm">/ {{ $durationDays }} hari</span>
            </p>
        </div>

        <div class="mt-8 bg-white rounded-2xl border border-slate-200 p-6 shadow-card">
            @if (session('success'))
                <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm mb-4">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($site->membership_benefits_html))
                <div class="prose prose-sm max-w-none">
                    {!! $site->membership_benefits_html !!}
                </div>
            @else
                <ul class="space-y-2 text-sm text-slate-700">
                    <li>✅ <b>Checkout Bebas Fee</b> — bayar pakai saldo akun, tanpa biaya admin.</li>
                    <li>✅ <b>Prioritas Support</b> — respon lebih cepat di WhatsApp & Telegram.</li>
                    <li>✅ <b>Promo Eksklusif</b> — voucher khusus member.</li>
                </ul>
            @endif

            @auth
                @php $u = auth()->user(); @endphp
                @if ($u->is_member && $u->member_expires_at && $u->member_expires_at->isFuture())
                    <div class="mt-6 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                        Status kamu sudah <b>Member Aktif</b> sampai {{ $u->member_expires_at->format('d M Y H:i') }}.
                        Pembayaran berikutnya akan memperpanjang masa aktif.
                    </div>
                @endif

                <form method="POST" action="{{ route('membership.subscribe') }}" class="mt-6 space-y-4">
                    @csrf
                    @php
                        $availableGateways = collect($gateways ?? [])->filter(fn ($g) => ! empty($g['enabled']) && ! empty($g['configured']));
                    @endphp
                    @if ($availableGateways->isNotEmpty())
                        <div class="grid grid-cols-1 gap-2">
                            @if ($availableGateways->has('pakasir'))
                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 cursor-pointer">
                                    <input type="radio" name="gateway" value="pakasir" @checked(($defaultGateway ?? 'pakasir') === 'pakasir')>
                                    <span class="text-sm"><b>Pakasir</b> — QRIS / VA / E-Wallet</span>
                                </label>
                            @endif
                            @if ($availableGateways->has('eqris'))
                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 cursor-pointer">
                                    <input type="radio" name="gateway" value="eqris" @checked(($defaultGateway ?? '') === 'eqris')>
                                    <span class="text-sm"><b>Eqris</b> — QRIS Multi-Bank</span>
                                </label>
                            @endif
                            @if ((int) $u->balance >= $price)
                                <label class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 cursor-pointer">
                                    <input type="radio" name="gateway" value="wallet">
                                    <span class="text-sm"><b>Saldo Akun</b> (saldo Rp {{ number_format((int) $u->balance, 0, ',', '.') }})</span>
                                </label>
                            @endif
                        </div>
                    @endif

                    <button type="submit" class="w-full rounded-xl btn-brand font-extrabold py-3 text-base">
                        Berlangganan Sekarang
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="mt-6 inline-block rounded-xl btn-brand font-extrabold py-3 px-6 text-base">
                    Login dulu untuk Berlangganan
                </a>
            @endauth
        </div>
    </div>
</section>
@endsection
