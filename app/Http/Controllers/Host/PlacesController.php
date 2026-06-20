<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Enums\PlaceReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Host\SaveDraftRequest;
use App\Http\Requests\Host\StorePlaceRequest;
use App\Http\Requests\Host\UpdatePlaceDetailsRequest;
use App\Models\AttributeGroup;
use App\Models\City;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Place\PlaceService;
use App\Services\Place\SettingService;
use App\Services\User\UserService;
use Aws\S3\S3Client;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlacesController extends Controller
{
    public function __construct(
        private readonly PlaceService $service,
        private readonly UserService $users,
        private readonly SettingService $settings,
    ) {}

    /**
     * Pick the user the wizard's place should be attached to. Admins can fill
     * an extra "attach to host phone" field — if present, we resolve (or
     * create on the fly) that user instead of the admin themselves so sales
     * staff can onboard hosts. For non-admins the field is ignored entirely
     * and we always fall back to the current user.
     */
    private function resolveHost(Request $request): User
    {
        $current = $request->user();
        $hostPhone = $current->isAdmin()
            ? trim((string) $request->input('host_phone'))
            : '';

        return $hostPhone !== ''
            ? $this->users->findOrCreateByPhone($hostPhone)
            : $current;
    }

    /**
     * "Become a host" — the multi-step wizard. Everything the front-end needs
     * (place types, city areas, attribute catalog) is loaded here and handed
     * to Alpine via a JSON script tag in the view.
     */
    public function create(Request $request): View
    {
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
            ...$this->wizardCatalog(),
            'draft' => $draft,
        ]);
    }

    /**
     * Shared catalog data the wizard view needs (place types, cities+areas,
     * amenity groups, pricing rates). Reused by both the add flow and the edit
     * flow so the edit screen is a true replica of the add page.
     *
     * @return array<string, mixed>
     */
    private function wizardCatalog(): array
    {
        return [
            'placeTypes' => PlaceType::active()->orderBy('name_en')->get(['id', 'name_ar', 'name_en', 'icon']),
            // Cities grouped with their areas — the wizard renders a 2-stage
            // picker (city card → that city's areas). Inactive cities hidden.
            'cities' => City::query()
                ->active()
                ->with(['areas' => fn ($q) => $q->orderBy('name_en')])
                ->orderBy('name_en')
                ->get(['id', 'name_ar', 'name_en', 'avatar']),
            // Attributes follow the admin-controlled order (sort_order, then a
            // name tiebreaker) so the wizard matches the place page exactly.
            'attributeGroups' => AttributeGroup::query()
                ->with(['attributes' => fn ($q) => $q->ordered()])
                ->ordered()
                ->get(),
            // Commission rate (%) so the pricing step can show the host Calm's
            // cut and their take-home. Falls back to 15%.
            'pricingRates' => $this->pricingRates(),
        ];
    }

    /**
     * Commission percentage from settings (fallback 15%).
     *
     * @return array{commission: float}
     */
    private function pricingRates(): array
    {
        $rate = $this->settings->byKeys(['commission_percentage'])['commission_percentage'] ?? null;

        return ['commission' => is_numeric($rate) ? (float) $rate : 15.0];
    }

    /**
     * Wizard auto-save. Called every time the host advances a step; upserts
     * the host's in-progress draft and returns its id so the client can keep
     * patching the same record.
     */
    public function saveDraft(SaveDraftRequest $request): JsonResponse
    {
        $draft = $this->service->saveDraftForHost(
            $this->resolveHost($request),
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
            $this->resolveHost($request),
            $request->placeData(),
            $request->string('draft_id')->toString() ?: null,
            $request->attributesData(),
            $request->photosData(),
        );

        // Admins land back on the admin places index so they can immediately
        // queue another listing for the next host. Hosts go to their own
        // /my-places page as before.
        $redirectRoute = $request->user()->isAdmin()
            ? 'admin.places.index'
            : 'user.places';

        return redirect()
            ->route($redirectRoute)
            ->with('status', __('Place ":title" created — pending review.', ['title' => $place->title]));
    }

    public function index(Request $request): View
    {
        return view('host.places.index', [
            'places' => $this->service->forHost($request->user()),
        ]);
    }

    /**
     * Edit an existing place via a pre-filled replica of the add wizard — the
     * host can jump between steps and save (which resubmits for review). Same
     * view as `create`, with an edit config that flips the form to a PUT and
     * shows the sticky Save/Discard/Cancel bar.
     */
    public function edit(Request $request, Place $place): View
    {
        $this->authorizeOwner($request, $place);

        $place->load(['attributeValues', 'photos', 'cityArea:id,city_id']);

        return view('host.places.create', [
            ...$this->wizardCatalog(),
            'draft' => $place,
            'editConfig' => [
                'enabled' => true,
                'isAdmin' => false,
                'submitUrl' => route('host.places.update', $place),
                'cancelUrl' => route('user.places'),
            ],
        ]);
    }

    /**
     * Persist a host's edit (details + amenities + photos). The service flips
     * the listing back to PendingReview (offline until re-approved) since its
     * content changed.
     */
    public function update(UpdatePlaceDetailsRequest $request, Place $place): RedirectResponse
    {
        $this->authorizeOwner($request, $place);

        $this->service->updateDetailsForHost(
            $place,
            $request->placeData(),
            $request->attributesData(),
            $request->photosData(),
        );

        return redirect()
            ->route('user.places')
            ->with('status', __('Place ":title" updated — resubmitted for review.', ['title' => $place->title]));
    }

    /**
     * Archive a host's place — soft delete, so its bookings and history are
     * preserved and the action is reversible. Hidden from every listing.
     */
    public function destroy(Request $request, Place $place): RedirectResponse
    {
        $this->authorizeOwner($request, $place);

        $title = $place->title;
        $this->service->delete($place);

        return redirect()
            ->route('user.places')
            ->with('status', __('Place ":title" deleted.', ['title' => $title]));
    }

    /**
     * Only the place's own host (or an admin acting on their behalf) may edit
     * it. 403 otherwise so one host can never touch another's listing.
     */
    private function authorizeOwner(Request $request, Place $place): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && ($place->host_user_id === $user->id || $user->isAdmin()),
            403,
        );
    }
}
