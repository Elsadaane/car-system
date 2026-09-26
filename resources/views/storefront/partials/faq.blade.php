@if ($faqs->isNotEmpty())
    <div class="space-y-3">
        @foreach ($faqs as $f)
            <details class="group rounded-2xl bg-white ring-1 ring-slate-200 open:ring-slate-300 open:shadow-sm" @if ($loop->first) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-extrabold text-slate-900">
                    {{ \App\Support\Lang::pick($f, 'title') }}
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-45 group-open:bg-[var(--accent)] group-open:text-[var(--accent-ink)]">+</span>
                </summary>
                <div class="px-5 pb-5 text-sm leading-relaxed text-slate-600 whitespace-pre-line">{{ \App\Support\Lang::pick($f, 'body') }}</div>
            </details>
        @endforeach
    </div>
    @push('head')
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqs->map(fn ($f) => ['@type' => 'Question', 'name' => \App\Support\Lang::pick($f, 'title'), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => \App\Support\Lang::pick($f, 'body')]])->values()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endpush
@endif
