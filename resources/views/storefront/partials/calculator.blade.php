{{--
    حاسبة التقسيط: @include('storefront.partials.calculator', ['price' => 12000, 'dark' => true])
    بتقرا النسبة وأقل مقدم وأقصى مدة من إعدادات الموقع
--}}
@php
    use App\Support\Site;
    $calcId = 'calc'.uniqid();
    $maxMonths = max(12, (int) Site::get('max_months', 60));
    $minDown = (float) Site::get('min_down_percent', 20);
    $rate = (float) Site::get('installment_rate', 0);
    $price = (float) ($price ?? 0) ?: 8000;
    $dark = $dark ?? false;
    $months = collect(range(12, $maxMonths, 12))->push($maxMonths)->unique()->values();
    $box = $dark ? 'bg-white/5 ring-white/10 text-white' : 'bg-white ring-slate-200 text-slate-900';
    $muted = $dark ? 'text-slate-400' : 'text-slate-500';
@endphp
<div id="{{ $calcId }}" class="rounded-3xl p-5 ring-1 sm:p-6 {{ $box }}" data-rate="{{ $rate }}">
    <div class="space-y-5">
        <div>
            <div class="mb-2 flex items-center justify-between text-sm font-bold"><span>{{ __('سعر العربية') }}</span>
                <span class="flex items-center gap-1"><input type="number" data-k="price" value="{{ round($price) }}" min="500" step="100" class="w-28 rounded-lg border-0 bg-slate-900/5 px-2 py-1 text-end font-black {{ $dark ? 'bg-white/10 text-white' : '' }}"><span class="{{ $muted }}">{{ __('ر.ع') }}</span></span>
            </div>
            <input type="range" data-r="price" min="1000" max="{{ max(60000, round($price * 1.5, -3)) }}" step="100" value="{{ round($price) }}" class="w-full accent-[var(--accent)]" {{ ($lockPrice ?? false) ? 'disabled' : '' }}>
        </div>
        <div>
            <div class="mb-2 flex items-center justify-between text-sm font-bold"><span>{{ __('المقدم') }}</span><span><span data-out="down_pct">{{ $minDown }}</span>% · <span data-out="down">—</span></span></div>
            <input type="range" data-k="down_pct" min="{{ $minDown }}" max="90" step="5" value="{{ max($minDown, 20) }}" class="w-full accent-[var(--accent)]">
        </div>
        <div>
            <div class="mb-2 text-sm font-bold">{{ __('المدة') }}</div>
            <div class="grid grid-cols-5 gap-1.5">
                @foreach ($months as $m)
                    <label class="cursor-pointer">
                        <input type="radio" name="{{ $calcId }}_m" data-k="months" value="{{ $m }}" class="peer sr-only" @checked($m === min(48, $maxMonths) || ($loop->last && $maxMonths < 48))>
                        <span class="block rounded-xl py-2 text-center text-sm font-extrabold ring-1 transition peer-checked:bg-[var(--accent)] peer-checked:text-[var(--accent-ink)] peer-checked:ring-transparent {{ $dark ? 'ring-white/15' : 'ring-slate-200' }}">{{ $m }}<span class="block text-[10px] font-bold opacity-70">{{ __('شهر') }}</span></span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl bg-[var(--accent)] p-5 text-center text-[var(--accent-ink)]">
        <div class="text-sm font-bold opacity-80">{{ __('القسط الشهري التقريبي') }}</div>
        <div class="mt-1 text-4xl font-black tabular-nums"><span data-out="monthly">—</span> <span class="text-base">{{ __('ر.ع') }}</span></div>
        <div class="mt-3 grid grid-cols-2 gap-2 text-xs font-bold opacity-80">
            <div>{{ __('المبلغ المقسّط:') }} <span data-out="financed">—</span></div>
            <div>{{ __('الإجمالي:') }} <span data-out="total">—</span></div>
        </div>
    </div>
    @if (Site::t('finance_note'))
        <p class="mt-3 text-center text-xs {{ $muted }}">{{ Site::t('finance_note') }}</p>
    @endif
    @if (! empty($cta))
        <a href="{{ $cta }}" data-calc-cta class="mt-4 block rounded-2xl bg-slate-900 px-5 py-3.5 text-center font-extrabold text-white ring-1 ring-white/10 transition hover:bg-slate-700">{{ __('اطلب التقسيط دلوقتي ←') }}</a>
    @endif
</div>
<script>
(function () {
    const box = document.getElementById(@js($calcId));
    const rate = parseFloat(box.dataset.rate) || 0;
    const fmt = (n) => Math.round(n).toLocaleString('en-US');
    const q = (s) => box.querySelector(s);
    const calc = (src) => {
        const priceIn = q('[data-k=price]'), range = q('[data-r=price]');
        if (src === range) priceIn.value = range.value; else range.value = priceIn.value;
        const price = parseFloat(priceIn.value) || 0;
        const pct = parseFloat(q('[data-k=down_pct]').value) || 0;
        const months = parseInt((q('[data-k=months]:checked') || {}).value || 12, 10);
        const down = price * pct / 100, financed = Math.max(0, price - down);
        const total = financed * (1 + rate / 100 * months / 12);
        q('[data-out=down_pct]').textContent = pct;
        q('[data-out=down]').textContent = fmt(down);
        q('[data-out=financed]').textContent = fmt(financed);
        q('[data-out=total]').textContent = fmt(total + down);
        q('[data-out=monthly]').textContent = fmt(total / months);
        const cta = q('[data-calc-cta]');
        if (cta) { const u = new URL(cta.href, location.href); u.searchParams.set('price', Math.round(price)); u.searchParams.set('down', Math.round(down)); u.searchParams.set('months', months); cta.href = u; }
        box.dispatchEvent(new CustomEvent('calc', { bubbles: true, detail: { price, down, months, monthly: total / months } }));
    };
    box.addEventListener('input', (e) => calc(e.target));
    calc();
})();
</script>
