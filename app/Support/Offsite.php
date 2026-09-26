<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * رفع النسخة الاحتياطية برّه السيرفر — مكانين:
 *   s3:     المخزن المركزي بتاعك (Cloudflare R2 / Backblaze B2 / Wasabi) — من .env
 *   gdrive: Google Drive بتاع المعرض نفسه — بيربطه صاحب المعرض بزرار
 * النسخة: ملف zip مشفّر (AES-256) فيه قاعدة البيانات + الصور والمرفقات.
 */
class Offsite
{
    public const TARGETS = ['s3' => 'المخزن المركزي', 'gdrive' => 'Google Drive المعرض'];

    private const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.file';

    // =========================================================
    //  إعدادات محفوظة (app_settings)
    // =========================================================

    public static function get(string $key, $default = null)
    {
        try {
            $v = DB::table('app_settings')->where('key', $key)->value('value');
        } catch (Throwable) {
            return $default;
        }

        return $v === null ? $default : json_decode($v, true);
    }

    public static function set(string $key, $value): void
    {
        $value === null
            ? DB::table('app_settings')->where('key', $key)->delete()
            : DB::table('app_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }

    public static function s3Ready(): bool
    {
        $c = config('backup.s3');

        return ! empty($c['endpoint']) && ! empty($c['bucket']) && ! empty($c['key']) && ! empty($c['secret']);
    }

    public static function driveReady(): bool
    {
        return (bool) self::get('gdrive.token');
    }

    public static function driveConfigured(): bool
    {
        return ! empty(config('backup.google.client_id')) && ! empty(config('backup.google.client_secret'));
    }

    /** [target => ['ok','at','error','file','size']] */
    public static function status(): array
    {
        return self::get('backup.offsite', []);
    }

    /** فيه مكان متظبط وفشل آخر مرة (أو ما اترفعش من أكتر من يوم ونص)؟ */
    public static function problems(): array
    {
        $out = [];
        $st = self::status();
        foreach (['s3' => self::s3Ready(), 'gdrive' => self::driveReady()] as $t => $ready) {
            if (! $ready) {
                continue;
            }
            $s = $st[$t] ?? null;
            if ($s && ! $s['ok']) {
                $out[$t] = $s['error'] ?? 'فشل الرفع';
            } elseif (! $s || empty($s['last_ok']) || $s['last_ok'] < now()->subHours(36)->timestamp) {
                $out[$t] = 'مااترفعتش نسخة من أكتر من يوم ونص';
            }
        }

        return $out;
    }

    // =========================================================
    //  الرفع
    // =========================================================

    /** بيعمل الملف المشفّر ويرفعه على كل الأماكن المتظبطة */
    public static function push(string $sqlFile, ?bool $withFiles = null): array
    {
        $targets = array_filter(['s3' => self::s3Ready(), 'gdrive' => self::driveReady()]);
        if (! $targets) {
            return [];
        }
        // الصور والمرفقات: مرة في اليوم كفاية (أول نسخة بعد 20 ساعة من آخر مرة)
        $withFiles ??= (int) self::get('backup.files_at', 0) < now()->subHours(20)->timestamp;

        @set_time_limit(0);
        [$bundle, $hasFiles] = self::bundle($sqlFile, $withFiles);
        $name = basename($bundle);
        $size = filesize($bundle);
        $status = self::status();
        $results = [];

        foreach (array_keys($targets) as $t) {
            try {
                $t === 's3' ? self::s3Upload($bundle, $name) : self::driveUpload($bundle, $name);
                $results[$t] = true;
                $status[$t] = ['ok' => true, 'at' => time(), 'last_ok' => time(), 'file' => $name, 'size' => $size, 'files' => $hasFiles, 'error' => null];
                try {
                    $t === 's3' ? self::s3Prune() : self::drivePrune();
                } catch (Throwable $e) {
                    report($e);
                }
            } catch (Throwable $e) {
                report($e);
                $results[$t] = false;
                $status[$t] = ['ok' => false, 'at' => time(), 'last_ok' => $status[$t]['last_ok'] ?? null, 'file' => $name, 'size' => $size, 'files' => $hasFiles, 'error' => Str::limit($e->getMessage(), 250)];
            }
        }
        @unlink($bundle);

        self::set('backup.offsite', $status);
        if ($hasFiles && in_array(true, $results, true)) {
            self::set('backup.files_at', time());
        }
        Audit::log('backup', null, ['description' => 'رفع النسخة برّه السيرفر — '.collect($results)->map(fn ($ok, $t) => self::TARGETS[$t].': '.($ok ? '✓' : '✗'))->implode(' · ')]);

        return $results;
    }

    /** zip مشفّر: database.sql.gz + files/public + files/attachments */
    private static function bundle(string $sqlFile, bool $withFiles): array
    {
        $dir = Backup::disk()->path(Backup::DIR.'/offsite');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $base = preg_replace('/\.sql\.gz$/', '', basename($sqlFile));

        if (! class_exists(ZipArchive::class)) { // من غير zip: قاعدة البيانات بس
            $path = $dir.'/'.$base.'.sql.gz';
            copy($sqlFile, $path);

            return [$path, false];
        }

        $path = $dir.'/'.$base.($withFiles ? '-full' : '-db').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('مش قادر أعمل ملف zip');
        }
        $password = (string) config('backup.password');
        $encrypt = $password !== '' && defined('ZipArchive::EM_AES_256');
        if ($encrypt) {
            $zip->setPassword($password);
        }
        $add = function (string $file, string $as) use ($zip, $encrypt) {
            $zip->addFile($file, $as);
            if ($encrypt) {
                $zip->setEncryptionName($as, ZipArchive::EM_AES_256);
            }
        };
        $add($sqlFile, 'database.sql.gz');

        $hasFiles = false;
        if ($withFiles) {
            foreach (['public' => Storage::disk('public')->path(''), 'attachments' => Storage::disk('local')->path('attachments')] as $label => $root) {
                if (! is_dir($root)) {
                    continue;
                }
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if ($f->isFile()) {
                        $add($f->getPathname(), 'files/'.$label.'/'.ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/'));
                        $hasFiles = true;
                    }
                }
            }
        }
        $zip->setArchiveComment('Laroville backup '.now()->toDateTimeString().($encrypt ? ' — encrypted (AES-256)' : ''));
        $zip->close();

        return [$path, $hasFiles];
    }

    // =========================================================
    //  S3 (R2 / B2 / Wasabi) — توقيع AWS SigV4 من غير مكتبات
    // =========================================================

    public static function s3Folder(): string
    {
        $f = trim((string) config('backup.s3.folder'), '/');

        return $f !== '' ? $f : (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'laroville');
    }

    private static function s3Upload(string $file, string $name): void
    {
        $h = fopen($file, 'rb');
        try {
            $res = self::s3('PUT', self::s3Folder().'/'.$name, [], $h, ['Content-Type' => 'application/octet-stream', 'Content-Length' => (string) filesize($file)]);
        } finally {
            is_resource($h) && fclose($h);
        }
        if (! $res->successful()) {
            throw new RuntimeException('المخزن رفض الرفع ('.$res->status().'): '.self::s3Error($res->body()));
        }
    }

    private static function s3Prune(): void
    {
        $limit = now()->subDays(config('backup.keep_days', 14));
        $res = self::s3('GET', '', ['list-type' => '2', 'prefix' => self::s3Folder().'/']);
        if (! $res->successful()) {
            return;
        }
        $xml = simplexml_load_string($res->body());
        foreach ($xml->Contents ?? [] as $obj) {
            if (Carbon::parse((string) $obj->LastModified)->lt($limit)) {
                self::s3('DELETE', (string) $obj->Key);
            }
        }
    }

    public static function s3List(): array
    {
        $res = self::s3('GET', '', ['list-type' => '2', 'prefix' => self::s3Folder().'/']);
        if (! $res->successful()) {
            throw new RuntimeException('مش قادر أوصل للمخزن ('.$res->status().'): '.self::s3Error($res->body()));
        }
        $out = [];
        foreach (simplexml_load_string($res->body())->Contents ?? [] as $obj) {
            $out[] = ['key' => (string) $obj->Key, 'size' => (int) $obj->Size, 'time' => Carbon::parse((string) $obj->LastModified)->timestamp];
        }

        return collect($out)->sortByDesc('time')->values()->all();
    }

    /** طلب موقّع بـ SigV4 (path-style: endpoint/bucket/key) */
    public static function s3(string $method, string $key, array $query = [], $body = null, array $headers = [], ?\Carbon\CarbonInterface $now = null)
    {
        $c = config('backup.s3');
        $endpoint = rtrim((string) $c['endpoint'], '/');
        $host = parse_url($endpoint, PHP_URL_HOST).(parse_url($endpoint, PHP_URL_PORT) ? ':'.parse_url($endpoint, PHP_URL_PORT) : '');
        $path = '/'.rawurlencode($c['bucket']).($key !== '' ? '/'.implode('/', array_map('rawurlencode', explode('/', $key))) : '/');
        $sign = self::s3Sign($method, $host, $path, $query, $headers, $body === null ? hash('sha256', '') : 'UNSIGNED-PAYLOAD', $now ?? now('UTC'));

        ksort($query);
        $url = $endpoint.$path.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
        $req = Http::withHeaders(array_diff_key($headers, ['Content-Type' => 1]) + $sign)->timeout(0)->connectTimeout(20);
        if ($body !== null) {
            $req = $req->withBody(\GuzzleHttp\Psr7\Utils::streamFor($body), $headers['Content-Type'] ?? 'application/octet-stream');
        }

        return $req->send($method, $url);
    }

    /** بيرجّع هيدرز التوقيع — public عشان الاختبار يقارنه بمكتبة AWS الرسمية */
    public static function s3Sign(string $method, string $host, string $path, array $query, array $headers, string $payloadHash, \Carbon\CarbonInterface $now): array
    {
        $c = config('backup.s3');
        $amzDate = $now->copy()->utc()->format('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);
        $region = $c['region'] ?: 'auto';

        ksort($query);
        $cq = implode('&', array_map(fn ($k, $v) => rawurlencode($k).'='.rawurlencode((string) $v), array_keys($query), $query));
        $sh = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $amzDate];
        ksort($sh);
        $canonHeaders = implode('', array_map(fn ($k, $v) => $k.':'.trim($v)."\n", array_keys($sh), $sh));
        $signed = implode(';', array_keys($sh));
        $canon = implode("\n", [$method, $path, $cq, $canonHeaders, $signed, $payloadHash]);
        $scope = "$date/$region/s3/aws4_request";
        $toSign = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n".hash('sha256', $canon);
        $k = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $region, hash_hmac('sha256', $date, 'AWS4'.$c['secret'], true), true), true), true);

        return [
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
            'Authorization' => "AWS4-HMAC-SHA256 Credential={$c['key']}/$scope, SignedHeaders=$signed, Signature=".hash_hmac('sha256', $toSign, $k),
        ];
    }

    private static function s3Error(string $xml): string
    {
        return preg_match('#<Message>(.*?)</Message>#s', $xml, $m) ? $m[1] : Str::limit(strip_tags($xml), 120);
    }

    // =========================================================
    //  Google Drive بتاع المعرض (OAuth — صلاحية الملفات اللي السيستم عملها بس)
    // =========================================================

    public static function driveAuthUrl(string $state, string $redirect): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('backup.google.client_id'), 'redirect_uri' => $redirect, 'response_type' => 'code',
            'scope' => self::DRIVE_SCOPE, 'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state, 'include_granted_scopes' => 'true',
        ]);
    }

    public static function driveConnect(string $code, string $redirect): string
    {
        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => config('backup.google.client_id'), 'client_secret' => config('backup.google.client_secret'),
            'redirect_uri' => $redirect, 'grant_type' => 'authorization_code',
        ]);
        if (! $res->successful() || ! $res->json('refresh_token')) {
            throw new RuntimeException('جوجل ما وافقتش: '.($res->json('error_description') ?? $res->json('error') ?? $res->status()));
        }
        $access = $res->json('access_token');
        $email = Http::withToken($access)->get('https://www.googleapis.com/drive/v3/about', ['fields' => 'user(emailAddress)'])->json('user.emailAddress');
        $folder = Http::withToken($access)->post('https://www.googleapis.com/drive/v3/files?fields=id', [
            'name' => 'Laroville Backups — '.Company::name(), 'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        if (! $folder->successful()) {
            throw new RuntimeException('مش قادر أعمل فولدر في الدرايف ('.$folder->status().')');
        }
        self::set('gdrive.token', Crypt::encryptString($res->json('refresh_token')));
        self::set('gdrive.email', $email);
        self::set('gdrive.folder', $folder->json('id'));
        Cache::put('gdrive.access', $access, now()->addSeconds(max(60, (int) $res->json('expires_in', 3600) - 120)));

        return (string) $email;
    }

    public static function driveDisconnect(): void
    {
        try {
            $token = self::get('gdrive.token');
            if ($token) {
                Http::asForm()->post('https://oauth2.googleapis.com/revoke', ['token' => Crypt::decryptString($token)]);
            }
        } catch (Throwable) {
        }
        foreach (['gdrive.token', 'gdrive.email', 'gdrive.folder'] as $k) {
            self::set($k, null);
        }
        $st = self::status();
        unset($st['gdrive']);
        self::set('backup.offsite', $st);
        Cache::forget('gdrive.access');
    }

    private static function driveToken(): string
    {
        if ($t = Cache::get('gdrive.access')) {
            return $t;
        }
        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('backup.google.client_id'), 'client_secret' => config('backup.google.client_secret'),
            'refresh_token' => Crypt::decryptString((string) self::get('gdrive.token')), 'grant_type' => 'refresh_token',
        ]);
        if (! $res->successful()) {
            throw new RuntimeException($res->json('error') === 'invalid_grant'
                ? 'ربط جوجل درايف اتلغى من حساب جوجل — اربطه تاني من صفحة النسخ الاحتياطي'
                : 'جوجل رفضت ('.$res->status().')');
        }
        Cache::put('gdrive.access', $res->json('access_token'), now()->addSeconds(max(60, (int) $res->json('expires_in', 3600) - 120)));

        return $res->json('access_token');
    }

    /** رفع على دفعات (resumable) — بيستحمل الملفات الكبيرة */
    private static function driveUpload(string $file, string $name): void
    {
        $token = self::driveToken();
        $size = filesize($file);
        $init = Http::withToken($token)->withHeaders(['X-Upload-Content-Type' => 'application/octet-stream', 'X-Upload-Content-Length' => (string) $size])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', ['name' => $name, 'parents' => [self::get('gdrive.folder')]]);
        if (! $init->successful() || ! $init->header('Location')) {
            throw new RuntimeException('جوجل درايف رفض يبدأ الرفع ('.$init->status().'): '.($init->json('error.message') ?? ''));
        }
        $url = $init->header('Location');
        $chunk = 8 * 1024 * 1024; // لازم مضاعف 256KB
        $h = fopen($file, 'rb');
        try {
            for ($offset = 0; $offset < $size || $size === 0; $offset += $chunk) {
                $data = $size ? fread($h, $chunk) : '';
                $end = $offset + strlen($data) - 1;
                $res = Http::withToken($token)->timeout(0)
                    ->withHeaders(['Content-Range' => $size ? "bytes $offset-$end/$size" : 'bytes */0'])
                    ->withBody($data, 'application/octet-stream')->put($url);
                if ($res->status() === 308) {
                    continue; // كمّل الدفعة الجاية
                }
                if (! $res->successful()) {
                    throw new RuntimeException('الرفع على جوجل درايف وقف ('.$res->status().'): '.($res->json('error.message') ?? ''));
                }
                break;
            }
        } finally {
            fclose($h);
        }
    }

    private static function drivePrune(): void
    {
        $token = self::driveToken();
        $limit = now()->subDays(config('backup.keep_days', 14))->toRfc3339String();
        $res = Http::withToken($token)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => "'".self::get('gdrive.folder')."' in parents and trashed = false and createdTime < '$limit'", 'fields' => 'files(id)', 'pageSize' => 100,
        ]);
        foreach ($res->json('files', []) as $f) {
            Http::withToken($token)->delete('https://www.googleapis.com/drive/v3/files/'.$f['id']);
        }
    }
}
