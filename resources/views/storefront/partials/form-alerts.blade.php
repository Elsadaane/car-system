@if (session('inquiry_success'))
    <div class="mb-4 flex items-start gap-3 rounded-2xl bg-emerald-50 px-4 py-3.5 text-sm font-bold text-emerald-800 ring-1 ring-emerald-200">
        <span class="text-xl">✅</span><span>{{ session('inquiry_success') }}</span>
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">
        @foreach ($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
    </div>
@endif
