<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Backup;
use App\Support\Offsite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** سجل النشاط + النسخ الاحتياطي */
class SystemController extends Controller
{
    public function audit(Request $request): View
    {
        $f = [
            'user'  => $request->integer('user') ?: null,
            'event' => array_key_exists((string) $request->query('event'), Audit::EVENTS) ? $request->query('event') : null,
            'from'  => $request->date('from')?->toDateString(),
            'to'    => $request->date('to')?->toDateString(),
            'q'     => trim((string) $request->query('q')),
        ];

        $logs = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->when($f['user'], fn ($q, $v) => $q->where('audit_logs.user_id', $v))
            ->when($f['event'], fn ($q, $v) => $q->where('audit_logs.event', $v))
            ->when($f['from'], fn ($q, $v) => $q->where('audit_logs.created_at', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->where('audit_logs.created_at', '<', \Illuminate\Support\Carbon::parse($v)->addDay()))
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('audit_logs.description', 'like', "%{$f['q']}%")->orWhere('audit_logs.route', 'like', "%{$f['q']}%")
                ->orWhere('audit_logs.subject_id', $f['q'])->orWhere('audit_logs.ip', $f['q'])->orWhere('audit_logs.data', 'like', "%{$f['q']}%")))
            ->select('audit_logs.*', 'users.name as user_name')
            ->orderByDesc('audit_logs.id')->paginate(50)->withQueryString();

        $since = now()->subDay();

        return view('admin.system.audit', [
            'logs'    => $logs,
            'filters' => $f,
            'users'   => DB::table('users')->whereIn('role', ['staff', 'admin'])->orderBy('name')->pluck('name', 'id'),
            'stats'   => [
                'today'  => DB::table('audit_logs')->where('created_at', '>=', $since)->whereIn('event', ['create', 'update', 'delete', 'action'])->count(),
                'failed' => DB::table('audit_logs')->where('created_at', '>=', $since)->where('event', 'login_failed')->count(),
                'denied' => DB::table('audit_logs')->where('created_at', '>=', $since)->where('event', 'denied')->count(),
                'deletes'=> DB::table('audit_logs')->where('created_at', '>=', $since)->where('event', 'delete')->count(),
            ],
        ]);
    }

    public function backups(): View
    {
        return view('admin.system.backups', [
            'backups' => Backup::list(), 'dueToday' => Backup::dueNow(), 'slot' => Backup::slotStart(),
            'offsite' => Offsite::status(), 's3Ready' => Offsite::s3Ready(), 's3Folder' => Offsite::s3Folder(),
            'driveReady' => Offsite::driveReady(), 'driveConfigured' => Offsite::driveConfigured(), 'driveEmail' => Offsite::get('gdrive.email'),
            'encrypted' => (string) config('backup.password') !== '' && class_exists(\ZipArchive::class),
            'filesAt' => Offsite::get('backup.files_at'),
        ]);
    }

    public function backupNow(\Illuminate\Http\Request $request): RedirectResponse
    {
        try {
            [$file, $results] = Backup::runFull('manual', $request->boolean('files') ?: null);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'النسخة ما اتعملتش: '.$e->getMessage());
        }
        $failed = array_keys(array_filter($results, fn ($ok) => ! $ok));
        if ($failed) {
            return back()->with('error', 'النسخة اتعملت على السيرفر، بس الرفع فشل على: '.collect($failed)->map(fn ($t) => Offsite::TARGETS[$t])->implode(' و ').' — شوف السبب تحت.');
        }

        return back()->with('status', '✅ اتعملت نسخة: '.$file.($results ? ' واترفعت على '.collect(array_keys($results))->map(fn ($t) => Offsite::TARGETS[$t])->implode(' و ') : ''));
    }

    // ─── ربط Google Drive بتاع المعرض ───
    public function driveConnect(\Illuminate\Http\Request $request): RedirectResponse
    {
        abort_unless(Offsite::driveConfigured(), 404);
        $state = \Illuminate\Support\Str::random(40);
        $request->session()->put('gdrive_state', $state);

        return redirect()->away(Offsite::driveAuthUrl($state, route('admin.backups.google.callback')));
    }

    public function driveCallback(\Illuminate\Http\Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('gdrive_state');
        if (! $expected || ! hash_equals($expected, (string) $request->query('state')) || ! $request->query('code')) {
            return redirect()->route('admin.backups.index')->with('error', $request->query('error') === 'access_denied' ? 'الربط اتلغى — ماتمتش الموافقة من جوجل.' : 'الربط ما كملش — جرّب تاني.');
        }
        try {
            $email = Offsite::driveConnect($request->query('code'), route('admin.backups.google.callback'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.backups.index')->with('error', $e->getMessage());
        }
        Audit::log('action', null, ['description' => 'ربط Google Drive للنسخ الاحتياطي — '.$email]);

        return redirect()->route('admin.backups.index')->with('status', '✅ اتربط Google Drive ('.$email.') — النسخ هتترفع عليه لوحدها. دوس "اعمل نسخة دلوقتي" عشان تجرّب.');
    }

    public function driveDisconnect(): RedirectResponse
    {
        Offsite::driveDisconnect();
        Audit::log('action', null, ['description' => 'فصل Google Drive من النسخ الاحتياطي']);

        return back()->with('status', 'اتفصل Google Drive — النسخ اللي اترفعت عليه قبل كده لسه موجودة في الدرايف.');
    }

    public function download(string $file): StreamedResponse
    {
        $path = Backup::path($file);
        abort_unless($path, 404);
        Audit::log('action', null, ['description' => 'تنزيل نسخة احتياطية — '.$file]);

        return Backup::disk()->download($path);
    }

    public function deleteBackup(string $file): RedirectResponse
    {
        $path = Backup::path($file);
        abort_unless($path, 404);
        Backup::disk()->delete($path);

        return back()->with('status', 'تم مسح النسخة');
    }
}
