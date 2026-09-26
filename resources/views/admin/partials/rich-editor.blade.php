{{--
    محرّر نصوص منسّقة (نفس محرّر المدونة، أخف):
    @include('admin.partials.rich-editor', ['name' => 'terms_ar', 'value' => $x, 'mode' => 'terms'])
    mode: full (صفحات الموقع) | terms (العقود المطبوعة — أدوات بسيطة بس)
--}}
@php
    $mode = $mode ?? 'full';
    $dir = $dir ?? 'rtl';
    $en = $dir === 'ltr';
    $tools = $mode === 'terms'
        ? [['h3', $en ? 'Title' : __('عنوان'), $en ? 'Heading' : __('عنوان صغير')], ['bold', '<b>B</b>', $en ? 'Bold' : __('بولد')], ['underline', '<u>U</u>', $en ? 'Underline' : __('خط تحت')], ['|'], ['ol', '1. ―', $en ? 'Numbered list' : __('بنود مرقّمة')], ['ul', '• ―', $en ? 'Bullets' : __('نقط')], ['|'], ['clear', '🧹', $en ? 'Clear formatting' : __('شيل التنسيق')], ['undo', '↶', $en ? 'Undo' : __('تراجع')]]
        : [['h2', 'H2', __('عنوان رئيسي')], ['h3', 'H3', __('عنوان فرعي')], ['p', '¶', __('فقرة عادية')], ['|'], ['bold', '<b>B</b>', __('بولد')], ['italic', '<i>I</i>', __('مائل')], ['underline', '<u>U</u>', __('خط تحت')], ['|'], ['ul', '• ―', __('نقط')], ['ol', '1. ―', __('أرقام')], ['quote', '❝', __('اقتباس')], ['|'], ['link', '🔗', __('رابط')], ['unlink', '⛓️‍💥', __('شيل الرابط')], ['hr', '―', __('خط فاصل')], ['|'], ['clear', '🧹', __('شيل التنسيق')], ['undo', '↶', __('تراجع')]];
    $html = \App\Support\RichText::toEditor($value ?? '');
    $invalid = isset($errors) && $errors->has($name);
@endphp
<div class="overflow-hidden rounded-xl border bg-white {{ $invalid ? 'border-rose-300' : 'border-slate-200' }} focus-within:border-blue-400 focus-within:ring-4 focus-within:ring-blue-50" data-rich-wrap data-mode="{{ $mode }}">
    <div class="flex flex-wrap items-center gap-0.5 border-b border-slate-100 bg-slate-50/70 p-1.5">
        @foreach ($tools as $b)
            @if ($b[0] === '|')
                <span class="mx-1 h-5 w-px bg-slate-200"></span>
            @else
                <button type="button" data-rcmd="{{ $b[0] }}" title="{{ $b[2] }}" tabindex="-1" class="grid h-8 min-w-8 place-items-center rounded-lg px-2 text-xs font-bold text-slate-600 hover:bg-white hover:shadow-sm">{!! $b[1] !!}</button>
            @endif
        @endforeach
    </div>
    <div contenteditable="true" dir="{{ $dir }}" data-rich-editor data-placeholder="{{ $placeholder ?? ($en ? 'Write or paste here…' : __('اكتب هنا… أو الصق من Word / جوجل دوكس / ChatGPT وهيتنضّف لوحده')) }}"
         class="rich-text rich-editor max-h-[65vh] overflow-y-auto px-4 py-3 text-sm text-slate-800 outline-none" style="min-height: {{ $minHeight ?? '12rem' }}">{!! $html !!}</div>
    <textarea name="{{ $name }}" @isset($id) id="{{ $id }}" @endisset class="hidden" data-rich-input>{{ $html }}</textarea>
</div>

