<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A single presigned-upload request from the place wizard. Only the extension
 * of `filename` is used (the S3 key is minted server-side) and `mime` becomes
 * the signed Content-Type the client must echo on its PUT.
 */
class PresignUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            // Only browser-displayable formats may land in the bucket — HEIC
            // in particular must be converted client-side first (raw HEIC
            // renders broken everywhere except Safari).
            'mime' => ['required', 'string', 'in:image/jpeg,image/png,image/webp,image/gif,image/avif,image/svg+xml'],
        ];
    }
}
