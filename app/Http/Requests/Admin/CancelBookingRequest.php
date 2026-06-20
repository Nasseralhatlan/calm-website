<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin cancels a confirmed booking, attributing it to the host (host called us
 * to cancel) or to the platform/admin (usually on the guest's request).
 */
class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actor' => ['required', 'in:host,admin'],
        ];
    }

    /** Map the chosen actor to the cancellation status. */
    public function canceledStatus(): BookingStatus
    {
        return $this->string('actor')->toString() === 'host'
            ? BookingStatus::CanceledByHost
            : BookingStatus::CanceledByAdmin;
    }
}