@once
    <style>
        .rich-editor:empty::before { content: attr(data-placeholder); color: #94a3b8; }
        .rich-editor ol { list-style: decimal; padding-inline-start: 1.6em; }
        .rich-editor ul { list-style: disc; padding-inline-start: 1.6em; }
        .rich-editor h2 { font-size: 1.25em; font-weight: 900; }
        .rich-editor h3 { font-size: 1.05em; font-weight: 800; }
        .rich-editor p, .rich-editor li { margin: .25em 0; }
        .rich-editor blockquote { border-inline-start: 3px solid #cbd5e1; padding-inline-start: .8em; color: #475569; }
        .rich-editor a { color: #2563eb; text-decoration: underline; }
    </style>
    <script>
    (() => {
        const FULL = { H2: [], H3: [], H4: [], P: [], BR: [], HR: [], STRONG: [], EM: [], U: [], UL: [], OL: [], LI: [], BLOCKQUOTE: [], A: ['href'] };
        const TERMS = { H3: [], H4: [], P: [], BR: [], STRONG: [], U: [], EM: [], UL: [], OL: [], LI: [] };
        const RENAME = { B: 'STRONG', I: 'EM', H1: 'H2', H5: 'H4', H6: 'H4', DIV: 'P', SECTION: 'P', ARTICLE: 'P' };
        const DROP = ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'FORM', 'INPUT', 'BUTTON', 'SVG', 'META', 'LINK', 'TITLE', 'NOSCRIPT', 'TEMPLATE', 'IMG', 'TABLE', 'O:P'];
        const esc = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

        const clean = (node, allowed) => {
            [...node.childNodes].forEach((ch) => {
                if (ch.nodeType === 8) { ch.remove(); return; }
                if (ch.nodeType !== 1) return;
                let tag = ch.tagName.toUpperCase();
                if (DROP.includes(tag)) { ch.remove(); return; }
                const style = ch.getAttribute('style') || '';
                if (tag === 'SPAN' && /font-weight:\s*(700|bold)/i.test(style)) tag = 'STRONG';
                if ((tag === 'B' || tag === 'STRONG') && (/font-weight:\s*(normal|400)/i.test(style) || (ch.id || '').startsWith('docs-internal'))) tag = 'SPAN';
                tag = RENAME[tag] || tag;
                if (tag === 'H2' && !allowed.H2) tag = 'H3';
                const parentTag = node.tagName ? node.tagName.toUpperCase() : '';
                if (tag === 'P' && ['LI', 'BLOCKQUOTE'].includes(parentTag)) tag = 'SPAN';
                if (['STRONG', 'EM', 'U'].includes(tag) && ch.querySelector('p,h1,h2,h3,h4,ul,ol,li,div,blockquote')) tag = 'SPAN';
                if (!allowed[tag]) { clean(ch, allowed); ch.replaceWith(...ch.childNodes); return; }
                let el = ch;
                if (tag !== ch.tagName.toUpperCase()) { el = document.createElement(tag); [...ch.attributes].forEach((a) => el.setAttribute(a.name, a.value)); el.append(...ch.childNodes); ch.replaceWith(el); }
                [...el.attributes].forEach((a) => { if (!allowed[tag].includes(a.name)) el.removeAttribute(a.name); });
                if (tag === 'A') {
                    let href = el.getAttribute('href') || '';
                    const g = href.match(/^https?:\/\/(www\.)?google\.[a-z.]+\/url\?(.*)$/i);
                    if (g) href = new URLSearchParams(g[2]).get('q') || '';
                    if (!/^(https?:\/\/|\/|#|mailto:|tel:)/i.test(href.trim())) el.removeAttribute('href'); else el.setAttribute('href', href);
                }
                clean(el, allowed);
            });
            return node;
        };

        // نص عادي ← HTML: كل سطر فقرة، "1." أو "١-" بنود مرقّمة، "- " نقط، "## " عنوان
        const fromText = (text, full) => {
            let out = '', list = null;
            const inline = (s) => esc(s).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            const close = () => { if (list) { out += '</' + list + '>'; list = null; } };
            text.split(/\r?\n/).forEach((l) => {
                const t = l.trim(); let m;
                if (!t) { close(); return; }
                if ((m = t.match(/^#{1,6}\s+(.*)/))) { close(); out += (full && t.startsWith('## ') ? '<h2>' : '<h3>') + inline(m[1]) + (full && t.startsWith('## ') ? '</h2>' : '</h3>'); }
                else if ((m = t.match(/^[-*•]\s+(.*)/))) { if (list !== 'ul') { close(); out += '<ul>'; list = 'ul'; } out += '<li>' + inline(m[1]) + '</li>'; }
                else if ((m = t.match(/^([0-9]+|[٠-٩]+)\s*[-.)]\s*(.*)/))) { if (list !== 'ol') { close(); out += '<ol>'; list = 'ol'; } out += '<li>' + inline(m[2]) + '</li>'; }
                else { close(); out += '<p>' + inline(t) + '</p>'; }
            });
            close();
            return out;
        };
        // ترتيب البلوكات: أي نص سايب يتلف في <p>، و<p> اللي جواها قايمة/عنوان يتفك
        const BLOCK = ['P', 'H2', 'H3', 'H4', 'UL', 'OL', 'BLOCKQUOTE', 'HR'];
        const normalize = (root) => {
            root.querySelectorAll('p').forEach((p) => { if ([...p.children].some((c) => BLOCK.includes(c.tagName))) p.replaceWith(...p.childNodes); });
            let run = [];
            const flush = (before) => {
                if (run.length && run.some((n) => (n.textContent || '').trim())) { const p = document.createElement('p'); root.insertBefore(p, before); run.forEach((n) => p.appendChild(n)); }
                else run.forEach((n) => n.remove());
                run = [];
            };
            [...root.childNodes].forEach((n) => { if (n.nodeType === 1 && BLOCK.includes(n.tagName)) flush(n); else run.push(n); });
            flush(null);
            return root;
        };
        const isHtml = (s) => /<(p|br|ul|ol|li|h[2-4]|strong|em|u|blockquote|a|hr)\b[^>]*>/i.test(s || '');

        const init = (wrap) => {
            if (wrap.dataset.ready) return;
            wrap.dataset.ready = '1';
            const ed = wrap.querySelector('[data-rich-editor]'), ta = wrap.querySelector('[data-rich-input]');
            const full = wrap.dataset.mode !== 'terms', allowed = full ? FULL : TERMS;
            const sync = () => {
                const html = normalize(clean(ed.cloneNode(true), allowed)).innerHTML.replace(/<p>(\s|&nbsp;|<br>)*<\/p>/g, '').replace(/<br><\/(p|li|h[2-4])>/g, '</$1>').trim();
                ta.value = ed.innerText.trim() === '' ? '' : html;
                if (!ed.innerText.trim()) ed.innerHTML = '';
            };
            ed.addEventListener('focus', () => document.execCommand('defaultParagraphSeparator', false, 'p'));
            ed.addEventListener('input', sync);
            ed.addEventListener('blur', sync);
            ed.addEventListener('paste', (e) => {
                e.preventDefault();
                const html = e.clipboardData.getData('text/html');
                let out;
                if (html) {
                    const box = document.createElement('div');
                    box.innerHTML = new DOMParser().parseFromString(html, 'text/html').body.innerHTML;
                    out = clean(box, allowed).innerHTML.replace(/<p>(\s|&nbsp;|<br>)*<\/p>/g, '');
                } else {
                    out = fromText(e.clipboardData.getData('text/plain'), full);
                }
                document.execCommand('insertHTML', false, out);
                sync();
            });
            wrap.querySelectorAll('[data-rcmd]').forEach((b) => b.addEventListener('mousedown', (e) => {
                e.preventDefault();
                if (document.activeElement !== ed) ed.focus();
                const c = b.dataset.rcmd;
                if (['h2', 'h3', 'p'].includes(c)) document.execCommand('formatBlock', false, document.queryCommandValue('formatBlock').toUpperCase() === c.toUpperCase() ? 'P' : c.toUpperCase());
                else if (c === 'quote') document.execCommand('formatBlock', false, 'BLOCKQUOTE');
                else if (c === 'ul') document.execCommand('insertUnorderedList');
                else if (c === 'ol') document.execCommand('insertOrderedList');
                else if (c === 'link') { const u = prompt(@js(__('الرابط (مثلاً /cars أو https://...):')), 'https://'); if (u) document.execCommand('createLink', false, u); }
                else if (c === 'hr') document.execCommand('insertHorizontalRule');
                else if (c === 'clear') { document.execCommand('removeFormat'); document.execCommand('formatBlock', false, 'P'); }
                else document.execCommand(c);
                sync();
            }));
            // حد تاني غيّر القيمة (زرار "النص الافتراضي" مثلاً)
            ta.addEventListener('rich:load', () => { ed.innerHTML = isHtml(ta.value) ? clean(Object.assign(document.createElement('div'), { innerHTML: ta.value }), allowed).innerHTML : fromText(ta.value, full); sync(); });
            ta.closest('form')?.addEventListener('submit', sync);
        };

        const boot = () => document.querySelectorAll('[data-rich-wrap]').forEach(init);
        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
        window.RichEditor = { boot };
    })();
    </script>
@endonce
