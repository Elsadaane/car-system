<?php

namespace App\Console\Commands;

use App\Support\Backup;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'laroville:backup {--local : من غير رفع برّه السيرفر} {--files : ارفع الصور والمرفقات دلوقتي}';

    protected $description = 'نسخة احتياطية (قاعدة البيانات + الصور والمرفقات) وترفعها على المخزن المركزي وجوجل درايف';

    public function handle(): int
    {
        if ($this->option('local')) {
            $this->info('✅ '.Backup::run('cron'));

            return self::SUCCESS;
        }
        [$file, $results] = Backup::runFull('cron', $this->option('files') ?: null);
        $this->info('✅ '.$file);
        foreach ($results as $target => $ok) {
            $ok ? $this->info('  ↑ '.$target.' ✓') : $this->error('  ↑ '.$target.' ✗ '.(\App\Support\Offsite::status()[$target]['error'] ?? ''));
        }

        return in_array(false, $results, true) ? self::FAILURE : self::SUCCESS;
    }
}
