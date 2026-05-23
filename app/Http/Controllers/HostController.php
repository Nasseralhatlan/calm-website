<?php

namespace App\Http\Controllers;

use App\Integrations\ObjectStorageIntegration;
use App\Models\Host;
use App\Support\Catalog;
use Illuminate\Http\Request;
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
        $data = $request->validate([
            'phone'                => ['required', 'string', 'min:6', 'max:30'],
            'place_type'           => ['required', 'in:chalet,resthouse,camp'],
            'title'                => ['required', 'string', 'min:2', 'max:120'],
            'description'          => ['nullable', 'string', 'max:5000'],
            'max_guests'           => ['required', 'integer', 'min:1', 'max:200'],
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

        $host = Host::create([
            'slug'        => Str::lower(Str::random(10)),
            'phone'       => $data['phone'],
            'place_type'  => $data['place_type'],
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'max_guests'  => $data['max_guests'],
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
}
