<?php

declare(strict_types=1);

namespace App\Contracts;

interface PushDeliveryContract
{
    /**
     * Deliver a push notification to one or more device tokens. Implementations
     * are pure transport — they don't know what the notification is about.
     *
     * @param  list<string>  $tokens  Device push tokens (e.g. Expo tokens).
     * @param  array<string, mixed>  $data  Deep-link / context payload for the app.
     */
    public function send(array $tokens, string $title, string $body, array $data = []): void;
}
