<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>تغيير سعر البيع — {{ $contract->contract_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>body { font-family: 'Tajawal', system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-100 pb-10 text-slate-800">
@php
    use App\Support\Money;
    $up = (float) $req->new_price > (float) $req->old_price;
    $states = ['approved' => ['✓ وافقت على السعر الجديد', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
               'rejected' => ['✕ رفضت السعر الجديد', 'bg-rose-50 text-rose-700 ring-rose-200'],
               'cancelled' => ['الطلب ده اتلغى من المعرض', 'bg-slate-50 text-slate-600 ring-slate-200']];
@endphp

<header class="bg-slate-900 px-5 pb-16 pt-6 text-white">
    <div class="mx-auto max-w-lg">
        <div class="text-sm text-slate-400">{{ $company->name_ar ?? config('app.name') }}</div>
        <h1 class="mt-1 text-2xl font-extrabold">طلب تغيير سعر البيع</h1>
        <div class="mt-1 text-sm text-slate-400" dir="ltr">Sale price change request · <span class="font-mono">{{ $contract->contract_number }}</span></div>
    </div>
</header>

<main class="mx-auto -mt-10 max-w-lg space-y-4 px-4">
    @if (session('status'))
        <div class="rounded-2xl bg-emerald-500 p-5 text-center text-white shadow-lg">
            <div class="text-4xl">✓</div>
            <div class="mt-2 text-lg font-extrabold">{{ session('status') }}</div>
        </div>
    @endif

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="text-sm text-slate-500">أهلاً</div>
        <div class="text-lg font-extrabold text-slate-900">{{ $contract->supplier_name }}</div>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            المعرض بيطلب موافقتك على تغيير سعر بيع عربيتك
            <b>{{ $contract->brand_name }} {{ $contract->model_name }} {{ $contract->manufacturing_year }}</b>
            @if ($contract->plate_number) (لوحة {{ $contract->plate_number }}) @endif
        </p>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="grid grid-cols-2 gap-3 text-center">
            <div class="rounded-xl bg-slate-50 p-4">
                <div class="text-xs text-slate-500">السعر الحالي</div>
                <div class="mt-1 text-lg font-extrabold text-slate-500 line-through decoration-rose-400">{{ Money::format($req->old_price) }}</div>
            </div>
            <div class="rounded-xl p-4 {{ $up ? 'bg-emerald-50' : 'bg-amber-50' }}">
                <div class="text-xs {{ $up ? 'text-emerald-700' : 'text-amber-700' }}">السعر الجديد</div>
                <div class="mt-1 text-lg font-extrabold {{ $up ? 'text-emerald-700' : 'text-amber-700' }}">{{ Money::format($req->new_price) }}</div>
            </div>
        </div>
        @if ($req->reason)
            <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm"><span class="font-bold text-slate-500">سبب التغيير:</span> {{ $req->reason }}</div>
        @endif
        @if ($totals)
            <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                @if ((float) $contract->commission_amount > 0)
                    <div class="flex justify-between"><dt class="text-slate-500">عمولة المعرض</dt><dd class="font-bold">− {{ Money::format($contract->commission_amount) }}</dd></div>
                @endif
                <div class="flex justify-between text-base"><dt class="font-bold text-slate-700">صافي مستحقك بعد التغيير</dt><dd class="font-extrabold text-emerald-600">{{ Money::format($totals['total']) }}</dd></div>
                @if ((float) $contract->paid_amount > 0)
                    <div class="flex justify-between"><dt class="text-slate-500">اتدفعلك لحد دلوقتي</dt><dd class="font-bold">{{ Money::format($contract->paid_amount) }}</dd></div>
                @endif
            </dl>
        @endif
    </section>

    @if ($req->status !== 'pending')
        @unless (session('status'))
            <div class="rounded-2xl p-5 text-center font-bold ring-1 {{ $states[$req->status][1] ?? '' }}">{{ $states[$req->status][0] ?? $req->status }}</div>
        @endunless
    @elseif ($blocked)
        <div class="rounded-2xl bg-amber-50 p-5 text-center font-bold text-amber-800 ring-1 ring-amber-200">{{ $blocked }}</div>
    @else
        <form method="POST" action="{{ url()->full() }}" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm"
              onsubmit="setTimeout(() => this.querySelectorAll('button').forEach(b => b.disabled = true));">
            @csrf
            @if ($errors->any())
                <div class="rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
            @endif
            <label class="block">
                <span class="mb-1 block text-sm font-bold text-slate-600">ملاحظة للمعرض (اختياري)</span>
                <input type="text" name="owner_note" maxlength="500" dir="auto" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-400">
            </label>
            <button name="decision" value="approve" class="w-full rounded-2xl bg-emerald-600 py-4 text-base font-extrabold text-white shadow-lg hover:bg-emerald-700 disabled:opacity-60">✓ موافق على السعر الجديد</button>
            <button name="decision" value="reject" class="w-full rounded-2xl bg-white py-3 text-sm font-extrabold text-rose-600 ring-1 ring-rose-200 hover:bg-rose-50 disabled:opacity-60">✕ مش موافق</button>
            <p class="text-center text-[11px] text-slate-400">الرابط ده خاص بيك ومش صالح غير لمدة محدودة.</p>
        </form>
    @endif
</main>
</body>
</html>
