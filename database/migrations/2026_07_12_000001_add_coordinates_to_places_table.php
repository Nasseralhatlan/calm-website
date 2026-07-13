<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            // The host's exact map pin. Publicly exposed only ROUNDED (~1 km);
            // the precise values unlock on confirmed bookings, mirroring the
            // location_url sensitivity rule.
            if (! Schema::hasColumn('places', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('location_url');
            }
            if (! Schema::hasColumn('places', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });

        // Best-effort backfill from the Google Maps links hosts already
        // pasted. Unparseable URLs simply stay null — the mobile app renders
        // the map only when coordinates exist.
        foreach (DB::table('places')->whereNotNull('location_url')->get(['id', 'location_url']) as $place) {
            $coords = $this->parseCoords((string) $place->location_url);
            if ($coords !== null) {
                DB::table('places')->where('id', $place->id)->update([
                    'latitude' => $coords[0],
                    'longitude' => $coords[1],
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            foreach (['latitude', 'longitude'] as $column) {
                if (Schema::hasColumn('places', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Extract (lat, lng) from the common Google Maps URL shapes:
     * `@lat,lng`, `!3d{lat}!4d{lng}`, and `q=lat,lng` / `query=lat,lng`.
     *
     * @return array{0: float, 1: float}|null
     */
    private function parseCoords(string $url): ?array
    {
        $patterns = [
            '/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/',
            '/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/',
            '/[?&](?:q|query)=(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m) === 1) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    return [$lat, $lng];
                }
            }
        }

        return null;
    }
};
