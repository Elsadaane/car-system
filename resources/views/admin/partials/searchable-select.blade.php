{{--
    بحث جوه القوايم (بديل select2 من غير jQuery):
    - أي <select> فيه أكتر من 7 اختيارات بيتحوّل لوحده لقايمة فيها بحث.
    - data-search  ← يجبره يتحوّل حتى لو اختياراته قليلة
    - data-no-search ← يمنعه يتحوّل
    البحث بيتجاهل الفرق بين أ/إ/آ/ا و ة/ه و ى/ي.
    الـ select الأصلي بيفضل موجود (مخفي) فالفورم والـ validation شغالين زي ما هم.
--}}
<style>
    .ss-wrap { position: relative; }
    .ss-native { position: absolute !important; inset: auto 0 0 0; width: 100% !important; height: 1px !important; opacity: 0 !important; pointer-events: none; }
    .ss-btn { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: .5rem; text-align: start; }
    .ss-btn span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ss-panel { position: absolute; z-index: 60; inset-inline: 0; margin-top: .35rem; overflow: hidden; border-radius: .9rem; background: #fff; box-shadow: 0 20px 40px -12px rgb(15 23 42 / .25); outline: 1px solid rgb(226 232 240); }
    .ss-panel.ss-up { bottom: 100%; margin-bottom: .35rem; }
    .ss-list { max-height: 16rem; overflow-y: auto; padding: .3rem; }
    .ss-opt { cursor: pointer; border-radius: .6rem; padding: .5rem .7rem; font-size: .875rem; color: rgb(51 65 85); }
    .ss-opt[aria-selected=true] { background: rgb(239 246 255); color: rgb(29 78 216); font-weight: 700; }
    .ss-opt.ss-active { background: rgb(241 245 249); }
    .ss-opt mark { background: rgb(254 240 138); color: inherit; border-radius: .2rem; }
    .ss-empty { padding: 1rem; text-align: center; font-size: .8rem; color: rgb(148 163 184); }
</style>
<script>
(function () {
    const norm = (s) => (s || '').toLowerCase()
        .replace(/[أإآٱ]/g, @js(__('ا'))).replace(/ة/g, @js(__('ه'))).replace(/ى/g, @js(__('ي'))).replace(/[ً-ْ]/g, '').trim();
    const esc = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    const enhance = (select) => {
        if (select.dataset.ssReady || select.multiple || select.hasAttribute('data-no-search')) return;
        if (!select.hasAttribute('data-search') && select.options.length <= 7) return;
        select.dataset.ssReady = '1';

        const wrap = document.createElement('div');
        wrap.className = 'ss-wrap';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.classList.add('ss-native');
        select.tabIndex = -1;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = select.className.replace('ss-native', '') + ' ss-btn';
        btn.innerHTML = '<span></span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="flex-shrink:0;opacity:.5"><path d="m6 9 6 6 6-6"/></svg>';
        wrap.insertBefore(btn, select);

        const panel = document.createElement('div');
        panel.className = 'ss-panel';
        panel.hidden = true;
        panel.innerHTML = @js(__('<div style="padding:.5rem;border-bottom:1px solid rgb(241 245 249)"><input type="search" placeholder="اكتب للبحث..." autocomplete="off" style="width:100%;border-radius:.6rem;border:1px solid rgb(226 232 240);padding:.5rem .7rem;font-size:.875rem;outline:none"></div><div class="ss-list" role="listbox"></div>'));
        wrap.appendChild(panel);
        const input = panel.querySelector('input'), list = panel.querySelector('.ss-list');
        let active = -1, items = [];

        const label = () => {
            const o = select.selectedOptions[0];
            btn.querySelector('span').textContent = o ? o.textContent.trim() : '';
            btn.querySelector('span').style.color = !o || o.value === '' ? 'rgb(148 163 184)' : '';
        };

        const render = () => {
            const q = norm(input.value), words = q.split(/\s+/).filter(Boolean);
            items = [...select.options].filter((o) => !o.hidden && !o.disabled && (!words.length || words.every((w) => norm(o.textContent).includes(w))));
            list.innerHTML = items.length ? '' : @js(__('<div class="ss-empty">مفيش نتايج</div>'));
            items.forEach((o, i) => {
                const d = document.createElement('div');
                d.className = 'ss-opt';
                d.setAttribute('role', 'option');
                d.setAttribute('aria-selected', o.selected && o.value !== '' ? 'true' : 'false');
                const text = o.textContent.trim();
                // تمييز أول كلمة بحث في النص الأصلي
                let html = esc(text);
                if (words[0]) {
                    const n = norm(text), at = n.indexOf(words[0]);
                    if (at >= 0 && n.length === text.length) html = esc(text.slice(0, at)) + '<mark>' + esc(text.slice(at, at + words[0].length)) + '</mark>' + esc(text.slice(at + words[0].length));
                }
                d.innerHTML = html;
                d.addEventListener('mousedown', (e) => { e.preventDefault(); choose(o); });
                list.appendChild(d);
            });
            active = items.findIndex((o) => o.selected);
            highlight();
        };

        const highlight = () => {
            [...list.children].forEach((c, i) => c.classList.toggle('ss-active', i === active));
            list.children[active]?.scrollIntoView({ block: 'nearest' });
        };

        const open = () => {
            document.querySelectorAll('.ss-panel').forEach((p) => { if (p !== panel) p.hidden = true; });
            input.value = '';
            render();
            panel.hidden = false;
            const r = btn.getBoundingClientRect();
            panel.classList.toggle('ss-up', window.innerHeight - r.bottom < 300 && r.top > 300);
            input.focus();
        };
        const close = () => { panel.hidden = true; };
        const choose = (o) => {
            select.value = o.value;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            close();
            btn.focus();
        };

        btn.addEventListener('click', () => (panel.hidden ? open() : close()));
        btn.addEventListener('keydown', (e) => { if (['ArrowDown', 'Enter', ' '].includes(e.key)) { e.preventDefault(); open(); } });
        input.addEventListener('input', render);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(items.length - 1, active + 1); highlight(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(0, active - 1); highlight(); }
            else if (e.key === 'Enter') { e.preventDefault(); if (items[active]) choose(items[active]); }
            else if (e.key === 'Escape') { e.preventDefault(); close(); btn.focus(); }
        });
        document.addEventListener('mousedown', (e) => { if (!wrap.contains(e.target)) close(); });

        // لو الكود غيّر القيمة أو ضاف اختيارات
        select.addEventListener('change', label);
        new MutationObserver(label).observe(select, { childList: true, subtree: true, attributes: true });
        select.addEventListener('invalid', () => { btn.classList.add('ss-invalid'); btn.style.borderColor = 'rgb(253 164 175)'; });
        label();
    };

    const boot = (root = document) => root.querySelectorAll('select').forEach(enhance);
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', () => boot()) : boot();
    window.enhanceSelects = boot;
})();
</script>
