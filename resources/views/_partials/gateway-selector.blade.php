{{--
    Reusable gateway selection block.
    Required props (passed via @include with array):
      - $availableGateways    : output of PaymentGatewayManager::availability()
      - $defaultGateway       : default gateway code (string|null)
      - $walletEligible       : bool — apakah opsi saldo boleh ditampilkan
      - $walletBalance        : int  — saldo user saat ini (0 kalau guest)
      - $walletAmountRequired : int  — jumlah yang harus dibayar (utk validasi cukup/tidak)
      - $walletTopupEnabled   : bool — boleh redirect ke /akun/topup atau tidak

    Hanya gateway yang `enabled=true` yang dirender. Untuk Eqris, sub-method
    tampil sebagai radio button kedua dengan label custom dari admin.
--}}
@php
    $gw = collect($availableGateways ?? [])->filter(fn ($g) => ! empty($g['enabled']));
    $effectiveDefault = old('gateway', $defaultGateway ?? $gw->keys()->first());
    $walletEligible = $walletEligible ?? false;
    $walletBalance = (int) ($walletBalance ?? 0);
    $walletAmountRequired = (int) ($walletAmountRequired ?? 0);
    $walletTopupEnabled = $walletTopupEnabled ?? false;
    $eqrisMethodsActive = collect(($gw['eqris']['methods'] ?? []))
        ->filter(fn ($m) => ! empty($m['enabled']));
    $defaultEqrisMethod = old('eqris_method', $eqrisMethodsActive->keys()->first());
@endphp

@if ($gw->isNotEmpty())
    <div>
        <label class="block text-sm font-bold text-slate-700 mb-2">Metode Pembayaran</label>

        <div class="space-y-2">
            @if ($gw->has('pakasir'))
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 cursor-pointer hover:border-brand transition">
                    <input type="radio" name="gateway" value="pakasir" @checked($effectiveDefault === 'pakasir') class="mt-1 text-brand">
                    <span class="flex-1">
                        <span class="block font-semibold text-slate-800 text-sm">{{ $gw['pakasir']['label'] }}</span>
                        <span class="block text-xs text-slate-500">{{ $gw['pakasir']['subtitle'] }}</span>
                    </span>
                </label>
            @endif

            @if ($gw->has('eqris') && $eqrisMethodsActive->isNotEmpty())
                <div class="rounded-xl border border-slate-200 hover:border-brand transition">
                    <label class="flex items-start gap-3 px-4 py-3 cursor-pointer">
                        <input type="radio" name="gateway" value="eqris" @checked($effectiveDefault === 'eqris') class="mt-1 text-brand">
                        <span class="flex-1">
                            <span class="block font-semibold text-slate-800 text-sm">{{ $gw['eqris']['label'] }}</span>
                            <span class="block text-xs text-slate-500">{{ $gw['eqris']['subtitle'] }}</span>
                        </span>
                    </label>

                    @if ($eqrisMethodsActive->count() === 1)
                        <input type="hidden" name="eqris_method" value="{{ $eqrisMethodsActive->keys()->first() }}">
                    @else
                        <div class="px-4 pb-3 pl-11 space-y-1.5 border-t border-slate-100 pt-2 mt-1">
                            @foreach ($eqrisMethodsActive as $code => $meta)
                                <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                    <input type="radio" name="eqris_method" value="{{ $code }}" @checked($defaultEqrisMethod === $code) class="text-brand">
                                    <span><b>{{ $meta['label'] }}</b><span class="text-slate-400"> · {{ $meta['subtitle'] }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            @auth
                @if ($walletEligible)
                    @php
                        $walletInsufficient = $walletAmountRequired > 0 && $walletBalance < $walletAmountRequired;
                    @endphp
                    <label class="flex items-start gap-3 rounded-xl border {{ $walletInsufficient ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} px-4 py-3 {{ $walletInsufficient ? '' : 'cursor-pointer' }} transition">
                        <input type="radio" name="gateway" value="wallet"
                               @checked($effectiveDefault === 'wallet')
                               @disabled($walletInsufficient)
                               class="mt-1 {{ $walletInsufficient ? 'text-amber-500' : 'text-emerald-600' }}">
                        <span class="flex-1">
                            <span class="block font-semibold {{ $walletInsufficient ? 'text-amber-900' : 'text-emerald-800' }} text-sm">
                                {{ $gw['wallet']['label'] ?? 'Saldo Akun' }} —
                                Rp {{ number_format($walletBalance, 0, ',', '.') }}
                            </span>
                            <span class="block text-xs {{ $walletInsufficient ? 'text-amber-700' : 'text-emerald-700' }}">
                                @if ($walletInsufficient)
                                    Saldo kurang Rp {{ number_format($walletAmountRequired - $walletBalance, 0, ',', '.') }}.
                                    @if ($walletTopupEnabled)
                                        <a href="{{ route('account.topup.show') }}" class="underline font-semibold">Top up dulu →</a>
                                    @endif
                                @else
                                    {{ $gw['wallet']['subtitle'] ?? 'Bebas fee' }}
                                @endif
                            </span>
                        </span>
                    </label>
                @endif
            @endauth
        </div>
    </div>
@endif
