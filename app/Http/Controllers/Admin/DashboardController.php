<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PlaceReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'counts' => [
                'users' => User::count(),
                'places' => Place::count(),
                'hosts' => User::query()->whereHas('places')->count(),
                'pending_review' => Place::query()->where('review_status', PlaceReviewStatus::PendingReview->value)->count(),
            ],
            'timelines' => [
                // Daily counts for the last 14 days. The view renders them as
                // small inline-SVG sparklines, no JS dependency. Bookings will
                // slot in here once that table exists.
                'users' => $this->dailyCounts(User::query(), 'created_at'),
                'places' => $this->dailyCounts(Place::query(), 'created_at'),
            ],
        ]);
    }

    /**
     * Group rows by day for the last N days. Missing days are filled with 0
     * so the sparkline width is stable across calls.
     *
     * @return array<int, int>
     */
    private function dailyCounts(Builder $base, string $column, int $days = 14): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = (clone $base)
            ->where($column, '>=', $start)
            ->get([$column])
            ->groupBy(fn ($r) => $r->{$column}->toDateString())
            ->map->count();

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $out[] = (int) ($rows[$day] ?? 0);
        }

        return $out;
    }
}
