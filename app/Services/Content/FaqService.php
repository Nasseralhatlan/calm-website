<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\FaqAudience;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Collection;

final class FaqService
{
    /**
     * Everything the admin index needs: both audiences, ordered.
     *
     * @return array{guest: Collection<int, Faq>, host: Collection<int, Faq>}
     */
    public function grouped(): array
    {
        $all = Faq::query()->ordered()->get()->groupBy(fn (Faq $f): string => $f->audience->value);

        return [
            'guest' => $all->get(FaqAudience::Guest->value, new Collection),
            'host' => $all->get(FaqAudience::Host->value, new Collection),
        ];
    }

    /**
     * One audience's entries in display order — the public page tab.
     *
     * @return Collection<int, Faq>
     */
    public function forAudience(FaqAudience $audience): Collection
    {
        return Faq::query()->forAudience($audience)->ordered()->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Faq
    {
        $data['sort_order'] ??= 0;

        return Faq::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Faq $faq, array $data): Faq
    {
        $data['sort_order'] ??= 0;

        $faq->update($data);

        return $faq->refresh();
    }

    public function delete(Faq $faq): void
    {
        $faq->delete();
    }
}
