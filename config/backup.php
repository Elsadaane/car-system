<?php

/*
| النسخ الاحتياطي — كل الإعدادات من ملف .env
|   BACKUP_TIMES=03:00,15:00          مواعيد النسخ (مرتين في اليوم)
|   BACKUP_PASSWORD=...               باسورد تشفير النسخة اللي بتترفع برّه (احفظه عندك برّه السيرفر!)
|   BACKUP_S3_*                       المخزن المركزي بتاعك (Cloudflare R2 / Backblaze B2 / Wasabi)
|   GOOGLE_DRIVE_CLIENT_ID/SECRET     عشان المعرض يربط Google Drive بتاعه (اختياري)
*/
return [
    'times'     => env('BACKUP_TIMES', '03:00,15:00'),
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
    'password'  => env('BACKUP_PASSWORD'),

    's3' => [
        'endpoint' => env('BACKUP_S3_ENDPOINT'),          // مثلاً https://<account-id>.r2.cloudflarestorage.com
        'region'   => env('BACKUP_S3_REGION', 'auto'),
        'bucket'   => env('BACKUP_S3_BUCKET'),
        'key'      => env('BACKUP_S3_KEY'),
        'secret'   => env('BACKUP_S3_SECRET'),
        'folder'   => env('BACKUP_S3_FOLDER'),            // فولدر المعرض ده (لو فاضي = اسم الدومين)
    ],

    'google' => [
        'client_id'     => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
    ],
];
