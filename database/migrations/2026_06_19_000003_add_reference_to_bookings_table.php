<?php

declare(strict_types=1);

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'reference')) {
            Schema::table('bookings', function (Blueprint $table): void {
                // Short support reference (e.g. CB-7K9P2Q). Nullable for the
                // backfill below; the app always populates it on create.
                $table->string('reference', 16)->nullable()->unique()->after('id');
            });
        }

        // Backfill existing bookings with a unique reference.
        Booking::query()->whereNull('reference')->chunkById(200, function ($bookings): void {
            foreach ($bookings as $booking) {
                $booking->forceFill(['reference' => Booking::generateUniqueReference()])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'reference')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropColumn('reference');
            });
        }
    }
};
