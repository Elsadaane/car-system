@extends('admin.layouts.app')

@section('title', __('الرئيسية'))
@section('subtitle', __('أهلاً ').auth()->user()->name.' 👋 — '.now()->translatedFormat('l j F'))

@section('actions')
    <button type="button" id="editDash" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">{{ __('✏️ خصّص الرئيسية') }}</button>
@endsection

@section('content')
    @unless ($permsOk)
        <div class="mb-4 rounded-2xl bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800 ring-1 ring-rose-200">
            {{ __('⚠️ نظام الصلاحيات (Spatie) مش متسطّب — المدير العام بس اللي شايف اللوحة. شغّل في الـ terminal:') }} <code dir="ltr" class="rounded bg-white px-2 py-0.5">composer require spatie/laravel-permission</code> {{ __('وبعدها') }} <code dir="ltr" class="rounded bg-white px-2 py-0.5">php artisan migrate</code>
        </div>
    @endunless

    {{-- شريط التخصيص --}}
    <div id="editBar" class="sticky top-16 z-20 mb-4 hidden flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-900 p-3 ps-5 text-white shadow-xl">
        <div class="text-sm font-bold">{{ __('✋ اسحب الكروت عشان ترتّبها — غيّر حجمها أو شيلها، وضيف اللي محتاجه') }}</div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="openDrawer" class="rounded-xl bg-white/10 px-4 py-2 text-sm font-bold hover:bg-white/20">{{ __('＋ إضافة ويدجت') }}</button>
            <button type="button" id="resetDash" class="rounded-xl px-3 py-2 text-sm font-bold text-slate-300 hover:text-white">{{ __('↺ الافتراضي') }}</button>
            <button type="button" id="cancelDash" class="rounded-xl px-3 py-2 text-sm font-bold text-slate-300 hover:text-white">{{ __('إلغاء') }}</button>
            <button type="button" id="saveDash" class="rounded-xl bg-emerald-500 px-5 py-2 text-sm font-extrabold hover:bg-emerald-600">{{ __('💾 حفظ الترتيب') }}</button>
        </div>
    </div>

    <div id="dash" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @forelse ($layout as $w)
            @include('admin.dashboard.card', ['key' => $w['k'], 'size' => $w['s'], 'w' => \App\Support\Dashboard::WIDGETS[$w['k']], 'd' => $data[$w['k']] ?? []])
        @empty
            <div data-empty class="rounded-2xl bg-white p-12 text-center shadow-sm ring-1 ring-slate-200 md:col-span-2 xl:col-span-4">
                <div class="text-4xl">🧩</div>
                <p class="mt-2 font-bold text-slate-600">{{ __('الرئيسية فاضية') }}</p>
                <p class="text-sm text-slate-400">{{ __('اضغط "خصّص الرئيسية" وضيف الويدجتس اللي تهمك.') }}</p>
            </div>
        @endforelse
    </div>

    {{-- درج الإضافة --}}
    <dialog id="drawer" class="m-0 ms-auto h-full max-h-none w-full max-w-md bg-white p-0 shadow-2xl backdrop:bg-slate-900/40">
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between border-b border-slate-100 p-5">
                <div><h2 class="text-lg font-black text-slate-900">{{ __('＋ إضافة ويدجت') }}</h2><p class="text-xs text-slate-400">{{ __('اللي بيظهر هنا على حسب صلاحياتك') }}</p></div>
                <button type="button" onclick="document.getElementById('drawer').close()" class="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-slate-100">✕</button>
            </div>
            <div class="flex-1 space-y-2 overflow-y-auto p-4" id="drawerList">
                @foreach ($available as $k => [$title, $icon, , $size, $desc])
                    <button type="button" data-add="{{ $k }}" data-size="{{ $size }}" class="flex w-full items-center gap-3 rounded-2xl p-3 text-start ring-1 ring-slate-200 transition hover:bg-blue-50 hover:ring-blue-300">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-slate-100 text-xl">{{ $icon }}</span>
                        <span class="min-w-0 flex-1"><span class="block font-extrabold text-slate-800">{{ $title }}</span><span class="block text-xs text-slate-500">{{ $desc }}</span></span>
                        <span class="text-lg font-black text-blue-600">＋</span>
                    </button>
                @endforeach
            </div>
        </div>
    </dialog>

    <style>
        #dash.editing [data-widget] { cursor: grab; outline: 2px dashed #93c5fd; outline-offset: 3px; }
        #dash.editing [data-widget] a, #dash.editing [data-widget] [data-body] { pointer-events: none; }
        #dash [data-widget].dragging { opacity: .35; }
        #dash:not(.editing) [data-edit-tools] { display: none; }
    </style>

    <script>
    (function () {
        const dash = document.getElementById('dash'), bar = document.getElementById('editBar'), drawer = document.getElementById('drawer');
        const SIZES = { 1: 'xl:col-span-1', 2: 'md:col-span-2 xl:col-span-2', 3: 'md:col-span-2 xl:col-span-3', 4: 'md:col-span-2 xl:col-span-4' };
        const ALL = Object.values(SIZES).join(' ').split(' ');
        const setSize = (el, s) => { el.classList.remove(...ALL); el.classList.add(...SIZES[s].split(' ')); el.dataset.size = s;
            el.querySelectorAll('[data-sz]').forEach((b) => { const on = +b.dataset.sz === +s; b.classList.toggle('bg-blue-600', on); b.classList.toggle('text-white', on); }); };
        const inLayout = () => [...dash.querySelectorAll('[data-widget]')].map((el) => el.dataset.widget);
        const syncDrawer = () => { const used = inLayout(); document.querySelectorAll('[data-add]').forEach((b) => { b.hidden = used.includes(b.dataset.add); }); };
        const wire = (el) => {
            el.addEventListener('dragstart', (e) => { if (!dash.classList.contains('editing')) return e.preventDefault(); el.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
            el.addEventListener('dragend', () => el.classList.remove('dragging'));
            el.querySelectorAll('[data-sz]').forEach((b) => b.addEventListener('click', () => setSize(el, +b.dataset.sz)));
            el.querySelector('[data-remove]')?.addEventListener('click', () => { el.remove(); syncDrawer(); });
            setSize(el, +el.dataset.size);
        };
        dash.querySelectorAll('[data-widget]').forEach(wire);

        dash.addEventListener('dragover', (e) => {
            if (!dash.classList.contains('editing')) return;
            e.preventDefault();
            const dragging = dash.querySelector('.dragging'), over = e.target.closest('[data-widget]');
            if (!dragging || !over || over === dragging) return;
            const r = over.getBoundingClientRect();
            const rtl = getComputedStyle(dash).direction === 'rtl';
            const after = (e.clientY - r.top) > r.height * 0.6 || (rtl ? e.clientX < r.left + r.width / 2 : e.clientX > r.left + r.width / 2);
            over.parentNode.insertBefore(dragging, after ? over.nextSibling : over);
        });

        const edit = (on) => {
            dash.classList.toggle('editing', on); bar.classList.toggle('hidden', !on); bar.classList.toggle('flex', on);
            dash.querySelectorAll('[data-widget]').forEach((el) => { el.draggable = on; });
            syncDrawer();
        };
        document.getElementById('editDash').addEventListener('click', () => edit(true));
        document.getElementById('cancelDash').addEventListener('click', () => location.reload());
        document.getElementById('openDrawer').addEventListener('click', () => drawer.showModal());
        drawer.addEventListener('click', (e) => { if (e.target === drawer) drawer.close(); });

        document.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', async () => {
            b.disabled = true;
            try {
                const r = await fetch(@js(url('admin/dashboard/widget')) + '/' + b.dataset.add + '?s=' + b.dataset.size, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) throw new Error(r.status);
                const box = document.createElement('div'); box.innerHTML = (await r.text()).trim();
                const el = box.firstElementChild;
                dash.querySelector('[data-empty]')?.remove();
                dash.prepend(el); wire(el); el.draggable = true; syncDrawer(); drawer.close();
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (e) { alert(@js(__('الويدجت ما اتضافش: ')) + e.message); }
            b.disabled = false;
        }));

        const save = async (reset = false) => {
            const layout = [...dash.querySelectorAll('[data-widget]')].map((el) => ({ k: el.dataset.widget, s: +el.dataset.size }));
            const r = await fetch(@js(route('admin.dashboard.layout')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify({ layout, reset }) });
            if (r.ok) location.reload(); else alert(@js(__('ما اتحفظش — جرّب تاني')));
        };
        document.getElementById('saveDash').addEventListener('click', () => save(false));
        document.getElementById('resetDash').addEventListener('click', () => confirm(@js(__('ترجع للترتيب الافتراضي؟'))) && save(true));
    })();
    </script>
@endsection
