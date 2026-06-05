<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Enums\PlaceReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Host\SaveDraftRequest;
use App\Http\Requests\Host\StorePlaceRequest;
use App\Models\AttributeGroup;
use App\Models\City;
use App\Models\Place;
use App\Models\PlaceType;
use App\Services\Place\PlaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlacesController extends Controller
{
    public function __construct(private readonly PlaceService $service) {}

    /**
     * "Become a host" — the multi-step wizard. Everything the front-end needs
     * (place types, city areas, attribute catalog) is loaded here and handed
     * to Alpine via a JSON script tag in the view.
     */
    public function create(Request $request): View
    {
        // Cities grouped with their areas — the wizard renders a 2-stage picker
        // (city card with emoji → list of that city's areas). Inactive cities
        // are hidden so we can pre-seed coverage we're not ready to launch yet.
        $cities = City::query()
            ->active()
            ->with(['areas' => fn ($q) => $q->orderBy('name_en')])
            ->orderBy('name_en')
            ->get(['id', 'name_ar', 'name_en', 'avatar']);

        // Resume an in-progress place when `?draft={id}` is on the URL. The
        // host can resume both Draft submissions AND Rejected ones — Rejected
        // places are re-editable so the host can address the admin's feedback
        // and resubmit. The wizard's banner surfaces the rejection_reason.
        $draft = null;
        if ($draftId = $request->string('draft')->toString()) {
            $draft = Place::query()
                ->with(['cityArea:id,city_id', 'attributeValues', 'photos'])
                ->where('id', $draftId)
                ->where('host_user_id', $request->user()->id)
                ->whereIn('review_status', [
                    PlaceReviewStatus::Draft->value,
                    PlaceReviewStatus::Rejected->value,
                ])
                ->first();
        }

        return view('host.places.create', [
            'placeTypes' => PlaceType::active()->orderBy('name_en')->get(['id', 'name_ar', 'name_en', 'icon']),
            'cities' => $cities,
            // Attributes are sorted by how often hosts have actually picked
            // them (the place_attributes count) so the most-used facilities
            // surface at the top of each group's chip row. Cold-start fallback
            // is alphabetical when nothing has been chosen yet.
            'attributeGroups' => AttributeGroup::query()
                ->with(['attributes' => fn ($q) => $q
                    ->withCount('placeValues')
                    ->orderByDesc('place_values_count')
                    ->orderBy('name_en')])
                ->orderBy('name_en')
                ->get(),
            'draft' => $draft,
        ]);
    }

    /**
     * Wizard auto-save. Called every time the host advances a step; upserts
     * the host's in-progress draft and returns its id so the client can keep
     * patching the same record.
     */
    public function saveDraft(SaveDraftRequest $request): JsonResponse
    {
        $draft = $this->service->saveDraftForHost(
            $request->user(),
            $request->placeData(),
            $request->string('draft_id')->toString() ?: null,
            $request->attributesData(),
            $request->photosData(),
        );

        return response()->json([
            'id' => $draft->id,
            'review_status' => $draft->review_status->value,
        ]);
    }

    /**
     * Mint a short-lived presigned PUT URL so the browser uploads the file
     * straight to DO Spaces / S3 — the PHP container never sees the bytes.
     * Mirrors master's `hosts.presign-upload` route.
     */
    public function presignUpload(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mime' => ['required', 'string', 'max:120'],
        ]);

        $ext = pathinfo((string) $request->input('filename'), PATHINFO_EXTENSION) ?: 'jpg';
        $key = 'places/uploads/'.Str::lower(Str::random(24)).'.'.Str::lower($ext);
        $mime = (string) $request->input('mime');

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        /** @var \Aws\S3\S3Client $client */
        $client = $disk->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $mime,
            'ACL' => 'public-read',
        ]);

        $presigned = $client->createPresignedRequest($command, '+15 minutes');

        return response()->json([
            'put_url' => (string) $presigned->getUri(),
            'path' => $key,
            'public_url' => $disk->url($key),
            'mime' => $mime,
        ]);
    }

    public function store(StorePlaceRequest $request): RedirectResponse
    {
        $place = $this->service->createForHost(
            $request->user(),
            $request->placeData(),
            $request->string('draft_id')->toString() ?: null,
            $request->attributesData(),
            $request->photosData(),
        );

        return redirect()
            ->route('user.places')
            ->with('status', __('Place ":title" created — pending review.', ['title' => $place->title]));
    }

    public function index(Request $request): View
    {
        return view('host.places.index', [
            'places' => $this->service->forHost($request->user()),
        ]);
    }
}
