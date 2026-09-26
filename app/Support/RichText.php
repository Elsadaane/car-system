<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * النصوص المنسّقة (المحرّر): شروط العقود وسياسات الموقع.
 *  - full:  عناوين، قوائم، لينكات، اقتباس — لصفحات الموقع
 *  - terms: عناوين صغيرة، بولد، خط تحت، قوائم — للعقود المطبوعة (بدون صور وجداول وألوان تبوّظ الورقة)
 * النصوص القديمة المكتوبة نص عادي بتفضل تتعرض زي ما هي (كل سطر في سطره).
 */
class RichText
{
    private const TERMS_TAGS = '<h3><h4><p><br><strong><em><u><ul><ol><li>';

    /** فيه تنسيق HTML ولا نص عادي؟ */
    public static function isHtml(?string $value): bool
    {
        return (bool) preg_match('#<(p|br|ul|ol|li|h[2-4]|strong|em|u|blockquote|a|hr)\b[^>]*>#i', (string) $value);
    }

    /** تنضيف قبل الحفظ (حماية من أي كود ضار جاي من اللصق أو من برّه) */
    public static function clean(?string $value, string $mode = 'full'): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! self::isHtml($value)) {
            return $value; // نص عادي — يفضل زي ما هو (وبيتعرض escaped)
        }
        $html = Blog::sanitize($value);
        if ($mode === 'terms') {
            $html = strip_tags(str_replace(['<h2>', '</h2>'], ['<h3>', '</h3>'], $html), self::TERMS_TAGS);
        } else {
            $html = preg_replace('#<img\b[^>]*>#i', '', $html); // الصور مكانها المدونة
        }
        $html = trim(preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#u', '', $html));

        return trim(strip_tags($html)) === '' ? null : $html;
    }

    /** العرض: HTML منضّف، أو نص عادي بسطوره */
    public static function render(?string $value, string $mode = 'full'): HtmlString
    {
        $value = (string) $value;
        if (trim($value) === '') {
            return new HtmlString('');
        }
        $body = self::isHtml($value) ? (string) self::clean($value, $mode) : nl2br(e(trim($value)), false);

        return new HtmlString(self::style().'<div class="rich-text">'.$body.'</div>');
    }

    /** نص عادي (للواتساب والملخصات) */
    public static function toText(?string $value): string
    {
        $v = (string) $value;
        if (! self::isHtml($v)) {
            return trim($v);
        }
        $v = preg_replace(['#<li[^>]*>#i', '#</(p|h[2-4]|li|blockquote)>#i', '#<br\s*/?>#i'], ['• ', "\n", "\n"], $v);

        return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($v), ENT_QUOTES, 'UTF-8')));
    }

    /** نص قديم عادي ← HTML للمحرّر (كل سطر فقرة، والسطور المرقّمة قايمة) */
    public static function toEditor(?string $value): string
    {
        $v = trim((string) $value);
        if ($v === '' || self::isHtml($v)) {
            return self::isHtml($v) ? (string) self::clean($v) : '';
        }
        $out = '';
        $list = null;
        $close = function () use (&$out, &$list) {
            if ($list) {
                $out .= "</{$list}>";
                $list = null;
            }
        };
        foreach (preg_split('/\r?\n/', $v) as $line) {
            $t = trim($line);
            if ($t === '') {
                $close();

                continue;
            }
            if (preg_match('/^(\d+|[٠-٩]+)\s*[-.)]\s*(.+)$/u', $t, $m)) {
                if ($list !== 'ol') {
                    $close();
                    $out .= '<ol>';
                    $list = 'ol';
                }
                $out .= '<li>'.e($m[2]).'</li>';
            } elseif (preg_match('/^[-•*]\s+(.+)$/u', $t, $m)) {
                if ($list !== 'ul') {
                    $close();
                    $out .= '<ul>';
                    $list = 'ul';
                }
                $out .= '<li>'.e($m[1]).'</li>';
            } else {
                $close();
                $out .= '<p>'.e($t).'</p>';
            }
        }
        $close();

        return $out;
    }

    /** الشكل (مرة واحدة في الصفحة) — شغال في الطباعة والموقع ولوحة التحكم */
    private static function style(): string
    {
        $key = 'richtext.styled.'.spl_object_id(request());
        if (app()->bound($key)) {
            return '';
        }
        app()->instance($key, true);

        return '<style>'
            .'.rich-text{line-height:1.95}'
            .'.rich-text>*+*{margin-top:.55em}'
            .'.rich-text h2{font-size:1.3em;font-weight:900;color:#0f172a;margin-top:1.2em}'
            .'.rich-text h3{font-size:1.08em;font-weight:800;color:#0f172a;margin-top:1em}'
            .'.rich-text h4{font-weight:800;color:#0f172a}'
            .'.rich-text ol{list-style:decimal;padding-inline-start:1.6em}'
            .'.rich-text ul{list-style:disc;padding-inline-start:1.6em}'
            .'.rich-text li{margin:.2em 0}.rich-text li::marker{font-weight:700;color:#475569}'
            .'.rich-text strong{font-weight:800;color:#0f172a}.rich-text u{text-underline-offset:3px}'
            .'.rich-text a{color:#2563eb;text-decoration:underline}'
            .'.rich-text blockquote{border-inline-start:3px solid #cbd5e1;padding-inline-start:.9em;color:#475569}'
            .'.rich-text hr{border:0;border-top:1px solid #e2e8f0;margin:1.2em 0}'
            .'@media print{.rich-text li,.rich-text p{break-inside:avoid}}'
            .'</style>';
    }
}
