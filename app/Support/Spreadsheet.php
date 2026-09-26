<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * قراءة وكتابة ملفات Excel (xlsx) و CSV من غير أي مكتبة خارجية.
 *  - xlsx أصلاً ملف zip جواه XML — بنقرا أول شيت بس
 *  - CSV: بيتعرّف على الفاصلة (, أو ; أو Tab) وعلى ترميز Windows العربي
 */
class Spreadsheet
{
    public const MAX_ROWS = 5000;

    /** @return array<int, array<int, string>> صفوف (أول صف = العناوين) */
    public static function read(string $path, string $extension): array
    {
        $rows = strtolower($extension) === 'xlsx' ? self::readXlsx($path) : self::readCsv($path);

        // نشيل الصفوف الفاضية والأعمدة الفاضية في الآخر
        $rows = array_values(array_filter($rows, fn ($r) => implode('', array_map('trim', $r)) !== ''));
        $width = 0;
        foreach ($rows as $r) {
            for ($i = count($r) - 1; $i >= 0; $i--) {
                if (trim((string) $r[$i]) !== '') {
                    $width = max($width, $i + 1);
                    break;
                }
            }
        }

        return array_map(fn ($r) => array_map(fn ($v) => trim((string) $v), array_pad(array_slice($r, 0, $width), $width, '')), $rows);
    }

