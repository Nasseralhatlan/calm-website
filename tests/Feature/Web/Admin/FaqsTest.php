<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Faq;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $this->actingAs($this->admin, 'api');
});

function faqPayload(array $overrides = []): array
{
    return array_merge([
        'audience' => 'guest',
        'question_ar' => 'كيف أحجز؟',
        'question_en' => 'How do I book?',
        'answer_ar' => 'اختر المكان والتواريخ ثم ادفع.',
        'answer_en' => 'Pick a place and dates, then pay.',
        'sort_order' => 1,
    ], $overrides);
}

it('creates, updates and deletes an FAQ', function (): void {
    $this->post(route('admin.faqs.store'), faqPayload())
        ->assertRedirect(route('admin.faqs.index'));

    $faq = Faq::query()->firstOrFail();
    expect($faq->audience->value)->toBe('guest')
        ->and($faq->question_ar)->toBe('كيف أحجز؟')
        ->and($faq->sort_order)->toBe(1);

    $this->put(route('admin.faqs.update', $faq), faqPayload(['audience' => 'host', 'question_ar' => 'كيف أستلم أرباحي؟']))
        ->assertRedirect(route('admin.faqs.index'));
    expect($faq->refresh()->audience->value)->toBe('host')
        ->and($faq->question_ar)->toBe('كيف أستلم أرباحي؟');

    $this->delete(route('admin.faqs.destroy', $faq))->assertRedirect(route('admin.faqs.index'));
    expect(Faq::query()->count())->toBe(0);
});

it('validates required fields and the audience enum', function (): void {
    $this->post(route('admin.faqs.store'), faqPayload(['audience' => 'aliens', 'question_ar' => '', 'answer_ar' => '']))
        ->assertSessionHasErrors(['audience', 'question_ar', 'answer_ar']);

    expect(Faq::query()->count())->toBe(0);
});

it('lists FAQs grouped by audience on the admin index', function (): void {
    Faq::query()->create(faqPayload(['question_ar' => 'سؤال الضيف']));
    Faq::query()->create(faqPayload(['audience' => 'host', 'question_ar' => 'سؤال المضيف']));

    $this->get(route('admin.faqs.index'))
        ->assertOk()
        ->assertSee('سؤال الضيف', escape: false)
        ->assertSee('سؤال المضيف', escape: false);
});

it('is admin-only', function (): void {
    $guest = User::factory()->create(['phone' => '519700001']);

    // The admin middleware bounces non-admins away (redirect, not 403).
    $this->actingAs($guest, 'api')
        ->get(route('admin.faqs.index'))
        ->assertRedirect();

    expect(Faq::query()->count())->toBe(0);
});

// ─── public /faq page ────────────────────────────────────────────────────────

it('shows both audiences as two sections on one public page, each in sort order', function (): void {
    auth('api')->logout(); // page must be public

    Faq::query()->create(faqPayload(['question_ar' => 'الثاني للضيوف', 'sort_order' => 5]));
    Faq::query()->create(faqPayload(['question_ar' => 'الأول للضيوف', 'sort_order' => 1]));
    Faq::query()->create(faqPayload(['audience' => 'host', 'question_ar' => 'سؤال للمضيفين']));

    // Guests section first (with its FAQs ordered), hosts section below.
    $this->get('/faq')
        ->assertOk()
        ->assertSeeInOrder(['الأول للضيوف', 'الثاني للضيوف', 'سؤال للمضيفين'], escape: false)
        ->assertSee('id="guest"', escape: false)
        ->assertSee('id="host"', escape: false);
});
