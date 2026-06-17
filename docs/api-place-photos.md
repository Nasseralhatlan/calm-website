# Place Photos & Gallery Ordering

How a place's photos are ordered, and which fields drive each level of sorting.
Read this alongside the **Canonical Place shape** and **3b. Place detail** sections in
[`api-home.md`](./api-home.md).

## The model: two axes

Every photo carries two independent ordering values:

- **`sort_order`** — one continuous integer sequence across the whole place. Amenity
  sections are laid out one after another, and photos are ordered within each section.
  So `sort_order` encodes **both** the section order *and* the order inside a section.
- **`featured_order`** — the photo's slot in the curated "shown outside" showcase on the
  place page. `0` = the cover, then `1, 2, …` (max 10). `null` = not featured.

To save you the grouping/sorting work, the API also returns two ready-made arrays:

- **`featured_photos[]`** — the showcase, already ordered (`[0]` is the cover). On every
  place payload.
- **`photo_groups[]`** — the grouped "view images" gallery, already grouped by amenity and
  ordered. **Detail endpoint only** (`GET /api/places/{place_id}`).

`cover_photo_url` is a convenience: the first featured photo's URL, falling back to the
first gallery photo when the host hasn't featured any.

## The three sort levels → which field

| Sort level | Use this (pre-built) | Or derive from `photos[]` |
| --- | --- | --- |
| **1. Amenity section order** | iterate `photo_groups[]` in array order (already sorted by `min_sort_order`); `attribute` is `null` for the general group | group by `attribute_id`, order groups by the smallest `sort_order` in each |
| **2. Image order within a section** | `photo_groups[].photos[]` is already in order | filter one `attribute_id`, sort by `sort_order` ascending |
| **3. Featured (shown-outside) order** | `featured_photos[]` is already ordered, `[0]` = cover | take `photos[]` where `featured_order !== null`, sort by `featured_order` ascending (`0` = cover) |

### Deriving it yourself from `photos[]`

If you'd rather compute from the flat `photos[]` array (e.g. on a list screen that doesn't
return `photo_groups`):

```js
// 1 + 2 — grouped gallery, sections and within-section order
const groups = Object.values(
  place.photos.reduce((acc, p) => {
    const key = p.attribute_id ?? '__general__';
    (acc[key] ??= { attribute_id: p.attribute_id, photos: [] }).photos.push(p);
    return acc;
  }, {})
);
groups.forEach(g => g.photos.sort((a, b) => a.sort_order - b.sort_order)); // within a section
groups.sort((a, b) => a.photos[0].sort_order - b.photos[0].sort_order);    // section order (min sort_order)

// 3 — featured showcase (first = cover)
const featured = place.photos
  .filter(p => p.featured_order !== null)
  .sort((a, b) => a.featured_order - b.featured_order);
```

Resolve a group's amenity name/icon by matching `attribute_id` against
`attributes[].attribute.id` (on the detail screen), or just read the `attribute` object
that `photo_groups[]` already embeds.

## Field reference

### `photos[]` — full gallery (every place payload)

```json
{
  "id": "019ed2e8-...",
  "url": "https://cdn.../1.jpg",
  "attribute_id": "019ebcf9-...",
  "sort_order": 0,
  "featured_order": null
}
```

| Field | Type | Notes |
| --- | --- | --- |
| `id` | UUID string | React key. |
| `url` | string | Full image URL. |
| `attribute_id` | UUID \| null | The amenity this photo belongs to (match against `attributes[].attribute.id`). `null` = general gallery photo. |
| `sort_order` | integer | Canonical order across the whole place — drives both section order and within-section order. Ascending. |
| `featured_order` | integer \| null | Slot in the showcase: `0` = cover, then `1, 2, …` (≤10). `null` = not shown outside. |

### `featured_photos[]` — the "shown outside" showcase (every place payload)

Already ordered by `featured_order`; `[0]` is the cover. At most 10 items.

```json
[
  { "id": "019ed2e8-...", "url": "https://cdn.../cover.jpg", "attribute_id": "019ebcf9-..." },
  { "id": "019ed2e8-...", "url": "https://cdn.../2.jpg",     "attribute_id": null }
]
```

### `photo_groups[]` — grouped "view images" gallery (detail endpoint only)

Already grouped by amenity and ordered by `min_sort_order`. Iterate in array order to
render the grouped gallery; within each group, `photos[]` is in display order.

```json
[
  {
    "attribute_id": "019ebcf9-b746-...",
    "attribute": { "id": "019ebcf9-b746-...", "name_en": "Bedroom", "name_ar": "غرفة نوم", "icon": "🛏️" },
    "min_sort_order": 0,
    "photos": [
      { "id": "019ed2e8-...", "url": "https://cdn.../b1.jpg", "sort_order": 0, "featured_order": null }
    ]
  },
  {
    "attribute_id": null,
    "attribute": null,
    "min_sort_order": 20,
    "photos": [
      { "id": "019ed2e8-...", "url": "https://cdn.../g1.jpg", "sort_order": 20, "featured_order": 5 }
    ]
  }
]
```

| Field | Type | Notes |
| --- | --- | --- |
| `attribute_id` | UUID \| null | Amenity id for the group; `null` = the general (no-amenity) group. |
| `attribute` | object \| null | `{ id, name_en, name_ar, icon }` for the amenity; `null` for the general group — render your own "General / عام" heading. |
| `min_sort_order` | integer | The group's earliest `sort_order` — its slot in the gallery (groups are returned in this order). |
| `photos` | array | The group's photos in display order: `{ id, url, sort_order, featured_order }`. |

## Worked example

A place with photos arranged Bedroom → Majlis → General produces these `min_sort_order`s
and therefore this section order:

| Group | `min_sort_order` | Renders |
| --- | --- | --- |
| Bedroom | 0 | 1st section |
| Majlis | 10 | 2nd section |
| General (`attribute: null`) | 20 | last section |

The cover is whichever photo has `featured_order === 0` (also surfaced as
`cover_photo_url` and `featured_photos[0]`).
