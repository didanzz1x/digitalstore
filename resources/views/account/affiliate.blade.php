@extends('layouts.app')

@section('title', 'Program Affiliate')

@section('content')
@section('account_content')
    <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Program Affiliate</h1>
    <p class="text-sm text-slate-500">Dapatkan komisi {{ rtrim(rtrim(number_format($percent, 2), '0'), '.') }}% dari setiap order PAID hasil link referral kamu.</p>

    @if (session('success'))
        <div class="mt-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
            <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl bg-white border border-slate-200 p-5">
            <div class="text-xs font-bold uppercase text-slate-400">Saldo Komisi</div>
            <div class="text-2xl font-extrabold text-emerald-600 mt-1">Rp {{ number_format((int) $user->affiliate_balance, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Bisa ditarik kapan saja.</div>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-5">
            <div class="text-xs font-bold uppercase text-slate-400">Total Earned</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">Rp {{ number_format($totalEarned, 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Akumulasi komisi yang pernah didapat.</div>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-5">
            <div class="text-xs font-bold uppercase text-slate-400">Total Referral</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $referrals->count() }}</div>
            <div class="text-[11px] text-slate-500 mt-1">User yang daftar via link kamu.</div>
        </div>
    </div>

    <div class="mt-5 rounded-2xl bg-white border border-slate-200 p-5">
        <div class="text-xs font-bold uppercase text-slate-400">Link Referral Kamu</div>
        <div class="mt-2 flex items-center gap-2">
            <input type="text" readonly value="{{ $referralLink }}" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono bg-slate-50">
            <button type="button" onclick="navigator.clipboard.writeText('{{ $referralLink }}'); this.textContent='Tersalin'" class="rounded-lg btn-brand px-3 py-2 text-xs font-bold">Salin</button>
        </div>
        <p class="mt-2 text-[11px] text-slate-500">Kode kamu: <b>{{ $user->referral_code }}</b> · cookie tracking: {{ $site->affiliate_cookie_days ?? 30 }} hari.</p>
    </div>

    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-2xl bg-white border border-slate-200 p-5">
            <div class="font-bold text-slate-800 mb-3">Transfer ke Saldo Utama</div>
            <form method="POST" action="{{ route('account.affiliate.transfer') }}" class="space-y-3">
                @csrf
                <input type="number" name="amount" min="1" max="{{ (int) $user->affiliate_balance }}" placeholder="Jumlah (Rp)" required
                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <button type="submit" class="w-full rounded-lg btn-brand font-bold py-2 text-sm">Transfer ke Saldo</button>
                <p class="text-[11px] text-slate-500">Saldo komisi pindah ke saldo akun utama. Bisa langsung dipakai untuk belanja.</p>
            </form>
        </div>
        @if ($bankWithdrawEnabled)
            <div class="rounded-2xl bg-white border border-slate-200 p-5">
                <div class="font-bold text-slate-800 mb-3">Withdraw ke Bank</div>
                <form method="POST" action="{{ route('account.affiliate.withdraw') }}" class="space-y-3">
                    @csrf
                    <input type="number" name="amount" min="{{ $minWithdraw ?: 1 }}" max="{{ (int) $user->affiliate_balance }}" placeholder="Jumlah (Rp). Min Rp {{ number_format($minWithdraw, 0, ',', '.') }}" required
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <input type="text" name="bank_name" placeholder="Nama Bank (BCA/Mandiri/dll)" required
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <input type="text" name="bank_account_no" placeholder="Nomor Rekening" required
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <input type="text" name="bank_account_name" placeholder="Nama Pemilik Rekening" required
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <button type="submit" class="w-full rounded-lg btn-brand font-bold py-2 text-sm">Request Withdraw</button>
                    <p class="text-[11px] text-slate-500">Diproses admin manual dalam 1×24 jam. Saldo dipotong saat request.</p>
                </form>
            </div>
        @endif
    </div>

    <div class="mt-5 rounded-2xl bg-white border border-slate-200 p-5">
        <div class="font-bold text-slate-800 mb-3">Riwayat Komisi (50 terakhir)</div>
        @if ($commissions->isEmpty())
            <p class="text-sm text-slate-500">Belum ada komisi. Bagikan link kamu untuk mulai dapat komisi.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500 border-b">
                        <tr><th class="text-left py-2">Tanggal</th><th class="text-left">Order</th><th class="text-left">Referee</th><th class="text-right">Komisi</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($commissions as $c)
                            <tr class="border-b last:border-0">
                                <td class="py-2">{{ $c->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $c->order?->order_code ?? '—' }}</td>
                                <td>{{ $c->referee?->email ?? '—' }}</td>
                                <td class="text-right font-bold text-emerald-600">Rp {{ number_format((int) $c->amount, 0, ',', '.') }}</td>
                                <td class="text-center"><span class="text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-700">{{ $c->status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-5 rounded-2xl bg-white border border-slate-200 p-5">
        <div class="font-bold text-slate-800 mb-3">Riwayat Withdraw (50 terakhir)</div>
        @if ($withdrawals->isEmpty())
            <p class="text-sm text-slate-500">Belum ada withdraw.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-500 border-b">
                        <tr><th class="text-left py-2">Tanggal</th><th class="text-left">Metode</th><th class="text-left">Bank</th><th class="text-right">Jumlah</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($withdrawals as $w)
                            <tr class="border-b last:border-0">
                                <td class="py-2">{{ $w->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $w->method }}</td>
                                <td>{{ $w->bank_name ?: '—' }}</td>
                                <td class="text-right font-bold">Rp {{ number_format((int) $w->amount, 0, ',', '.') }}</td>
                                <td class="text-center"><span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $w->status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@include('account._layout')
@endsection
