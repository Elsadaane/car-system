{{--
    لوحة توقيع:
    @include('admin.partials.signature-pad', ['name' => 'signature', 'label' => 'توقيع العميل', 'existing' => $url])
    بتبعت الصورة كـ data URL في حقل مخفي بنفس الاسم.
--}}
@php $padId = 'sig_'.\Illuminate\Support\Str::random(6); @endphp

<div data-signature-pad id="{{ $padId }}">
    <div class="mb-1.5 flex items-center justify-between">
        <span class="text-sm font-semibold text-slate-700">{{ $label }}</span>
        <button type="button" data-sig-clear class="rounded-lg px-2 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50">{{ __('مسح التوقيع') }}</button>
    </div>
    <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-white">
        <canvas class="block h-44 w-full touch-none cursor-crosshair"></canvas>
        <div data-sig-hint class="pointer-events-none absolute inset-0 grid place-items-center text-sm text-slate-300">
            {{ ! empty($existing) ? '' : __('وقّع هنا بالماوس أو بصباعك') }}
        </div>
        @if (! empty($existing))
            <img data-sig-existing src="{{ $existing }}" alt="" class="pointer-events-none absolute inset-0 m-auto max-h-full max-w-full object-contain opacity-60">
        @endif
        <div class="pointer-events-none absolute inset-x-8 bottom-10 border-b border-slate-200"></div>
    </div>
    <input type="hidden" name="{{ $name }}" value="">
</div>

@once
<script>
(function () {
    const init = (wrap) => {
        const canvas = wrap.querySelector('canvas');
        const input = wrap.querySelector('input[type=hidden]');
        const hint = wrap.querySelector('[data-sig-hint]');
        const existing = wrap.querySelector('[data-sig-existing]');
        const ctx = canvas.getContext('2d');
        let drawing = false, dirty = false;

        const resize = () => {
            const ratio = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            if (!rect.width) return;
            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
        };
        const point = (e) => { const r = canvas.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };

        canvas.addEventListener('pointerdown', (e) => {
            drawing = true; canvas.setPointerCapture(e.pointerId);
            const [x, y] = point(e); ctx.beginPath(); ctx.moveTo(x, y);
            if (hint) hint.textContent = '';
            if (existing) existing.style.display = 'none';
        });
        canvas.addEventListener('pointermove', (e) => {
            if (!drawing) return;
            const [x, y] = point(e); ctx.lineTo(x, y); ctx.stroke(); dirty = true;
        });
        const end = () => { if (!drawing) return; drawing = false; if (dirty) input.value = canvas.toDataURL('image/png'); };
        canvas.addEventListener('pointerup', end);
        canvas.addEventListener('pointerleave', end);

        wrap.querySelector('[data-sig-clear]').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            input.value = ''; dirty = false;
            if (existing) existing.style.display = '';
            if (hint && !existing) hint.textContent = @js(__('وقّع هنا بالماوس أو بصباعك'));
        });

        resize();
        new ResizeObserver(() => { if (!dirty) resize(); }).observe(canvas);
    };

    const boot = () => document.querySelectorAll('[data-signature-pad]:not([data-ready])').forEach((w) => { w.dataset.ready = '1'; init(w); });
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
    window.bootSignaturePads = boot;
})();
</script>
@endonce
