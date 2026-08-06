<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\ReviewStatus;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceList;
use App\Models\PlacePhoto;
use App\Models\PlaceReview;
use App\Models\PlaceType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * LOCAL/DEV ONLY — demo places, curated lists, and demo reviews so the guest
 * web UI (home carousels, search grid, place page) has something to render.
 * Not part of DatabaseSeeder: run explicitly with
 *
 *   php artisan db:seed --class=DemoContentSeeder
 *
 * Idempotent (keyed on the demo host + titles). Refuses to run in production —
 * this is layout-testing content, never real inventory.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoContentSeeder is demo content — refusing to run in production.');

            return;
        }

        $host = User::query()->firstOrCreate(
            ['phone' => '511111111'],
            ['name' => 'مضيف تجريبي'],
        );

        $types = PlaceType::query()->orderBy('name_en')->get();
        $areas = CityArea::query()->with('city')->get();

        if ($types->isEmpty() || $areas->isEmpty()) {
            $this->command?->error('Seed the base catalog first (php artisan db:seed).');

            return;
        }

        $typeByName = fn (string $en) => $types->firstWhere('name_en', $en) ?? $types->first();

        $demoPlaces = [
            ['شاليه الواحة', 'Chalets', 850, 12, 'شاليه عائلي بمسبح خاص وجلسات خارجية مظللة، مناسب للتجمعات العائلية ونهاية الأسبوع.'],
            ['شاليه لؤلؤة الخليج', 'Chalets', 1200, 16, 'شاليه فاخر بإطلالة مفتوحة، مسبح كبير وقسمين منفصلين للعوائل.'],
            ['شاليه المرجان', 'Chalets', 650, 8, 'شاليه هادئ بتصميم عصري، مثالي للإقامة القصيرة والمناسبات الصغيرة.'],
            ['استراحة النخيل', 'Resthouses', 550, 20, 'استراحة واسعة وسط مزارع النخيل، ملحق شوي وجلسات أرضية تقليدية.'],
            ['استراحة ضي القمر', 'Resthouses', 750, 25, 'استراحة بمسطحات خضراء واسعة وإضاءة مسائية مميزة للمناسبات.'],
            ['استراحة الروابي', 'Resthouses', 480, 15, 'استراحة عائلية بأسعار مناسبة، مجهزة بالكامل مع ألعاب أطفال.'],
            ['مزرعة الريف السعيد', 'Farms & Camps', 950, 30, 'مزرعة متكاملة ببيت ريفي وحظائر صغيرة، تجربة ريفية كاملة للعائلة.'],
            ['مخيم رمال الصحراء', 'Farms & Camps', 1400, 40, 'مخيم فاخر بخيام مكيفة وجلسات نار ليلية وتجربة صحراوية أصيلة.'],
            ['مزرعة وادي الغيم', 'Farms & Camps', 700, 18, 'مزرعة هادئة على أطراف الوادي، مساحات مفتوحة ومجلس خارجي كبير.'],
        ];

        // Mostly 5s so a few places cross the «مفضل الضيوف» badge bar
        // (avg ≥ 4.8 with ≥ 2 reviews) while others stay below it.
        $reviewPool = [
            ['سارة', 5, 'المكان نظيف ومرتب والتعامل راقٍ جداً.'],
            ['محمد', 5, 'تجربة جميلة، الموقع سهل الوصول والمرافق ممتازة.'],
            ['نورة', 5, 'من أجمل الأماكن التي جربناها، بالتأكيد سنكرر الزيارة.'],
            ['عبدالله', 4, 'المكان مطابق للصور تماماً والخصوصية عالية.'],
            ['ريم', 5, 'قضينا وقتاً رائعاً، المسبح نظيف والجلسات مريحة.'],
        ];

        $places = collect($demoPlaces)->map(function (array $row, int $i) use ($host, $areas, $typeByName, $reviewPool): Place {
            [$title, $typeName, $price, $guests, $description] = $row;
            $area = $areas[$i % $areas->count()];

            $place = Place::query()->updateOrCreate(
                ['host_user_id' => $host->id, 'title_ar' => $title],
                [
                    'place_type_id' => $typeByName($typeName)->id,
                    'city_area_id' => $area->id,
                    'title' => $title,
                    'description' => $description,
                    'description_ar' => $description,
                    'price' => $price,
                    'max_guests' => $guests,
                    'check_in_time' => '15:00',
                    'check_out_time' => '12:00',
                    'checkout_next_day' => true,
                    'status' => PlaceStatus::Active->value,
                    'review_status' => PlaceReviewStatus::Approved->value,
                ],
            );

            // Photos: stable picsum seeds → same images every run. First three
            // are featured so coverPhoto + the place-page mosaic light up.
            $place->photos()->delete();
            for ($p = 0; $p < 4; $p++) {
                PlacePhoto::query()->create([
                    'place_id' => $place->id,
                    'path' => "https://picsum.photos/seed/calm-demo-{$i}-{$p}/800/700",
                    'sort_order' => $p,
                    'featured_order' => $p < 3 ? $p : null,
                ]);
            }

            // Demo reviews (2–3 per place, skipping one place so the «جديد»
            // no-rating card state stays visible on the home page too).
            $place->reviews()->forceDelete();
            if ($i !== 2) {
                $count = 2 + ($i % 2);
                for ($r = 0; $r < $count; $r++) {
                    [$name, $rate, $comment] = $reviewPool[($i + $r) % count($reviewPool)];
                    PlaceReview::query()->create([
                        'place_id' => $place->id,
                        'reviewer_name' => $name,
                        'rate' => $rate,
                        'comment' => $comment,
                        'status' => ReviewStatus::Published->value,
                    ]);
                }
            }

            return $place;
        });

        // Curated home lists — three rows so the home page scrolls like the app.
        $demoLists = [
            ['مختارات كالم', 'Calm picks', '✨', 1, [0, 1, 4, 7, 3]],
            ['الأعلى تقييماً', 'Top rated', '⭐', 2, [1, 0, 6, 8, 5]],
            ['وجهات نهاية الأسبوع', 'Weekend getaways', '🏖️', 3, [2, 5, 8, 6, 3]],
        ];

        foreach ($demoLists as [$nameAr, $nameEn, $icon, $sort, $indexes]) {
            $list = PlaceList::query()->updateOrCreate(
                ['name_en' => $nameEn],
                ['name_ar' => $nameAr, 'icon' => $icon, 'sort_order' => $sort, 'status' => 'active'],
            );
            $list->places()->sync(
                collect($indexes)->mapWithKeys(fn (int $idx, int $order) => [
                    $places[$idx]->id => ['sort_order' => $order],
                ])->all(),
            );
        }

        $this->command?->info(sprintf(
            'Demo content ready: %d places, %d lists (host %s).',
            $places->count(),
            count($demoLists),
            $host->phone,
        ));
    }
}
