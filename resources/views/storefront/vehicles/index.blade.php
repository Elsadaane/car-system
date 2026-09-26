@extends('storefront.layouts.app')

@php
    use App\Support\Showroom;
    use App\Http\Controllers\Storefront\VehicleController as VC;
    $in = 'w-full rounded-xl border-0 bg-slate-100 px-3 py-2.5 text-sm font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-[var(--accent)]';
    $active = array_filter($filters, fn ($v) => $v !== null && $v !== '');
    $labels = [
        'q' => fn ($v) => '«'.$v.'»',
        'brand' => fn ($v) => \App\Support\Lang::pick($brands->firstWhere('id', (int) $v), 'name') ?? $v,
        'model' => fn ($v) => \App\Support\Lang::pick($models->firstWhere('id', (int) $v), 'name') ?? $v,
        'body' => fn ($v) => Showroom::bodyLabel($v),
        'fuel' => fn ($v) => Showroom::fuelLabel($v),
        'color' => fn ($v) => $v,
        'branch' => fn ($v) => '📍 '.(\App\Support\Lang::pick($branches->firstWhere('id', (int) $v), 'name') ?? $v),
        'year_from' => fn ($v) => __('من سنة ').$v, 'year_to' => fn ($v) => __('لحد سنة ').$v,
        'price_from' => fn ($v) => __('من ').number_format((float) $v), 'price_to' => fn ($v) => __('لحد ').number_format((float) $v),
        'monthly_max' => fn ($v) => __('قسط لحد ').number_format((float) $v).__('/شهر'), 'offers' => fn ($v) => __('🔥 العروض بس'),
    ];
@endphp

@section('title', $title.' — '.\App\Support\Site::name())

@section('content')
<div class="bg-slate-950">
    <div class="mx-auto max-w-7xl px-4 pb-16 pt-10 sm:px-6 lg:px-8">
        <nav class="mb-3 text-xs font-bold text-slate-500"><a href="{{ lroute('storefront.home') }}" class="hover:text-white">{{ __('الرئيسية') }}</a> / <span class="text-slate-300">{{ $title }}</span></nav>
        <h1 class="text-3xl font-black text-white sm:text-4xl">{{ $title }}</h1>
        <p class="mt-2 text-slate-400"><span class="font-extrabold text-[var(--accent)]">{{ $vehicles->total() }}</span> {{ __('سيارة') }} {{ $active ? __('مطابقة لبحثك') : __('جاهزة للمعاينة') }}</p>
    </div>
</div>

