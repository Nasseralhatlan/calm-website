<?php

declare(strict_types=1);

namespace App\Services\Upload;

use Aws\S3\S3Client;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mints short-lived presigned S3 PUT URLs so clients (web wizard + mobile app)
 * upload photo bytes straight to DO Spaces / S3 — the PHP container never
 * handles the file itself. Extracted from Host\PlacesController::presignUpload
 * so the web and API endpoints share one signing path.
 */
final class PresignService
{
    /**
     * Presign a 15-minute PUT for one upload. The key is always minted
     * server-side under places/uploads/ — the client's filename only
     * contributes its extension, so a hostile name can't traverse the bucket.
     *
     * @return array{put_url: string, path: string, public_url: string, mime: string}
     */
    public function presignPut(string $filename, string $mime): array
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
        $key = 'places/uploads/'.Str::lower(Str::random(24)).'.'.Str::lower($ext);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        /** @var S3Client $client */
        $client = $disk->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $mime,
            'ACL' => 'public-read',
        ]);

        $presigned = $client->createPresignedRequest($command, '+15 minutes');

        return [
            'put_url' => (string) $presigned->getUri(),
            'path' => $key,
            'public_url' => $disk->url($key),
            'mime' => $mime,
        ];
    }
}
