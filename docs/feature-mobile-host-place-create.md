# Feature plan: Mobile host place creation — API + Expo flow

> Status: **backend built** (2026-07-02). Frontend integration guide (API reference + web
> wizard walkthrough for the mobile port): `docs/mobile-host-place-create-frontend-guide.md`.
> All endpoints below are live with feature tests
> (`AttributeGroupsApiTest`, `HostUploadPresignTest`, `HostPlaceCreateTest`, `HostPlaceShowEditTest`,
> `HostListingsFilterTest`, `SettingsTest`); full suite green. The Expo client flow (§ below) is the
> remaining work, in the app repo.
> Decisions made: images = client compress (expo-image-manipulator) + presigned S3 PUT (mirrors web,
> server never handles bytes); scope = the full mobile create flow.
> Implementation notes: web presign extracted into `App\Services\Upload\PresignService` (web +
> API share it); `SaveDraftRequest`/`UpdatePlaceDetailsRequest::photosData()` now return the
> syncPhotos no-op shape when no photo fields are sent, so details-only edits and pre-photo-step
> draft saves no longer wipe an existing gallery (latent bug found while testing).

## Context
Hosts can create/edit listings only on the **web wizard** today. We want the same flow in the Expo app:
list "my places" with status, resume drafts, run the multi-step form, upload compressed photos, and
submit for review. The backend logic already exists in `PlaceService`
(`createForHost` / `saveDraftForHost` / `updateDetailsForHost` / `listingsForHost` / `delete`) and the
FormRequests (`StorePlaceRequest` / `SaveDraftRequest` / `UpdatePlaceDetailsRequest` with
`placeData()/attributesData()/photosData()`). Reference data (`/api/place-types`, `/api/cities`,
`/api/countries`) and guest-side place reads already exist. So this is mostly **exposing existing service
logic over `/api`** plus an attributes catalog, a presign endpoint, and a full "host place for edit"
resource. No migration.

## What already exists (reuse, don't rebuild)
- `GET /api/host/listings` → host's places, all statuses, with `status`, `review_status`,
  `rejection_reason`, counts, paginated (`HostListingResource`).
- Reference data: `GET /api/place-types`, `GET /api/cities` (areas eager-loaded), `GET /api/countries`.
- `PlaceService::{saveDraftForHost, createForHost, updateDetailsForHost, listingsForHost, delete}`.
- Web presign logic in `Host\PlacesController::presignUpload` (to extract into a shared service).
- FormRequests `Host\{StorePlaceRequest, SaveDraftRequest, UpdatePlaceDetailsRequest}` — reusable as-is
  for the API (JSON validation → enveloped 422; expose `placeData/attributesData/photosData`).

## Endpoints to add

### Reference data (public)
- **`GET /api/attribute-groups`** — amenity catalog: groups + attributes (`name_ar/en`, `icon`,
  `question_ar/en`, `type`, `photo_rule`, `is_highlighted`, `options`, `sort_order`).
  New `AttributeGroupResource` + `AttributeResource`.
- **`GET /api/settings`** — extend to also return `commission_percentage` + `vat_percentage` (pricing
  preview). Currently only support phone/email.

### Host (auth:api + throttle:authenticated)
- **`GET /api/host/listings?status=`** — add optional `status` filter
  (`draft|pending_review|approved|rejected|active`) for tabs → `listingsForHost($host, $status, $perPage)`
  + a `HostListingsRequest`.
- **`POST /api/host/uploads/presign`** — body `{filename, mime}` → `{put_url, path, public_url, mime}`
  (15-min presigned S3 PUT, `public-read`). Extract web logic into `App\Services\Upload\PresignService`
  (`presignPut(filename, mime): array`, key `places/uploads/<random24>.<ext>`) + `PresignUploadRequest`
  (`filename` required max:255, `mime` required max:120) + `Api\HostUploadController::presign`.
- **`GET /api/host/places/{place}`** — full **editable** host place (drafts included) for resume/edit.
  Owner-only. New `HostPlaceResource` — see shape below.
- **`POST /api/host/places/draft`** — wizard auto-save. `SaveDraftRequest` → `saveDraftForHost`.
  Returns `{ id, review_status }` (client keeps patching the same id; first call `draft_id=null`).
- **`POST /api/host/places`** — final submit. `StorePlaceRequest` → `createForHost`. `201` +
  `HostPlaceResource`. Requires ≥5 photos + all required fields (already enforced by the request).
- **`PUT /api/host/places/{place}`** — edit → resubmits for review. `UpdatePlaceDetailsRequest` →
  `updateDetailsForHost`. Owner-only.
- **`DELETE /api/host/places/{place}`** — soft-delete. Owner-only. `PlaceService::delete`.

New `Api\HostPlaceController` (owner guard like `Api\HostBlockingController`): `show/draft/store/update/destroy`,
one service call each, `ApiResponse` envelope. Host = `$request->user()` (admin `host_phone` path stays web-only).

