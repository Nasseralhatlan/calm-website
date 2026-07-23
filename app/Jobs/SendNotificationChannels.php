<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\PushDeliveryContract;
use App\Contracts\SmsDeliveryContract;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fan a single notification out to the "live" transports (SMS + Expo push) for
 * one recipient. The in-app copy is written synchronously by NotificationService
 * BEFORE this job is dispatched, so the feed updates instantly; this job only
 * handles the external channels. Title/body arrive already resolved to the
 * user's language. Each channel is isolated so one failing doesn't drop the other.
 *
 * @param  array<string, mixed>  $data
 */
class SendNotificationChannels implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly User $user,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(SmsDeliveryContract $sms, PushDeliveryContract $push): void
    {
        // SMS — phone always exists (it's the login identifier). Body ONLY:
        // catalog bodies are self-contained sentences, and prepending the
        // title just repeated the opening words while eating SMS length.
        // Push/in-app keep the title — their UIs have a separate slot for it.
        if ($this->user->phone !== null && $this->user->phone !== '') {
            try {
                $sms->send($this->user->phone, $this->body);
            } catch (Throwable $e) {
                Log::warning('[notify] SMS failed', ['user' => $this->user->id, 'error' => $e->getMessage()]);
            }
        }

        // Push — disabled by default (config('push.enabled')); only sent when the
        // channel is on AND the user has registered device tokens.
        if (config('push.enabled')) {
            $tokens = $this->user->deviceTokens()->pluck('token')->all();
            if ($tokens !== []) {
                try {
                    $push->send($tokens, $this->title, $this->body, $this->data);
                } catch (Throwable $e) {
                    Log::warning('[notify] push failed', ['user' => $this->user->id, 'error' => $e->getMessage()]);
                }
            }
        }
    }
}
