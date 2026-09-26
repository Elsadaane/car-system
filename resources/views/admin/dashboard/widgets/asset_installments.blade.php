@php use App\Support\Money; @endphp
<a href="{{ route('admin.asset-installments.index', ['tab' => $d['overdue'] ? 'overdue' : 'upcoming']) }}" class="block">
    <div class="rounded-xl {{ $d['overdue'] ? 'bg-rose-600 text-white' : 'bg-slate-50' }} p-3"><div class="text-xs opacity-80">{{ __('🔴 متأخر') }}</div><div class="text-lg font-black">{{ $d['overdue'] }} · {{ Money::format($d['overdue_amount'], false) }}</div></div>
    <div class="mt-2 rounded-xl bg-violet-50 p-3 text-violet-800"><div class="text-xs">{{ __('⏰ خلال أسبوع') }}</div><div class="text-lg font-black">{{ $d['soon'] }} · {{ Money::format($d['soon_amount'], false) }}</div></div>
</a>
