<?php

namespace App\Integrations;

use App\Exceptions\ResultException;
use App\Traits\ResultTrait;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ObjectStorageIntegration
{
    use ResultTrait;

    protected string $disk = 's3';

    public function upload(
        UploadedFile $file,
        ?string $directory = null,
        ?string $filename = null
    ): array {
        try {
            $path = $file->storeAs(
                $directory ?? '',
                $filename ?? $this->generateFilename($file),
                [
                    'disk' => $this->disk,
                    'visibility' => 'public',
                ]
            );

            if (! $path) {
                throw new ResultException('Failed to upload file.', 500);
            }

            return $this->success('File uploaded successfully.', [
                'path' => $path,
            ]);
        } catch (Exception $e) {
            throw new ResultException(
                'Object storage upload failed, '.$e->getMessage(),
                500,
            );
        }
    }

    public function delete(string $path): array
    {
        try {
            Storage::disk($this->disk)->delete($path);

            return $this->success('File deleted successfully.');
        } catch (Throwable $e) {
            throw new ResultException(
                'Failed to delete file, '.$e->getMessage(),
                500,
            );
        }
    }

    protected function generateFilename(UploadedFile $file): string
    {
        return uniqid('', true).'.'.$file->getClientOriginalExtension();
    }
}
