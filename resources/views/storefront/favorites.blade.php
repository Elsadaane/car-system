@extends('storefront.layouts.app')

@section('title', __('المفضلة — ').\App\Support\Site::name())

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-black text-slate-900">{{ __('❤️ العربيات اللي عجبتك') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('محفوظة على جهازك — ارجعلها في أي وقت.') }}</p>

    @if ($cars->isEmpty())
        <div class="mt-8 rounded-3xl bg-white px-6 py-20 text-center ring-1 ring-slate-200">
            <div class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-3xl bg-rose-50 text-3xl">🤍</div>
            <h2 class="text-lg font-black text-slate-900">{{ __('المفضلة فاضية') }}</h2>
            <p class="mt-2 text-sm text-slate-500">{{ __('دوس على القلب في أي عربية عشان تحفظها هنا.') }}</p>
            <a href="{{ lroute('storefront.vehicles.index') }}" class="mt-6 inline-block rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white">{{ __('تصفّح السيارات') }}</a>
        </div>
    @else
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($cars as $car)@include('storefront.partials.vehicle-card', ['car' => $car])@endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const ids = LV.store.get('fav');
    if (!{{ $hasIds ? 'true' : 'false' }} && ids.length) location.replace('?ids=' + ids.join(','));
    // لما يشيل عربية من المفضلة هنا، تختفي
    document.addEventListener('click', (e) => { const b = e.target.closest('[data-fav]'); if (b && !b.classList.contains('is-on')) b.closest('article').style.display = 'none'; });
})();
</script>
@endpush
