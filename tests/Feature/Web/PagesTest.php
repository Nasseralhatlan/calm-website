<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;

it('renders each static content page in Arabic by default', function (string $path, string $heading): void {
    $this->get($path)
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee($heading, false);
})->with([
    'about' => ['/about', 'عن كالم'],
    'terms' => ['/terms', 'الشروط والأحكام'],
    'privacy' => ['/privacy', 'سياسة الخصوصية'],
    'cancellation' => ['/cancellation-policy', 'سياسة الإلغاء والاسترداد'],
    'community' => ['/community-standards', 'معايير المجتمع'],
]);

// Force the English locale (skip SetLocale, which would re-apply the cookie /
// Arabic default) to exercise the English render of each bilingual view.
it('renders each static content page in English', function (string $path, string $heading): void {
    $this->app->setLocale('en');

    $this->withoutMiddleware(SetLocale::class)
        ->get($path)
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('dir="ltr"', false)
        ->assertSee($heading);
})->with([
    'about' => ['/about', 'About Calm'],
    'terms' => ['/terms', 'Terms & Conditions'],
    'privacy' => ['/privacy', 'Privacy Policy'],
    'cancellation' => ['/cancellation-policy', 'Cancellation & Refunds'],
    'community' => ['/community-standards', 'Community Standards'],
]);
