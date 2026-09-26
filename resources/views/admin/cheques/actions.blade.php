{{-- أزرار إجراءات الشيك (حسب حالته) --}}
@php $acts = \App\Support\Cheques::ACTIONS[$c->status] ?? []; @endphp
<div class="inline-flex flex-wrap items-center justify-end gap-1">
    @foreach ($acts as $a)
        <form method="POST" action="{{ route('admin.cheques.action', $c->id) }}" class="inline"
              @if ($a === 'bounce') onsubmit="const r = prompt(@js(__('سبب الارتجاع (اختياري):'))); if (r === null) return false; this.reason.value = r;"
              @elseif ($a === 'clear' && $c->status === 'received') onsubmit="return confirm(@js(__('تسجيل الشيك إنه اتصرف؟ هتتعمل دفعة على العقد.')))"
              @elseif (in_array($a, ['cancel', 'return'], true)) onsubmit="return confirm(@js(__('متأكد؟')))" @endif>
            @csrf @method('PATCH')
            <input type="hidden" name="action" value="{{ $a }}">
            <input type="hidden" name="reason">
            <button class="rounded-lg px-2.5 py-1.5 text-xs font-bold {{ ['deposit' => 'bg-blue-50 text-blue-700 hover:bg-blue-100', 'clear' => 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100', 'bounce' => 'bg-rose-50 text-rose-700 hover:bg-rose-100', 'return' => 'text-amber-700 hover:bg-amber-50', 'cancel' => 'text-slate-500 hover:bg-slate-100'][$a] }}">{{ __(\App\Support\Cheques::ACTION_LABELS[$a]) }}</button>
        </form>
    @endforeach
    @if ($c->status !== 'cleared')
        <form method="POST" action="{{ route('admin.cheques.destroy', $c->id) }}" class="inline" onsubmit="return confirm(@js(__('مسح الشيك ده؟')))">
            @csrf @method('DELETE')
            <button class="grid h-7 w-7 place-items-center rounded-lg text-slate-300 hover:bg-rose-50 hover:text-rose-600" title="{{ __('مسح') }}">🗑</button>
        </form>
    @endif
</div>
