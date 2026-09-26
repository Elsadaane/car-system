<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>توقيع عقد {{ $contract->contract_number }} — {{ $company->name_ar ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>body { font-family: 'Tajawal', system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-100 pb-10 text-slate-800">
@php
    use App\Support\Money;
    use App\Support\Media;
    use Illuminate\Support\Carbon;
    $img = Media::url(Media::list($contract->vehicle_images)[0] ?? null);
@endphp

<header class="bg-slate-900 px-5 pb-16 pt-6 text-white">
    <div class="mx-auto max-w-lg">
        <div class="text-sm text-slate-400">{{ $company->name_ar ?? config('app.name') }}</div>
        <h1 class="mt-1 text-2xl font-extrabold">عقد شراء مركبة</h1>
        <div class="mt-1 font-mono text-sm text-slate-400">{{ $contract->contract_number }}</div>
    </div>
</header>

<main class="mx-auto -mt-10 max-w-lg space-y-4 px-4">

    @if (session('status'))
        <div class="rounded-2xl bg-emerald-500 p-5 text-center text-white shadow-lg">
            <div class="text-4xl">✓</div>
            <div class="mt-2 text-lg font-extrabold">{{ session('status') }}</div>
        </div>
    @endif

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
        @if ($img) <img src="{{ $img }}" alt="" class="aspect-[16/9] w-full object-cover"> @endif
        <div class="p-5">
            <div class="text-sm text-slate-500">مبروك يا</div>
            <div class="text-lg font-extrabold text-slate-900">{{ $contract->customer_name }} 🎉</div>
            <dl class="mt-4 space-y-2.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">العربية</dt><dd class="font-bold">{{ $contract->brand_name }} {{ $contract->model_name }} {{ $contract->manufacturing_year }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">اللون</dt><dd class="font-bold">{{ $contract->color }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">الشاسيه</dt><dd class="font-mono font-bold">{{ $contract->chassis_number ?: '—' }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <dl class="space-y-2.5 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">السعر</dt><dd class="font-bold">{{ Money::format($contract->sale_price) }}</dd></div>
            @if ((float) $contract->discount_amount > 0) <div class="flex justify-between"><dt class="text-slate-500">الخصم</dt><dd class="font-bold text-rose-600">− {{ Money::format($contract->discount_amount) }}</dd></div> @endif
            @if ((float) $contract->tax_amount > 0) <div class="flex justify-between"><dt class="text-slate-500">الضريبة</dt><dd class="font-bold">+ {{ Money::format($contract->tax_amount) }}</dd></div> @endif
            <div class="flex justify-between border-t border-slate-100 pt-3 text-base"><dt class="font-bold text-slate-700">الإجمالي</dt><dd class="font-extrabold text-slate-900">{{ Money::format($contract->total_amount) }}</dd></div>
            @if ((float) $contract->down_payment > 0)
                <div class="flex justify-between"><dt class="text-slate-500">{{ $contract->payment_type === 'installments' ? 'المقدم' : 'المدفوع' }}</dt><dd class="font-bold text-emerald-600">{{ Money::format($contract->down_payment) }}</dd></div>
            @endif
        </dl>
        @if ($installments->isNotEmpty())
            @php $first = $installments->first(); $last = $installments->last(); @endphp
            <div class="mt-4 rounded-xl bg-blue-50 p-4 text-sm text-blue-900">
                <b>{{ $installments->count() }} قسط</b> — القسط {{ Money::format($first->amount) }}
                <div class="mt-1 text-xs text-blue-700">من {{ Carbon::parse($first->due_date)->format('Y-m-d') }} لحد {{ Carbon::parse($last->due_date)->format('Y-m-d') }}</div>
            </div>
        @endif
    </section>

    @if ($contract->terms_ar)
        <section class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="mb-2 font-extrabold text-slate-900">الشروط والأحكام</div>
            <div class="max-h-64 overflow-y-auto rounded-xl bg-slate-50 p-4 text-sm leading-loose text-slate-700">{{ \App\Support\RichText::render($contract->terms_ar, 'terms') }}</div>
        </section>
    @endif

    @if ($contract->status_code === 'cancelled')
        <div class="rounded-2xl bg-slate-200 p-5 text-center font-bold text-slate-600">العقد ده اتلغى.</div>
    @elseif ($signed)
        @unless (session('status'))
            <div class="rounded-2xl bg-emerald-50 p-5 text-center font-bold text-emerald-700 ring-1 ring-emerald-200">العقد ده اتوقّع بالفعل. شكراً لك.</div>
        @endunless
    @else
        <form method="POST" action="{{ url()->full() }}" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm"
              onsubmit="const b = this.querySelector('[type=submit]'); b.disabled = true; b.textContent = 'جاري الحفظ...';">
            @csrf
            @if ($errors->any())
                <div class="rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
            @endif

            @include('admin.partials.signature-pad', ['name' => 'signature_data', 'label' => 'توقيعك'])

            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50">
                <input type="checkbox" name="agree" value="1" class="mt-1 h-4 w-4 accent-emerald-600" required>
                <span class="text-sm text-slate-700">قرأت بيانات العقد والشروط وموافق عليها{{ $installments->isNotEmpty() ? '، وملتزم بسداد الأقساط في مواعيدها' : '' }}.</span>
            </label>

            <button type="submit" class="w-full rounded-2xl bg-emerald-600 py-4 text-base font-extrabold text-white shadow-lg hover:bg-emerald-700 disabled:opacity-60">وقّع وأكّد</button>
            <p class="text-center text-[11px] text-slate-400">الرابط ده خاص بيك ومش صالح غير لمدة محدودة.</p>
        </form>
    @endif
</main>
</body>
</html>
