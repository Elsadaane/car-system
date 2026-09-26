@php
    use App\Http\Controllers\Admin\InquiryController as CRM;
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    $created = Carbon::parse($i->created_at);
    $hours = $created->diffInHours(now());
    $car = $i->brand ? trim($i->brand.' '.$i->model.' '.$i->year) : null;
    $price = $i->discount_price ?: $i->selling_price;
    $src = CRM::SOURCES[strtolower((string) $i->source)] ?? ($i->source ?: __('🌐 الموقع'));
    $payload = [
        'id' => $i->id, 'name' => $i->name, 'phone' => $i->phone, 'email' => $i->email, 'number' => $i->inquiry_number,
        'type' => CRM::TYPES[$i->type] ?? $i->type, 'message' => $i->message, 'car' => $car, 'date' => $i->preferred_date,
        'source' => $src, 'campaign' => $i->utm_campaign, 'status' => $i->status, 'assigned' => $i->assigned_to,
        'notes' => $i->staff_notes, 'created' => $created->format('Y-m-d H:i'),
    ];
@endphp
<article draggable="true" data-card="{{ $i->id }}" data-inq="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}"
         class="cursor-grab rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200 transition hover:shadow-md active:cursor-grabbing">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="truncate text-sm font-extrabold text-slate-900">{{ $i->name }}</div>
            <div class="font-mono text-[11px] text-slate-400" dir="ltr">{{ $i->phone }}</div>
        </div>
        @if ($i->status === 'new')
            {{-- مؤشر سرعة الرد: أخضر أقل من ساعتين، أصفر لحد يوم، أحمر أكتر --}}
            <span data-sla class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-black {{ $hours >= 24 ? 'bg-rose-600 text-white' : ($hours >= 2 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}">{{ $created->diffForHumans(null, true) }}</span>
        @endif
    </div>
    <div class="mt-2 flex flex-wrap gap-1 text-[11px]">
        <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-bold text-slate-600">{{ CRM::TYPES[$i->type] ?? $i->type }}</span>
        <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-slate-600">{{ $src }}</span>
    </div>
    @if ($car)
        <div class="mt-2 truncate text-xs text-slate-600">🚗 {{ $car }}@if ($price) · <b>{{ Money::format($price, false) }}</b>@endif</div>
    @endif
    @if ($i->staff_notes)
        <div class="mt-2 line-clamp-2 rounded-lg bg-amber-50 px-2 py-1 text-[11px] text-amber-900">📝 {{ $i->staff_notes }}</div>
    @endif
    <div class="mt-2 flex items-center justify-between gap-2 border-t border-slate-100 pt-2">
        <span class="truncate text-[11px] {{ $i->assignee ? 'text-slate-500' : 'font-bold text-amber-600' }}">{{ $i->assignee ? '👤 '.$i->assignee : __('⚠️ محدش ماسكه') }}</span>
        <a href="https://wa.me/{{ preg_replace('/\D/', '', (string) $i->phone) }}" target="_blank" draggable="false" class="shrink-0 rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100">{{ __('واتساب') }}</a>
    </div>
</article>