### `HostPlaceResource` shape (mirror the web `edit()` serialization)
- scalars: `id, status, review_status, rejection_reason, last_step`, `title_ar/en`, `description_ar/en`,
  `rules_ar/en`, `place_type_id`, `city_area_id` (+ `city_id` via `cityArea.city_id`), `price`,
  `price_sunday..price_saturday`, `check_in_time`, `check_out_time`, `checkout_next_day`, `max_guests`,
  `location_url`.
- `attributes`: `[{ attribute_id, value, description }]` (from `attributeValues`).
- `photos`: **flat** `[{ place_attribute_id, path, url, featured_order, sort_order }]` — the app regroups
  into `attribute_image_paths` / `extra_image_paths` / `featured`, exactly like the web JS does
  (`resources/views/host/places/create.blade.php` ~L109). Keeps the server simple + web-consistent.
- Load `['attributeValues', 'photos', 'cityArea:id,city_id']`.

### Routes (`routes/api.php`)
```php
Route::get('/attribute-groups', [AttributeGroupsController::class, 'index']);   // public
// host auth group:
Route::post('/host/uploads/presign', [HostUploadController::class, 'presign']);
Route::get('/host/places/{place}', [HostPlaceController::class, 'show']);
Route::post('/host/places/draft', [HostPlaceController::class, 'draft']);
Route::post('/host/places', [HostPlaceController::class, 'store']);
Route::put('/host/places/{place}', [HostPlaceController::class, 'update']);
Route::delete('/host/places/{place}', [HostPlaceController::class, 'destroy']);
```

## Payloads (mirror the web wizard exactly)
- Bilingual: `title_ar`/`title_en` (≥1 required on submit), `description_ar/en`, `rules_ar/en` — backend
  derives canonical `title/description/rules` via `DerivesCanonicalContent`.
- Pricing: `price` (base) + `price_sunday..price_saturday` (0 = fall back to base).
- `check_in_time`/`check_out_time` `HH:MM`, `checkout_next_day` bool, `max_guests` 1–50,
  `location_url` (valid URL on submit).
- `attributes`: `[{ attribute_id, value?, description? }]`.
- Photos: `attribute_image_paths` = `{ attributeId: [path,…] }` (≤10 each), `extra_image_paths` = `[path,…]`
  (≤10), `featured` = ordered keys `attribute_images.<attrId>.<i>` / `extra_images.<i>` (first = cover).
  Submit needs ≥5 photos total; drafts have no minimum.
- Draft save also accepts `draft_id` + `last_step`.

## Expo (React Native) client flow
1. On wizard open: `GET /api/place-types`, `/api/cities`, `/api/attribute-groups`, `/api/settings`.
2. Pick + compress each photo: `expo-image-picker` → `expo-image-manipulator`
   `manipulateAsync(uri, [{ resize: { width: 2048 } }], { compress: 0.7, format: SaveFormat.JPEG })`
   (JPEG — manipulator WebP support is inconsistent; skip re-compress if already small).
3. Upload: `POST /api/host/uploads/presign {filename, mime}` → `PUT put_url` with
   `body: await (await fetch(localUri)).blob()` and headers `Content-Type: mime`,
   `x-amz-acl: public-read`. Keep `path` (+ `public_url` to preview).
4. Assemble the payload (above). Auto-save each step via `POST /api/host/places/draft`
   (`draft_id` null first, then the returned `id`). Submit via `POST /api/host/places`.
   Resume/edit via `GET /api/host/places/{id}` → hydrate; `PUT /api/host/places/{id}` to save edits.

## Tests (when built)
- `AttributeGroupsApiTest`: groups + nested attributes (type/photo_rule/options); public.
- `HostUploadPresignTest`: faked s3 config → `put_url` + `path` under `places/uploads/`; validates
  filename/mime; 401 unauth.
- `HostPlaceCreateTest`: draft returns id + `review_status:draft`; re-saving with that id updates the same
  row; submit ≥5 photos → 201 `pending_review`; <5 → 422.
- `HostPlaceShowEditTest`: `GET /host/places/{draft}` full editable shape (flat `photos` + `attributes`);
  owner-only (other host → 403); `PUT` edits + flips to pending_review; `DELETE` soft-deletes; owner-guarded.
- `HostListingsFilterTest`: `?status=` narrows; invalid status → 422.
- Settings now includes `commission_percentage` + `vat_percentage`.

## Verification (when built)
- `php artisan test --filter='HostPlace|AttributeGroups|HostUpload|HostListings'` + full suite green; Pint.
- Manual (host 501203845, JWT): reference GETs → presign → PUT → draft → submit → appears in
  `GET /api/host/listings?status=pending_review`; `GET/PUT/DELETE /api/host/places/{id}`.
- Code-only; no migration/env. Prod deploy `php artisan optimize:clear && php artisan optimize`.