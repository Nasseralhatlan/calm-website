<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PresignUploadRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Upload\PresignService;
use Illuminate\Http\JsonResponse;

/**
 * Mobile host uploads: mint a short-lived presigned S3 PUT so the app uploads
 * compressed photos straight to the bucket — the API never sees the bytes.
 * Same signing path as the web wizard (see PresignService).
 */
class HostUploadController extends Controller
{
    public function __construct(private readonly PresignService $service) {}

    /** Presign one upload: {filename, mime} → {put_url, path, public_url, mime}. */
    public function presign(PresignUploadRequest $request): JsonResponse
    {
        $presigned = $this->service->presignPut(
            (string) $request->validated('filename'),
            (string) $request->validated('mime'),
        );

        return ApiResponse::success(
            data: $presigned,
            message: 'Upload URL minted.',
        );
    }
}
