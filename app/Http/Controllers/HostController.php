<?php

namespace App\Http\Controllers;

use App\Integrations\ObjectStorageIntegration;
use App\Models\Host;
use App\Support\Catalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HostController extends Controller
{
    public function __construct(
        protected ObjectStorageIntegration $storage,
    ) {}

    public function create()
    {
        return view('hosts.register', [
            'placeTypes'    => Catalog::placeTypes(),
            'facilities'    => Catalog::facilities(),
            'amenityGroups' => Catalog::amenityGroups(),
        ]);
    }

    public function store(Request $request)
    {
        // Lift execution + memory limits — many remote MySQL round-trips can add up.
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');

        // Accept either a plain URL or the full iframe HTML that Google's
        // "Embed a map" tab gives. If it's an iframe, pull out the src.
        $rawMaps = (string) $request->input('maps_url', '');
        if (str_contains($rawMaps, '<iframe') && preg_match('/src=["\']([^"\']+)["\']/i', $rawMaps, $m)) {
            $request->merge(['maps_url' => $m[1]]);
        }

        $data = $request->validate([
            'phone'                => ['required', 'string', 'regex:/^5\d{8}$/'],
            'place_type'           => ['required', 'in:chalet,resthouse,camp'],
            'title'                => ['required', 'string', 'min:2', 'max:120'],
            'description'          => ['nullable', 'string', 'max:5000'],
            'max_guests'           => ['required', 'integer', 'min:1', 'max:200'],
            'maps_url'             => ['required', 'url', 'max:500'],
            'address'              => ['nullable', 'string', 'max:255'],
            'facilities'           => ['required', 'array', 'min:1'],
            'facilities.*.key'         => ['required', 'string'],
            'facilities.*.count'       => ['required', 'integer', 'min:1', 'max:99'],
            'facilities.*.description' => ['nullable', 'string', 'max:500'],
            'amenities'                => ['array'],
            'amenities.*'              => ['string'],
            'facility_image_paths'     => ['array'],
            'facility_image_paths.*'   => ['array'],
            'facility_image_paths.*.*' => ['string', 'max:500'],
            'extra_image_paths'        => ['array'],
            'extra_image_paths.*'      => ['string', 'max:500'],
        ]);

        // every selected facility must have at least one image
        $pathsByKey = $data['facility_image_paths'] ?? [];
        $errors     = [];
        foreach ($data['facilities'] as $f) {
            $paths = (array) ($pathsByKey[$f['key']] ?? []);
            if (\count($paths) === 0) {
                $errors["facility_image_paths.{$f['key']}"] = __('photos_required_per_facility');
            }
        }
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // try to extract lat/lng from the pasted Google Maps URL (handles long and short URLs)
        [$lat, $lng] = $this->extractCoordsFromMapsUrl($data['maps_url']);

        $host = Host::create([
            'slug'        => Str::lower(Str::random(10)),
            'phone'       => $data['phone'],
            'place_type'  => $data['place_type'],
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'max_guests'  => $data['max_guests'],
            'maps_url'    => $data['maps_url'],
            'latitude'    => $lat,
            'longitude'   => $lng,
            'address'     => $data['address'] ?? null,
        ]);

        $facilityModels = [];
        foreach ($data['facilities'] ?? [] as $f) {
            $facilityModels[$f['key']] = $host->facilities()->create([
                'key'         => $f['key'],
                'count'       => (int) $f['count'],
                'description' => $f['description'] ?? null,
            ]);
        }

        // Bulk-insert amenities — one round-trip instead of N.
        $now = now();
        if (! empty($data['amenities'])) {
            \App\Models\HostAmenity::insert(array_map(fn ($key) => [
                'host_id'    => $host->id,
                'key'        => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ], $data['amenities']));
        }

        // Files were already uploaded to S3; we only persist their paths here.
        $sort            = 0;
        $primaryKey      = (string) $request->input('primary_image', '');
        $primaryAssigned = false;
        $imageRows       = [];

        foreach ($data['facility_image_paths'] ?? [] as $facilityKey => $paths) {
            $facility = $facilityModels[$facilityKey] ?? null;
            foreach ($paths as $i => $path) {
                $thisKey   = "facility_images.{$facilityKey}.{$i}";
                $isPrimary = ! $primaryAssigned && $thisKey === $primaryKey;
                if ($isPrimary) {
                    $primaryAssigned = true;
                }
                $imageRows[] = [
                    'host_id'          => $host->id,
                    'host_facility_id' => $facility?->id,
                    'path'             => $path,
                    'sort'             => $sort++,
                    'is_primary'       => $isPrimary,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
        }

        foreach ($data['extra_image_paths'] ?? [] as $i => $path) {
            $thisKey   = "extra_images.{$i}";
            $isPrimary = ! $primaryAssigned && $thisKey === $primaryKey;
            if ($isPrimary) {
                $primaryAssigned = true;
            }
            $imageRows[] = [
                'host_id'          => $host->id,
                'host_facility_id' => null,
                'path'             => $path,
                'sort'             => $sort++,
                'is_primary'       => $isPrimary,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        // Bulk-insert all images in a single round-trip.
        if (! empty($imageRows)) {
            \App\Models\HostImage::insert($imageRows);
        }

        return redirect()->route('property.show', ['slug' => $host->slug]);
    }

    public function show(string $slug)
    {
        $host = Host::where('slug', $slug)
            ->with(['facilities.images', 'amenities', 'images'])
            ->firstOrFail();

        return view('hosts.show', [
            'host' => $host,
        ]);
    }

    /**
     * Mints a short-lived presigned PUT URL so the browser can upload the file
     * straight to DO Spaces — the PHP container never sees the bytes.
     */
    public function presignUpload(Request $request)
    {
        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mime'     => ['required', 'string', 'max:120'],
        ]);

        $ext  = pathinfo((string) $request->input('filename'), PATHINFO_EXTENSION) ?: 'jpg';
        $key  = 'hosts/uploads/' . Str::lower(Str::random(24)) . '.' . Str::lower($ext);
        $mime = (string) $request->input('mime');

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk    = \Illuminate\Support\Facades\Storage::disk('s3');
        /** @var \Aws\S3\S3Client $client */
        $client  = $disk->getClient();
        $bucket  = config('filesystems.disks.s3.bucket');

        $command = $client->getCommand('PutObject', [
            'Bucket'      => $bucket,
            'Key'         => $key,
            'ContentType' => $mime,
            'ACL'         => 'public-read',
        ]);

        $presigned = $client->createPresignedRequest($command, '+15 minutes');

        return response()->json([
            'put_url'    => (string) $presigned->getUri(),
            'path'       => $key,
            'public_url' => $disk->url($key),
            'mime'       => $mime,
        ]);
    }

    /**
     * Legacy server-proxied upload (kept as a fallback).
     */
    public function uploadImage(Request $request)
    {
        // Lift the per-request limits so large files + slow S3 round-trips don't 504.
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');

        $request->validate([
            // no size cap — image previews must stay full-quality
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,avif'],
        ]);

        $result = $this->storage->upload($request->file('image'), 'hosts/uploads');

        return response()->json([
            'path' => $result['data']['path'],
            'url'  => \Illuminate\Support\Facades\Storage::disk('s3')->url($result['data']['path']),
        ]);
    }

    /**
     * Try to pull lat/lng out of a pasted Google Maps URL.
     * Handles long URLs (@LAT,LNG, ?q=LAT,LNG, !3dLAT!4dLNG)
     * and short share URLs (maps.app.goo.gl/...) by following the redirect.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function extractCoordsFromMapsUrl(string $url): array
    {
        $candidate = $url;

        // short URLs need to be expanded first
        if (preg_match('#^https?://(?:maps\.app\.goo\.gl|goo\.gl/maps)/#i', $url)) {
            try {
                $response = Http::withOptions(['allow_redirects' => false])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->timeout(5)
                    ->get($url);
                $location = $response->header('Location');
                if ($location) {
                    $candidate = $location;
                }
            } catch (\Throwable) {
                // network failure: fall back to the raw URL we were given
            }
        }

        // "Embed a map" URLs encode coordinates as !2dLNG!3dLAT (longitude first).
        if (preg_match('/!2d(-?\d+\.\d+)!3d(-?\d+\.\d+)/', $candidate, $m)) {
            return [(float) $m[2], (float) $m[1]];
        }

        $patterns = [
            '/@(-?\d+\.\d+),(-?\d+\.\d+)/',          // .../@LAT,LNG,zoom
            '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',      // place pin format
            '/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/',     // ?q=LAT,LNG
            '/[?&]ll=(-?\d+\.\d+),(-?\d+\.\d+)/',    // ?ll=LAT,LNG
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $candidate, $m)) {
                return [(float) $m[1], (float) $m[2]];
            }
        }

        return [null, null];
    }
}
