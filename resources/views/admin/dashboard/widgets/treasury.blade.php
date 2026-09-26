@php use App\Support\Money; @endphp
<div class="mb-3 rounded-xl bg-slate-900 p-3 text-white"><div class="text-xs text-slate-400">{{ __('إجمالي الخزائن') }}</div><div class="truncate text-2xl font-black">{{ Money::format($d['total'], false) }}</div></div>
@foreach ($d['rows'] as $a)
    <a href="{{ route('admin.accounts.show', $a['id']) }}" class="flex justify-between py-1.5 text-sm hover:text-blue-700"><span class="truncate text-slate-600">{{ $a['name'] }}</span><span class="font-extrabold {{ $a['balance'] < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ Money::format($a['balance'], false) }}</span></a>
@endforeach
