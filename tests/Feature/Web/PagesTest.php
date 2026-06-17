<?php

declare(strict_types=1);

it('renders each static content page', function (string $path, string $heading): void {
    $this->get($path)
        ->assertOk()
        ->assertSee($heading);
})->with([
    'about' => ['/about', 'عن كالم'],
    'terms' => ['/terms', 'الشروط والأحكام'],
    'privacy' => ['/privacy', 'سياسة الخصوصية'],
    'cancellation' => ['/cancellation-policy', 'سياسة الإلغاء والاسترداد'],
    'community' => ['/community-standards', 'معايير المجتمع'],
]);
