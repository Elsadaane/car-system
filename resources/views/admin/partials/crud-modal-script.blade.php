{{--
    سكريبت موحّد لمودال الإضافة والتعديل.
    المودال:  <dialog data-crud data-store-url="..." data-title-new="..." data-title-edit="...">
    زرار جديد: <button data-crud-new="idOfDialog">
    زرار تعديل: <button data-crud-edit="idOfDialog" data-values='{"url": "...", "name_ar": "...", "_image": "..."}'>
    الحقول بتتملى بالاسم، و data-default بيحدد القيمة الافتراضية في الإضافة.
--}}
<script>
(function () {
    document.querySelectorAll('dialog[data-crud]').forEach((dlg) => {
        const form = dlg.querySelector('form');
        const method = form.querySelector('[name=_method]');
        const editUrl = form.querySelector('[name=_edit_url]');
        const title = dlg.querySelector('[data-crud-title]');
        const preview = dlg.querySelector('[data-crud-preview]');
        const skip = ['_token', '_method', '_modal', '_edit_url'];
        const fields = () => [...form.elements].filter((el) => el.name && !skip.includes(el.name) && el.type !== 'hidden' && el.type !== 'file' && el.type !== 'submit');

        const setValue = (el, v) => {
            if (el.type === 'checkbox') el.checked = v === true || v === 1 || v === '1';
            else el.value = v ?? '';
            el.dispatchEvent(new Event('change'));
        };

        const setMode = (editing, url) => {
            form.action = editing ? url : dlg.dataset.storeUrl;
            method.value = editing ? 'PUT' : 'POST';
            editUrl.value = editing ? url : '';
            title.textContent = editing ? dlg.dataset.titleEdit : dlg.dataset.titleNew;
            dlg.querySelectorAll('[data-edit-only]').forEach((el) => el.classList.toggle('hidden', !editing));
            dlg.querySelectorAll('[data-error]').forEach((el) => el.remove());
            form.querySelectorAll('input[type=file]').forEach((el) => { el.value = ''; });
        };

        const showPreview = (src) => {
            if (!preview) return;
            preview.classList.toggle('hidden', !src);
            preview.querySelector('img').src = src || '';
        };

        document.querySelectorAll(`[data-crud-new="${dlg.id}"]`).forEach((b) => b.addEventListener('click', () => {
            setMode(false);
            fields().forEach((el) => setValue(el, el.dataset.default ?? (el.type === 'checkbox' ? '1' : '')));
            showPreview(null);
            dlg.showModal();
            fields()[0]?.focus();
        }));

        document.querySelectorAll(`[data-crud-edit="${dlg.id}"]`).forEach((b) => b.addEventListener('click', () => {
            const values = JSON.parse(b.dataset.values);
            setMode(true, values.url);
            fields().forEach((el) => setValue(el, values[el.name] ?? (el.type === 'checkbox' ? false : '')));
            showPreview(values._image || null);
            dlg.showModal();
        }));

        dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
        dlg.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => dlg.close()));
        form.addEventListener('submit', () => {
            const s = form.querySelector('[type=submit]'); s.disabled = true; s.textContent = @js(__('جاري الحفظ...'));
        });

        // لو رجع بأخطاء: افتحه تاني بنفس البيانات
        if (dlg.dataset.reopen) {
            const url = dlg.dataset.reopen === 'new' ? null : dlg.dataset.reopen;
            form.action = url || dlg.dataset.storeUrl;
            method.value = url ? 'PUT' : 'POST';
            editUrl.value = url || '';
            title.textContent = url ? dlg.dataset.titleEdit : dlg.dataset.titleNew;
            dlg.querySelectorAll('[data-edit-only]').forEach((el) => el.classList.toggle('hidden', !url));
            dlg.showModal();
        }
    });

    // ?new=1 في الرابط (من السايدبار): افتح نافذة الإضافة على طول
    const params = new URLSearchParams(location.search);
    if (params.has('new')) {
        const first = document.querySelector('[data-crud-new]');
        if (first && !document.querySelector('dialog[open]')) first.click();
        params.delete('new');
        history.replaceState(null, '', location.pathname + (params.toString() ? '?' + params : ''));
    }
})();
</script>
