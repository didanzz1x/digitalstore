{{-- Sub-layout akun: di-include dari child views, isi via @yield('account_content'). --}}
<section class="py-8 md:py-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- session('success'/'error') sudah ditampilkan global di layouts/app.blade.php --}}
        <div class="grid lg:grid-cols-[240px_1fr] gap-6">
            <aside class="space-y-2">
                <div class="rounded-2xl bg-white border border-slate-200 p-4">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full grid place-items-center bg-brand text-white font-bold">
                            {{ strtoupper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                </div>
                <nav class="rounded-2xl bg-white border border-slate-200 p-2 text-sm">
                    @php
                        $rn = request()->route()?->getName();
                        $site = \App\Models\SiteSetting::current();
                        $items = [
                            ['account.index', 'Dashboard', '🏠'],
                            ['account.orders.index', 'History Pesanan', '📦'],
                            ['account.profile', 'Profil & Password', '⚙️'],
                            ['account.telegram.show', 'Hubungkan Telegram', '✈️'],
                        ];
                        if ($site->affiliate_enabled ?? false) {
                            $items[] = ['account.affiliate.dashboard', 'Affiliate', '💰'];
                        }
                        if ($site->membership_enabled ?? false) {
                            $items[] = ['membership.show', 'Membership', '⭐'];
                        }
                    @endphp
                    @foreach ($items as [$route, $label, $icon])
                        <a href="{{ route($route) }}"
                           class="flex items-center gap-2 px-3 py-2 rounded-lg {{ $rn === $route ? 'bg-brand-soft text-brand font-semibold' : 'text-slate-700 hover:bg-slate-50' }}">
                            <span>{{ $icon }}</span>{{ $label }}
                        </a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit" class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg text-rose-600 hover:bg-rose-50">
                            <span>↩</span>Keluar
                        </button>
                    </form>
                </nav>
            </aside>

            <div class="min-w-0">
                @yield('account_content')
            </div>
        </div>
    </div>
</section>
