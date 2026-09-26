<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * نسخ احتياطي لقاعدة البيانات (SQL مضغوط) — من غير أي برامج خارجية.
 *  - بيتعمل أوتوماتيك في مواعيد BACKUP_TIMES (افتراضي 3 الفجر و3 العصر) — بالـ cron أو أول دخول للوحة بعد الميعاد
 *  - بيتحفظ في storage/app/private/backups (مش متاح من النت) — وبيترفع برّه السيرفر (Offsite)
 *  - بيفضل آخر 14 يوم
 *  الاسترجاع: فك الملف وافتحه في phpMyAdmin / HeidiSQL ← Import
 */
class Backup
{
    public const DIR = 'backups';

    public const KEEP = 28; // 14 يوم × نسختين

    public static function disk()
    {
        return Storage::disk('local');
    }

    public static function list(): array
    {
        return collect(self::disk()->files(self::DIR))
            ->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->map(fn ($f) => ['file' => basename($f), 'size' => self::disk()->size($f), 'time' => self::disk()->lastModified($f)])
            ->sortByDesc('time')->values()->all();
    }

    /** بداية الميعاد الحالي (آخر ميعاد من BACKUP_TIMES عدّى) */
    public static function slotStart(): \Carbon\CarbonInterface
    {
        $times = collect(explode(',', (string) config('backup.times', '03:00,15:00')))->map(fn ($t) => trim($t))
            ->filter(fn ($t) => preg_match('/^\d{1,2}:\d{2}$/', $t))->sort()->values();
        $times = $times->isEmpty() ? collect(['03:00']) : $times;
        $now = now();
        $start = null;
        foreach ($times as $t) {
            $at = today()->setTimeFromTimeString($t);
            if ($at->lte($now)) {
                $start = $at;
            }
        }

        return $start ?? today()->subDay()->setTimeFromTimeString($times->last());
    }

    /** نسخة الميعاد ده لسه ما اتعملتش؟ */
    public static function dueNow(): bool
    {
        $start = self::slotStart();

        return ! Cache::has('laroville.backup.'.$start->format('Y-m-d-Hi'))
            && ! collect(self::list())->contains(fn ($b) => $b['time'] >= $start->timestamp);
    }

    public static function dueToday(): bool
    {
        return self::dueNow();
    }

    /** للتشغيل الأوتوماتيك: عمرها ما تطلّع error للمستخدم */
    public static function runSafely(string $by = 'auto'): ?string
    {
        $key = 'laroville.backup.'.self::slotStart()->format('Y-m-d-Hi');
        if (! Cache::add($key, 1, now()->addHours(12))) {
            return null; // في حد تاني بيعمل النسخة دلوقتي
        }
        try {
            return self::runFull($by)[0];
        } catch (\Throwable $e) {
            Cache::forget($key);
            report($e);

            return null;
        }
    }

    /** نسخة + رفعها برّه السيرفر — بيرجّع [اسم الملف, نتيجة الرفع لكل مكان] */
    public static function runFull(string $by = 'manual', ?bool $withFiles = null): array
    {
        $name = self::run($by);
        $results = [];
        try {
            $results = Offsite::push(self::disk()->path(self::DIR.'/'.$name), $withFiles);
        } catch (\Throwable $e) {
            report($e);
            $st = Offsite::status();
            foreach (['s3' => Offsite::s3Ready(), 'gdrive' => Offsite::driveReady()] as $t => $ready) {
                if ($ready) {
                    $st[$t] = ['ok' => false, 'at' => time(), 'last_ok' => $st[$t]['last_ok'] ?? null, 'error' => \Illuminate\Support\Str::limit($e->getMessage(), 250)];
                    $results[$t] = false;
                }
            }
            Offsite::set('backup.offsite', $st);
        }

        return [$name, $results];
    }

    public static function run(string $by = 'manual'): string
    {
        @set_time_limit(600);
        $name = 'laroville-'.now()->format('Y-m-d_His').'.sql.gz';
        $dir = self::disk()->path(self::DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = $dir.'/'.$name.'.part';
        $gz = gzopen($tmp, 'wb6');
        $pdo = DB::connection()->getPdo();
        $driver = DB::getDriverName();

        gzwrite($gz, "-- Laroville backup ".now()->toDateTimeString()." ($by)\n");
        if ($driver === 'mysql' || $driver === 'mariadb') {
            gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        }

        foreach (self::tables() as $table) {
            $q = self::q($table, $driver);
            if ($driver === 'sqlite') {
                $create = DB::selectOne("select sql from sqlite_master where type='table' and name=?", [$table])->sql ?? null;
            } else {
                $create = ((array) DB::selectOne("SHOW CREATE TABLE $q"))['Create Table'] ?? null;
            }
            if (! $create) {
                continue;
            }
            gzwrite($gz, "DROP TABLE IF EXISTS $q;\n$create;\n");

            $batch = [];
            foreach (DB::cursor("select * from $q") as $row) {
                $vals = array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? $v : $pdo->quote((string) $v)), (array) $row);
                $batch[] = '('.implode(',', $vals).')';
                if (count($batch) >= 200) {
                    gzwrite($gz, "INSERT INTO $q VALUES ".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, "INSERT INTO $q VALUES ".implode(",\n", $batch).";\n");
            }
            gzwrite($gz, "\n");
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        }
        gzclose($gz);
        rename($tmp, $dir.'/'.$name);

        // آخر 14 بس
        foreach (array_slice(self::list(), self::KEEP) as $old) {
            self::disk()->delete(self::DIR.'/'.$old['file']);
        }
        Audit::log('backup', null, ['description' => ($by === 'auto' ? 'نسخة أوتوماتيك' : 'نسخة يدوي').' — '.$name]);

        return $name;
    }

    /** اسم آمن للملف (مفيش ../) */
    public static function path(string $file): ?string
    {
        return preg_match('/^laroville-[0-9_\-]+\.sql\.gz$/', $file) && self::disk()->exists(self::DIR.'/'.$file) ? self::DIR.'/'.$file : null;
    }

    private static function tables(): array
    {
        return collect(\Illuminate\Support\Facades\Schema::getTables())
            ->map(fn ($t) => is_array($t) ? $t['name'] : $t->name)
            ->reject(fn ($t) => in_array($t, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sqlite_sequence'], true))
            ->values()->all();
    }

    private static function q(string $table, string $driver): string
    {
        return $driver === 'sqlite' ? '"'.str_replace('"', '', $table).'"' : '`'.str_replace('`', '', $table).'`';
    }
}
