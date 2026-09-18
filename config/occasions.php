<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Occasions wizard catalogue
|--------------------------------------------------------------------------
|
| The options the occasions wizard offers, in display order. The web wizard
| renders from here and the admin table labels stored keys from here.
|
| KEYS ARE A CONTRACT. The mobile app holds its own copy in
| constants/occasions.ts and submits these exact strings; they are what lands
| in occasion_requests. Add freely, but never rename or delete a key — old
| rows still carry it, and the admin table would stop being able to label them.
|
*/

return [

    // Step 1 — what they're celebrating (single select, 3x3 grid).
    'types' => [
        'birthday' => ['ar' => 'عيد ميلاد', 'en' => 'Birthday', 'emoji' => '🎂'],
        'graduation' => ['ar' => 'تخرّج', 'en' => 'Graduation', 'emoji' => '🎓'],
        'anniversary' => ['ar' => 'ذكرى سنوية', 'en' => 'Anniversary', 'emoji' => '❤️'],
        'wedding' => ['ar' => 'زواج', 'en' => 'Wedding', 'emoji' => '💍'],
        'engagement' => ['ar' => 'خطوبة', 'en' => 'Engagement', 'emoji' => '💐'],
        'gathering' => ['ar' => 'تجمّع', 'en' => 'Gathering', 'emoji' => '🎊'],
        'promotion' => ['ar' => 'ترقية', 'en' => 'Promotion', 'emoji' => '🎖️'],
        'national_day' => ['ar' => 'اليوم الوطني', 'en' => 'National Day', 'emoji' => '🇸🇦'],
        // Reveals a free-text field -> details.occasion_type_other
        'other' => ['ar' => 'أخرى', 'en' => 'Other', 'emoji' => '✨'],
    ],

    // Step 2 — what they're picturing (multi select, grouped chips, optional).
    'need_groups' => [
        'food' => ['ar' => 'الطعام والضيافة', 'en' => 'Food & hospitality'],
        'decorations' => ['ar' => 'الديكور والتنسيق', 'en' => 'Decorations'],
        'entertainment' => ['ar' => 'الترفيه والتصوير', 'en' => 'Entertainment & media'],
        'other' => ['ar' => 'غير ذلك', 'en' => 'Other'],
    ],

    'needs' => [
        'coffee' => ['group' => 'food', 'ar' => 'صبّابين', 'en' => 'Coffee service', 'emoji' => '☕'],
        'hospitality' => ['group' => 'food', 'ar' => 'ضيافة', 'en' => 'Hospitality', 'emoji' => '🍽️'],
        'dinner' => ['group' => 'food', 'ar' => 'عشاء', 'en' => 'Dinner', 'emoji' => '🍛'],
        'styling' => ['group' => 'decorations', 'ar' => 'تنسيق', 'en' => 'Styling', 'emoji' => '🎀'],
        'mirrors' => ['group' => 'decorations', 'ar' => 'مرايا', 'en' => 'Mirrors', 'emoji' => '🪞'],
        'lighting' => ['group' => 'decorations', 'ar' => 'إضاءات', 'en' => 'Lighting', 'emoji' => '💡'],
        'florals' => ['group' => 'decorations', 'ar' => 'تنسيق ورد', 'en' => 'Floral styling', 'emoji' => '💐'],
        'photography' => ['group' => 'entertainment', 'ar' => 'تصوير', 'en' => 'Photography', 'emoji' => '📸'],
        'sound' => ['group' => 'entertainment', 'ar' => 'صوتيات', 'en' => 'Sound', 'emoji' => '🎵'],
        'dj' => ['group' => 'entertainment', 'ar' => 'دي جي', 'en' => 'DJ', 'emoji' => '🎧'],
        'activities' => ['group' => 'entertainment', 'ar' => 'فعاليات وألعاب', 'en' => 'Activities', 'emoji' => '🎪'],
        // Reveals a free-text field -> details.needs_other
        'other' => ['group' => 'other', 'ar' => 'شيء آخر', 'en' => 'Something else', 'emoji' => '✨'],
    ],

    // Step 3 — quick-pick guest counts beside the number input.
    'guest_presets' => [5, 10, 20, 50, 100, 200, 300, 400, 500, 1000],

    // Step 4 — do they already have somewhere to hold it?
    'venue_statuses' => [
        'have' => ['ar' => 'لديّ مكان بالفعل', 'en' => 'I already have a place'],
        'help' => ['ar' => 'ساعدوني في إيجاد مكان', 'en' => 'Help me find a place'],
    ],
];
