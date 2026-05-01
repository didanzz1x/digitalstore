@extends('layouts.app')

@section('title', 'Public API — Documentation')

@section('content')
@php $site = \App\Models\SiteSetting::current(); @endphp
<section class="py-10">
    <div class="max-w-3xl mx-auto px-4 prose prose-slate max-w-none">
        <h1 class="text-3xl font-extrabold tracking-tight">Public API</h1>
        <p class="text-slate-500">REST API untuk integrasi pihak ketiga — ambil produk, kategori, dan stok dari toko ini.</p>

        @if (! empty($site->public_api_docs_html))
            {!! $site->public_api_docs_html !!}
        @else
            <h2>Authentication</h2>
            <p>Semua endpoint butuh header <code>X-API-KEY: ak_...</code>. Hubungi admin untuk dapat API key.</p>

            <h2>Rate Limit</h2>
            <p>Default 60 request/menit per key. Admin bisa mengubah per client. IP whitelisting opsional.</p>

            <h2>Endpoints</h2>

            <h3>GET /api/v1/products</h3>
            <p>List semua produk paginated.</p>
            <p><b>Query:</b> <code>per_page</code> (default 20, max 100), <code>page</code>, <code>q</code> (search), <code>category</code> (id).</p>
            <p><b>Response:</b></p>
<pre><code class="language-json">{
  "data": [
    {
      "id": 1,
      "name": "Netflix Premium 1 Bulan",
      "short_description": "Akun sharing, garansi penuh",
      "lowest_price": 35000,
      "is_best_seller": true,
      "category": { "id": 1, "name": "Streaming" },
      "image_url": "https://...",
      "sold_count": 120,
      "variants": [
        { "id": 1, "name": "Sharing", "price": 35000, "list_price": 35000, "stock_available": 12, "warranty_days": 30, "share_type": "sharing", "is_auto_send": true }
      ]
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "last_page": 5, "total": 100 }
}</code></pre>

            <h3>GET /api/v1/products/{id}</h3>
            <p>Detail satu produk (termasuk semua varian + stok).</p>

            <h3>GET /api/v1/categories</h3>
            <p>List kategori.</p>

            <h2>Curl Example</h2>
<pre><code>curl -H "X-API-KEY: ak_xxxxxxxxxxxx" \
     "{{ rtrim(config('app.url'), '/') }}/api/v1/products?per_page=10"</code></pre>

            <h2>Error Codes</h2>
            <ul>
                <li><b>401</b> — API key kosong / tidak valid.</li>
                <li><b>403</b> — IP tidak ada di whitelist.</li>
                <li><b>429</b> — Rate limit exceeded.</li>
                <li><b>503</b> — Public API dinonaktifkan admin.</li>
            </ul>
        @endif
    </div>
</section>
@endsection
