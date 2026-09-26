<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

/**
 * المدونة: تنضيف HTML المقال (حماية من XSS + تنضيف اللصق)، الفهرس، وقت القراءة، والرابط.
 */
class Blog
{
    /** التاجات المسموحة و الـ attributes بتاعتها */
    private const ALLOWED = [
        'h2' => [], 'h3' => [], 'h4' => [], 'p' => [], 'br' => [], 'hr' => [],
        'strong' => [], 'em' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [], 'mark' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'a' => ['href', 'title'], 'img' => ['src', 'alt', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [], 'figure' => [], 'figcaption' => [],
    ];

    /** تاجات بتتحوّل لتاجات تانية (جاية من جوجل دوكس / الوورد) */
    private const RENAME = ['b' => 'strong', 'i' => 'em', 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4', 'div' => 'p', 'section' => 'p', 'article' => 'p'];

    /** بتتشال بمحتواها كله */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'meta', 'link', 'title', 'head', 'noscript', 'template'];

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();

        $root = $doc->getElementById('__root');
        if (! $root) {
            return e(strip_tags($html));
        }
        self::clean($root, $doc);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }
        // فقرات فاضية ومسافات زيادة من اللصق
        $out = preg_replace(['#<p>(\s|&nbsp;|<br\s*/?>)*</p>#u', '#(<br\s*/?>\s*){3,}#u'], ['', '<br><br>'], $out);

        return trim($out);
    }

    private static function clean(DOMNode $node, DOMDocument $doc): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }

            $style = $child->getAttribute('style');
            // جوجل دوكس بيحط البولد في span بستايل، وبيلف الصفحة كلها في <b style="font-weight:normal">
            if ($tag === 'span' && preg_match('/font-weight:\s*(700|bold)/i', $style)) {
                $tag = 'strong';
            }
            if (in_array($tag, ['b', 'strong'], true) && (preg_match('/font-weight:\s*(normal|400)/i', $style) || str_starts_with($child->getAttribute('id'), 'docs-internal'))) {
                $tag = 'span';
            }
            $tag = self::RENAME[$tag] ?? $tag;
            // <p> جوه <li> أو تاج بلوك جوه بولد: بنسيب المحتوى بس
            $parent = $node instanceof DOMElement ? strtolower($node->tagName) : '';
            if (($tag === 'p' && in_array($parent, ['li', 'td', 'th', 'blockquote'], true))
                || (in_array($tag, ['strong', 'em', 'u'], true) && self::hasBlock($child))) {
                $tag = 'span';
            }

            if (! isset(self::ALLOWED[$tag])) {
                // تاج مش مسموح (span, font, ...): نسيب محتواه ونشيله هو
                self::clean($child, $doc);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            if ($tag !== strtolower($child->tagName)) {
                $new = $doc->createElement($tag);
                foreach (iterator_to_array($child->attributes) as $a) {
                    $new->setAttribute($a->name, $a->value);
                }
                while ($child->firstChild) {
                    $new->appendChild($child->firstChild);
                }
                $node->replaceChild($new, $child);
                $child = $new;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                if (! in_array($attr->name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attr->name);
                }
            }
            // روابط جوجل المتغلفة: google.com/url?q=الرابط-الحقيقي
            if ($tag === 'a' && preg_match('#^https?://(www\.)?google\.[a-z.]+/url\?#i', $child->getAttribute('href'))) {
                parse_str((string) parse_url($child->getAttribute('href'), PHP_URL_QUERY), $q);
                $child->setAttribute('href', (string) ($q['q'] ?? $q['url'] ?? ''));
            }
            foreach (['href', 'src'] as $urlAttr) {
                if ($child->hasAttribute($urlAttr) && ! self::safeUrl($child->getAttribute($urlAttr))) {
                    $child->removeAttribute($urlAttr);
                }
            }
            if ($tag === 'a' && $child->hasAttribute('href') && self::isExternal($child->getAttribute('href'))) {
                $child->setAttribute('target', '_blank');
                $child->setAttribute('rel', 'noopener nofollow');
            }
            if ($tag === 'img') {
                if (! $child->hasAttribute('src')) {
                    $node->removeChild($child);
                    continue;
                }
                $child->setAttribute('loading', 'lazy');
            }

            self::clean($child, $doc);
        }
    }

    private static function hasBlock(DOMElement $el): bool
    {
        foreach ($el->getElementsByTagName('*') as $d) {
            if (in_array(strtolower($d->tagName), ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'div', 'table', 'blockquote'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim(html_entity_decode($url));

        return $url !== '' && (bool) preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $url) && ! preg_match('#^\s*(javascript|data|vbscript):#i', $url);
    }

    private static function isExternal(string $href): bool
    {
        return (bool) preg_match('#^https?://#i', $href) && parse_url($href, PHP_URL_HOST) !== request()->getHost();
    }

    /** بيدي كل عنوان H2/H3 id، ويرجّع [html, فهرس] */
    public static function withToc(string $html): array
    {
        $toc = [];
        $used = [];
        $html = preg_replace_callback('#<(h2|h3)>(.*?)</\1>#su', function ($m) use (&$toc, &$used) {
            $text = trim(html_entity_decode(strip_tags($m[2])));
            $id = self::slugify($text) ?: 'section';
            $base = $id;
            $n = 2;
            while (isset($used[$id])) {
                $id = $base.'-'.$n++;
            }
            $used[$id] = true;
            $toc[] = ['level' => (int) substr($m[1], 1), 'text' => $text, 'id' => $id];

            return '<'.$m[1].' id="'.e($id).'">'.$m[2].'</'.$m[1].'>';
        }, $html);

        return [$html, $toc];
    }

    public static function readingMinutes(?string $html): int
    {
        $words = count(preg_split('/\s+/u', trim(strip_tags((string) $html)), -1, PREG_SPLIT_NO_EMPTY));

        return max(1, (int) ceil($words / 200));
    }

    public static function wordCount(?string $html): int
    {
        return count(preg_split('/\s+/u', trim(strip_tags((string) $html)), -1, PREG_SPLIT_NO_EMPTY));
    }

    /** رابط بيقبل العربي (أحسن للـ SEO في السوق العربي) */
    public static function slugify(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $text); // تشكيل وتطويل
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);

        return Str::limit(trim($text, '-'), 90, '');
    }

    /** الحقل باللغة الحالية ولو فاضي يرجع للعربي */
    public static function t(object $row, string $field): ?string
    {
        if (app()->getLocale() === 'en' && filled($row->{$field.'_en'} ?? null)) {
            return $row->{$field.'_en'};
        }

        return $row->{$field.'_ar'} ?? null;
    }
}
