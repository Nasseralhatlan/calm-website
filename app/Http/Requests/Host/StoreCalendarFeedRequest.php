<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An external iCal URL (Airbnb / Gathern / Google) the host wants imported
 * into a place. Ownership is enforced in the controller; the per-place feed
 * cap lives in CalendarSyncService. Shared by the web and API endpoints.
 */
class StoreCalendarFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // http/https only — this URL is fetched server-side, so other
            // schemes (file:, ftp:, …) are an SSRF vector.
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @return array{name: string, url: string}
     */
    public function feedData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'url' => (string) $this->validated('url'),
        ];
    }
}