    private static function readCsv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = self::fromWindowsArabic($raw); // CSV محفوظ من Excel العربي القديم
        }
        $first = strtok($raw, "\n") ?: '';
        $delim = collect([',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")])->sortDesc()->keys()->first();

        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw);
        rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $delim, '"', '')) !== false && count($rows) <= self::MAX_ROWS) {
            $rows[] = $r;
        }
        fclose($h);

        return $rows;
    }

    /** Windows-1256 ← UTF-8 (بـ iconv لو موجود، وإلا بجدول التحويل) */
    private static function fromWindowsArabic(string $raw): string
    {
        if (function_exists('iconv') && ($out = @iconv('CP1256', 'UTF-8//IGNORE', $raw)) !== false) {
            return $out;
        }
        $map = mb_str_split('€پ‚ƒ„…†‡ˆ‰ٹ‹Œچژڈگ‘’“”•–—ک™ڑ›œ‌‍ں ،¢£¤¥¦§¨©ھ«¬­®¯°±²³´µ¶·¸¹؛»¼½¾؟ہءآأؤإئابةتثجحخدذرزسشصض×طظعغـفقكàلâمنهوçèéêëىيîïًٌٍَôُِ÷ّùْûü‎‏ے');
        $out = '';
        foreach (str_split($raw) as $ch) {
            $o = ord($ch);
            $out .= $o < 0x80 ? $ch : $map[$o - 0x80];
        }

        return $out;
    }

    private static function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('إضافة zip في PHP مش متفعّلة — احفظ الملف CSV من Excel وارفعه، أو فعّل الإضافة.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('الملف ده مش Excel سليم.');
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = self::xml($xml);
            foreach ($sx->si as $si) {
                $shared[] = self::text($si);
            }
        }

        // أول شيت حسب ترتيب الـ workbook
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $w = self::xml($wb);
            $first = $w->sheets->sheet[0] ?? null;
            if ($first) {
                $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach (self::xml($rels)->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');
                        $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                    }
                }
            }
        }

        // التنسيقات اللي هي تواريخ (عشان نحوّل رقم Excel لتاريخ)
        $dateStyles = [];
        if (($st = $zip->getFromName('xl/styles.xml')) !== false) {
            $s = self::xml($st);
            $customDates = [];
            foreach ($s->numFmts->numFmt ?? [] as $f) {
                if (preg_match('/[dy]/i', preg_replace('/\[[^\]]*\]|"[^"]*"/', '', (string) $f['formatCode']))) {
                    $customDates[] = (int) $f['numFmtId'];
                }
            }
            $i = 0;
            foreach ($s->cellXfs->xf ?? [] as $xf) {
                $id = (int) $xf['numFmtId'];
                if (($id >= 14 && $id <= 22) || ($id >= 45 && $id <= 47) || in_array($id, $customDates, true)) {
                    $dateStyles[$i] = true;
                }
                $i++;
            }
        }

        $sheet = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheet === false) {
            throw new RuntimeException('مش لاقي شيت في الملف.');
        }

        $rows = [];
        foreach (self::xml($sheet)->sheetData->row as $row) {
            if (count($rows) > self::MAX_ROWS) {
                break;
            }
            $r = [];
            $pos = 0;
            foreach ($row->c as $c) {
                $col = isset($c['r']) ? self::colIndex((string) $c['r']) : $pos;
                $type = (string) $c['t'];
                $v = isset($c->v) ? (string) $c->v : '';
                $value = match ($type) {
                    's'         => $shared[(int) $v] ?? '',
                    'inlineStr' => self::text($c->is),
                    'b'         => $v === '1' ? '1' : '0',
                    default     => $v,
                };
                if ($type === '' && $v !== '' && is_numeric($v) && isset($dateStyles[(int) $c['s']])) {
                    $value = Carbon::create(1899, 12, 30)->addDays((int) floor((float) $v))->toDateString();
                } elseif ($type === '' && is_numeric($value) && str_contains($value, 'E')) {
                    $value = rtrim(rtrim(sprintf('%.6F', (float) $value), '0'), '.');
                }
                $r[$col] = $value;
                $pos = $col + 1;
            }
            if ($r) {
                $max = max(array_keys($r));
                $rows[] = array_map(fn ($i) => $r[$i] ?? '', range(0, $max));
            } else {
                $rows[] = [];
            }
        }

        return $rows;
    }

    private static function xml(string $xml): \SimpleXMLElement
    {
        $x = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if ($x === false) {
            throw new RuntimeException('الملف ده مش Excel سليم.');
        }

        return $x;
    }

    private static function text(?\SimpleXMLElement $node): string
    {
        if (! $node) {
            return '';
        }
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $out = '';
        foreach ($node->r ?? [] as $run) {
            $out .= (string) $run->t;
        }

        return $out;
    }

    private static function colIndex(string $ref): int
    {
        $letters = preg_replace('/\d/', '', $ref);
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private static function colName(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    // =========================================================
    //  الكتابة
    // =========================================================

    /** بيرجّع ملف Excel للتنزيل (أو CSV لو zip مش متاح) */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        if (! class_exists(ZipArchive::class)) {
            return response()->streamDownload(function () use ($headers, $rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // عشان Excel يقرا العربي صح
                fputcsv($out, $headers, ',', '"', '');
                foreach ($rows as $r) {
                    fputcsv($out, array_values($r), ',', '"', '');
                }
                fclose($out);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        self::writeXlsx($tmp, $headers, $rows);

        return response()->streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public static function writeXlsx(string $path, array $headers, iterable $rows): void
    {
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $cell = function (int $col, int $row, $v, bool $bold = false) use ($esc) {
            $ref = self::colName($col).$row;
            $style = $bold ? ' s="1"' : '';
            if ($v === null || $v === '') {
                return '';
            }
            // int/float بس بيتكتبوا أرقام — التليفونات والأكواد والأرقام المدنية تفضل نص
            if (! $bold && (is_int($v) || is_float($v))) {
                return "<c r=\"{$ref}\"{$style}><v>{$v}</v></c>";
            }

            return "<c r=\"{$ref}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">".$esc($v).'</t></is></c>';
        };

        $body = '<row r="1">';
        foreach (array_values($headers) as $i => $h) {
            $body .= $cell($i, 1, $h, true);
        }
        $body .= '</row>';
        $n = 1;
        foreach ($rows as $r) {
            $n++;
            $body .= '<row r="'.$n.'">';
            foreach (array_values((array) $r) as $i => $v) {
                $body .= $cell($i, $n, $v);
            }
            $body .= '</row>';
        }
        $cols = '';
        foreach (array_values($headers) as $i => $h) {
            $w = max(12, min(40, mb_strlen((string) $h) + 6));
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView rightToLeft="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$cols.'</cols><sheetData>'.$body.'</sheetData></worksheet>');
        $zip->close();
    }
}