<div class="relative z-10 mx-auto -mt-8 max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid gap-6 lg:grid-cols-[290px_1fr]">

        {{-- ═══════ الفلاتر ═══════ --}}
        <aside>
            <details class="group rounded-3xl bg-white shadow-sm ring-1 ring-slate-200 lg:sticky lg:top-24" id="filtersBox">
                <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 font-extrabold text-slate-900 lg:pointer-events-none">
                    <span>{{ __('⚙️ فلترة النتائج') }} @if ($active)<span class="ms-1 rounded-full bg-[var(--accent)] px-2 py-0.5 text-xs text-[var(--accent-ink)]">{{ count($active) }}</span>@endif</span>
                    <span class="text-slate-400 transition group-open:rotate-180 lg:hidden">▾</span>
                </summary>
                <form action="{{ lroute('storefront.vehicles.index') }}" method="GET" id="filterForm" class="space-y-4 border-t border-slate-100 p-5">
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('🔍 ماركة، موديل، لون، كود...') }}" class="{{ $in }}">

                    <label class="flex cursor-pointer items-center justify-between rounded-xl bg-rose-50 px-3 py-2.5 text-sm font-extrabold text-rose-700">
                        {{ __('🔥 العروض والخصومات بس') }}
                        <input type="checkbox" name="offers" value="1" @checked(! empty($filters['offers'])) class="h-5 w-5 rounded accent-rose-500" data-auto>
                    </label>

                    @if ($bodies->count() > 1)
                        <div>
                            <div class="mb-2 text-xs font-extrabold text-slate-500">{{ __('نوع العربية') }}</div>
                            <div class="grid grid-cols-3 gap-1.5">
                                @foreach ($bodies as $type => $c)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="body" value="{{ $type }}" class="peer sr-only" @checked(($filters['body'] ?? '') === $type) data-auto>
                                        <span class="block rounded-xl bg-slate-100 px-1 py-2 text-center text-[11px] font-bold text-slate-600 transition peer-checked:bg-slate-900 peer-checked:text-white"><span class="block text-xl">{{ Showroom::bodyIcon($type) }}</span>{{ Showroom::bodyLabel($type) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div>
                        <div class="mb-1.5 text-xs font-extrabold text-slate-500">{{ __('الماركة') }}</div>
                        <select name="brand" class="{{ $in }}" data-auto data-reset="model">
                            <option value="">{{ __('كل الماركات') }}</option>
                            @foreach ($brands as $b)<option value="{{ $b->id }}" @selected(($filters['brand'] ?? null) == $b->id)>{{ \App\Support\Lang::pick($b, 'name') }}</option>@endforeach
                        </select>
                    </div>
                    @if ($models->isNotEmpty())
                        <div>
                            <div class="mb-1.5 text-xs font-extrabold text-slate-500">{{ __('الموديل') }}</div>
                            <select name="model" class="{{ $in }}" data-auto>
                                <option value="">{{ __('كل الموديلات') }}</option>
                                @foreach ($models as $m)<option value="{{ $m->id }}" @selected(($filters['model'] ?? null) == $m->id)>{{ \App\Support\Lang::pick($m, 'name') }}</option>@endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <div class="mb-1.5 text-xs font-extrabold text-slate-500">{{ __('الميزانية (ر.ع)') }}</div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" name="price_from" value="{{ $filters['price_from'] ?? '' }}" placeholder="{{ __('من') }}" min="0" step="500" class="{{ $in }}">
                            <input type="number" name="price_to" value="{{ $filters['price_to'] ?? '' }}" placeholder="{{ __('إلى') }}" min="0" step="500" class="{{ $in }}">
                        </div>
                    </div>
                    <div>
                        <div class="mb-1.5 text-xs font-extrabold text-slate-500">{{ __('📅 أقصى قسط شهري') }}</div>
                        <input type="number" name="monthly_max" value="{{ $filters['monthly_max'] ?? '' }}" placeholder="{{ __('مثلاً 250') }}" min="0" step="10" class="{{ $in }}">
                    </div>
                    <div>
                        <div class="mb-1.5 text-xs font-extrabold text-slate-500">{{ __('سنة الصنع') }}</div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" name="year_from" value="{{ $filters['year_from'] ?? '' }}" placeholder="{{ __('من') }}" min="1980" max="{{ date('Y') + 1 }}" class="{{ $in }}">
                            <input type="number" name="year_to" value="{{ $filters['year_to'] ?? '' }}" placeholder="{{ __('إلى') }}" min="1980" max="{{ date('Y') + 1 }}" class="{{ $in }}">
                        </div>
                    </div>
                    @if ($fuels->count() > 1)
                        <div>
                            <div class="mb-1.5 text-xs font-extrabold text-slate-500">{{ __('الوقود') }}</div>
                            <select name="fuel" class="{{ $in }}" data-auto>
                                <option value="">{{ __('الكل') }}</option>
                                @foreach ($fuels as $fu)<option value="{{ $fu }}" @selected(($filters['fuel'] ?? '') === $fu)>{{ Showroom::fuelLabel($fu) }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    @if ($colors->count() > 1)
                        <div>
                            <div class="mb-1.5 text-xs font-extrabold text-slate-500">{{ __('اللون') }}</div>
                            <select name="color" class="{{ $in }}" data-auto>
                                <option value="">{{ __('الكل') }}</option>
                                @foreach ($colors as $c)<option value="{{ $c }}" @selected(($filters['color'] ?? '') === $c)>{{ $c }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    @if ($branches->count() > 1)
                        <div>
                            <div class="mb-1.5 text-xs font-extrabold text-slate-500">{{ __('الفرع') }}</div>
                            <select name="branch" class="{{ $in }}" data-auto>
                                <option value="">{{ __('كل الفروع') }}</option>
                                @foreach ($branches as $br)<option value="{{ $br->id }}" @selected(($filters['branch'] ?? null) == $br->id)>{{ \App\Support\Lang::pick($br, 'name') }}</option>@endforeach
                            </select>
                        </div>
                    @endif

                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 rounded-xl bg-slate-900 px-4 py-3 text-sm font-extrabold text-white hover:bg-slate-700">{{ __('عرض النتائج') }}</button>
                        @if ($active)<a href="{{ lroute('storefront.vehicles.index') }}" class="rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-600 hover:bg-slate-200">{{ __('مسح') }}</a>@endif
                    </div>
                </form>
            </details>
        </aside>

        {{-- ═══════ النتائج ═══════ --}}
        <div>
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-3xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-wrap gap-1.5">
                    @forelse ($active as $k => $v)
                        @continue(! isset($labels[$k]))
                        <a href="{{ lroute('storefront.vehicles.index', array_merge(\Illuminate\Support\Arr::except($active, [$k, $k === 'brand' ? 'model' : '_']), $sort ? ['sort' => $sort] : [])) }}" class="inline-flex items-center gap-1 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-rose-600">{{ $labels[$k]($v) }} <span class="opacity-60">✕</span></a>
                    @empty
                        <span class="px-2 text-sm font-bold text-slate-400">{{ __('كل السيارات') }}</span>
                    @endforelse
                </div>
                <div class="flex items-center gap-2">
                    <form action="{{ lroute('storefront.vehicles.index') }}" method="GET">
                        @foreach ($active as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                        <select name="sort" onchange="this.form.submit()" class="rounded-xl border-0 bg-slate-100 py-2 pe-8 ps-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-[var(--accent)]">
                            @foreach (VC::SORTS as $k => $l)<option value="{{ $k }}" @selected($sort === $k)>{{ __($l) }}</option>@endforeach
                        </select>
                    </form>
                    <div class="hidden rounded-xl bg-slate-100 p-1 sm:flex">
                        <button type="button" data-view="grid" class="rounded-lg px-2.5 py-1.5 text-sm" title="{{ __('شبكة') }}">▦</button>
                        <button type="button" data-view="list" class="rounded-lg px-2.5 py-1.5 text-sm" title="{{ __('قائمة') }}">☰</button>
                    </div>
                </div>
            </div>

            @if ($vehicles->isEmpty())
                <div class="rounded-3xl bg-white px-6 py-20 text-center ring-1 ring-slate-200">
                    <div class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-3xl bg-slate-100 text-3xl">🔍</div>
                    <h3 class="text-lg font-black text-slate-900">{{ __('مفيش نتايج مطابقة') }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ __('جرّب توسّع البحث، أو سيبلنا طلبك وهندوّرلك على العربية اللي عايزها.') }}</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-2">
                        <a href="{{ lroute('storefront.vehicles.index') }}" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white">{{ __('كل السيارات') }}</a>
                        <a href="{{ lroute('storefront.contact', ['need' => 1]) }}" class="rounded-xl bg-[var(--accent)] px-5 py-3 text-sm font-bold text-[var(--accent-ink)]">{{ __('دوّرولي على عربية 🙋') }}</a>
                    </div>
                </div>
            @else
                <div id="results" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3" data-grid="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($vehicles as $car)
                        <div data-item>@include('storefront.partials.vehicle-card', ['car' => $car])</div>
                    @endforeach
                </div>
                <div class="mt-10">{{ $vehicles->onEachSide(1)->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const box = document.getElementById('filtersBox');
    if (window.matchMedia('(min-width: 1024px)').matches) box.open = true;

    const form = document.getElementById('filterForm');
    form.querySelectorAll('[data-auto]').forEach((el) => el.addEventListener('change', () => {
        if (el.dataset.reset) { const r = form.querySelector('[name=' + el.dataset.reset + ']'); if (r) r.value = ''; }
        form.requestSubmit();
    }));
    form.addEventListener('submit', () => { [...form.elements].forEach((el) => { if (el.name && !el.value && el.type !== 'radio' && el.type !== 'checkbox') el.disabled = true; }); });

    // عرض شبكة / قائمة (بيتفتكر)
    const results = document.getElementById('results');
    const setView = (v) => {
        if (!results) return;
        results.className = v === 'list' ? 'grid gap-4' : results.dataset.grid;
        results.querySelectorAll('article').forEach((a) => {
            a.classList.toggle('sm:flex-row', v === 'list');
            const media = a.querySelector('a');
            media.classList.toggle('sm:w-80', v === 'list'); media.classList.toggle('sm:aspect-auto', v === 'list');
        });
        document.querySelectorAll('[data-view]').forEach((b) => b.classList.toggle('bg-white', b.dataset.view === v));
        LV.store.set('view', [v]);
    };
    document.querySelectorAll('[data-view]').forEach((b) => b.addEventListener('click', () => setView(b.dataset.view)));
    setView(LV.store.get('view')[0] || 'grid');
})();
</script>
@endpush
