<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

/**
 * الصور المرفوعة (شعارات الماركات، صور العربيات...)
 * بتتخزن في storage/app/public - ومحتاجة مرة واحدة: php artisan storage:link
 */
class Media
{
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    /** بيرجّع قايمة مسارات من عمود JSON سواء متخزن array أو نص */
    public static function list(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $value = json_decode($value, true);
        }

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    public static function store(UploadedFile $file, string $folder): string
    {
        return $file->store($folder, 'public');
    }

    /**
     * بيحفظ توقيع جاي من لوحة الرسم (data:image/png;base64,...) كصورة PNG.
     * بيرجّع null لو البيانات مش صورة PNG سليمة.
     */
    public static function storeSignature(?string $dataUrl, string $folder = 'signatures'): ?string
    {
        if (! $dataUrl || ! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        if ($binary === false || strlen($binary) > 512 * 1024 || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        $path = $folder.'/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://', '/'])) {
            Storage::disk('public')->delete($path);
        }
    }
}
