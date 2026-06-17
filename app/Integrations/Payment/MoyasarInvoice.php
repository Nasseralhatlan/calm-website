<?php

declare(strict_types=1);

namespace App\Integrations\Payment;

/**
 * Thin, read-only view of a Moyasar hosted invoice — only the fields the
 * booking flow needs. The full raw payload is preserved in `raw` so it can be
 * snapshotted onto the booking for audit/debugging.
 */
final readonly class MoyasarInvoice
{
    /**
     * @param  int  $amount  Charged amount in halalas (minor units).
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $status,
        public int $amount,
        public ?string $url,
        public array $metadata,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            status: (string) ($data['status'] ?? 'unknown'),
            amount: (int) ($data['amount'] ?? 0),
            url: $data['url'] ?? null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            raw: $data,
        );
    }

    public function bookingId(): ?string
    {
        $id = $this->metadata['booking_id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /** Still open at the gateway — guest hasn't finished (or abandoned) paying. */
    public function isPending(): bool
    {
        return in_array($this->status, ['initiated', 'pending'], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'canceled';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /** Method of the successful payment (creditcard / mada / applepay / stcpay). */
    public function paymentMethod(): ?string
    {
        $payments = $this->raw['payments'] ?? [];
        if (! is_array($payments)) {
            return null;
        }

        foreach (array_reverse($payments) as $payment) {
            if (($payment['status'] ?? null) === 'paid') {
                $type = $payment['source']['type'] ?? null;

                return is_string($type) ? $type : null;
            }
        }

        return null;
    }
}
