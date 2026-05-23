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
        // Accept either a plain URL or the full iframe HTML that Google's
        // "Embed a map" tab gives. If it's an iframe, pull out the src.
        $rawMaps = (string) $request->input('maps_url', '');
        if (str_contains($rawMaps, '<iframe') && preg_match('/src=["\']([^"\']+)["\']/i', $rawMaps, $m)) {
            $request->merge(['maps_url' => $m[1]]);
        }

        $data = $request->validate([
            'phone'                => ['required', 'string', 'min:6', 'max:30'],
            'place_type'           => ['required', 'in:chalet,resthouse,camp'],
            'title'                => ['required', 'string', 'min:2', 'max:120'],
            'description'          => ['nullable', 'string', 'max:5000'],
            'max_guests'           => ['required', 'integer', 'min:1', 'max:200'],
            'maps_url'             => ['required', 'url', 'max:500'],
            'address'              => ['nullable', 'string', 'max:255'],
            'facilities'           => ['required', 'array', 'min:1'],
            'facilities.*.key'     => ['required', 'string'],
            'facilities.*.count'   => ['required', 'integer', 'min:1', 'max:99'],
            'amenities'            => ['array'],
            'amenities.*'          => ['string'],
            'facility_images'      => ['array'],
            'facility_images.*'    => ['array'],
            'facility_images.*.*'  => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,avif', 'max:16384'],
            'extra_images'         => ['array'],
            'extra_images.*'       => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,avif', 'max:16384'],
        ]);

        // every selected facility must have at least one image
        $uploadedByKey = $request->file('facility_images', []);
        $errors = [];
        foreach ($data['facilities'] as $f) {
            $files = (array) ($uploadedByKey[$f['key']] ?? []);
            if (\count($files) === 0) {
                $errors["facility_images.{$f['key']}"] = __('photos_required_per_facility');
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
                'key'   => $f['key'],
                'count' => (int) $f['count'],
            ]);
        }

        foreach ($data['amenities'] ?? [] as $key) {
            $host->amenities()->create(['key' => $key]);
        }

        $sort = 0;

        foreach ($data['facility_images'] ?? [] as $facilityKey => $files) {
            $facility = $facilityModels[$facilityKey] ?? null;
            foreach ($files as $file) {
                $upload = $this->storage->upload($file, "hosts/{$host->slug}");
                $host->images()->create([
                    'host_facility_id' => $facility?->id,
                    'path'             => $upload['data']['path'],
                    'sort'             => $sort++,
                ]);
            }
        }

        foreach ($data['extra_images'] ?? [] as $file) {
            $upload = $this->storage->upload($file, "hosts/{$host->slug}");
            $host->images()->create([
                'host_facility_id' => null,
                'path'             => $upload['data']['path'],
                'sort'             => $sort++,
            ]);
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
