<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttributePhotoRule;
use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * English names of attributes that hold a count rather than a yes/no flag.
     * Rooms, bathrooms, beds, courts, fields — anything you'd ask "how many?" of.
     */
    private const COUNTABLE_ATTRIBUTES = [
        // Accommodation — every room/bed type
        'Bedroom', 'Master Bedroom',
        'Twin Beds', 'Single Beds', 'Sofa Bed',
        'Bathroom', 'Guest Bathroom',
        'Majlis', "Women's Majlis", "Men's Majlis",
        'Living Room', 'Dining Room', 'Kitchen',
        'Maid Room', 'Driver Room',
        // Outdoor — anything that can be more than one of
        'Tent', 'Luxury Tent',
        // Activities — pools, courts, fields, etc.
        'Swimming Pool', 'Kids Pool', 'Jacuzzi', 'Cinema Room',
        'Football Field', 'Volleyball Court', 'Basketball Hoop',
    ];

    public function run(): void
    {
        foreach ($this->catalog() as $groupSpec) {
            $group = AttributeGroup::query()->updateOrCreate(
                ['name_en' => $groupSpec['name_en']],
                ['name_ar' => $groupSpec['name_ar']],
            );

            foreach ($groupSpec['attributes'] as $spec) {
                $type = in_array($spec['name_en'], self::COUNTABLE_ATTRIBUTES, true)
                    ? AttributeType::Number
                    : AttributeType::Boolean;

                Attribute::query()->updateOrCreate(
                    ['group_id' => $group->id, 'name_en' => $spec['name_en']],
                    [
                        'name_ar' => $spec['name_ar'],
                        'icon' => $spec['emoji'],
                        'photo_rule' => $this->photoRule($spec['photo'])->value,
                        'type' => $type->value,
                        'options' => null,
                    ],
                );
            }
        }
    }

    private function photoRule(string $raw): AttributePhotoRule
    {
        return match ($raw) {
            'required' => AttributePhotoRule::Required,
            'optional' => AttributePhotoRule::Optional,
            default => AttributePhotoRule::None,
        };
    }

    /**
     * @return array<int, array{name_en: string, name_ar: string, attributes: array<int, array{emoji: string, name_en: string, name_ar: string, photo: string}>}>
     */
    private function catalog(): array
    {
        return [
            [
                'name_en' => 'Accommodation',
                'name_ar' => 'الإقامة',
                'attributes' => [
                    ['emoji' => '🛏️', 'name_en' => 'Bedroom',          'name_ar' => 'غرفة نوم',        'photo' => 'required'],
                    ['emoji' => '👑',  'name_en' => 'Master Bedroom',   'name_ar' => 'غرفة نوم رئيسية',  'photo' => 'required'],
                    ['emoji' => '🛌',  'name_en' => 'Twin Beds',        'name_ar' => 'سريرين مفردين',    'photo' => 'optional'],
                    ['emoji' => '🛏️', 'name_en' => 'Single Beds',      'name_ar' => 'أسرّة مفردة',      'photo' => 'optional'],
                    ['emoji' => '🛋️', 'name_en' => 'Sofa Bed',         'name_ar' => 'سرير أريكة',       'photo' => 'optional'],
                    ['emoji' => '🚿',  'name_en' => 'Bathroom',         'name_ar' => 'حمام',             'photo' => 'required'],
                    ['emoji' => '🚻',  'name_en' => 'Guest Bathroom',   'name_ar' => 'حمام ضيوف',        'photo' => 'optional'],
                    ['emoji' => '🛋️', 'name_en' => 'Majlis',           'name_ar' => 'مجلس',             'photo' => 'required'],
                    ['emoji' => '👩',  'name_en' => "Women's Majlis",   'name_ar' => 'مجلس نساء',        'photo' => 'optional'],
                    ['emoji' => '👨',  'name_en' => "Men's Majlis",     'name_ar' => 'مجلس رجال',        'photo' => 'optional'],
                    ['emoji' => '🛋️', 'name_en' => 'Living Room',      'name_ar' => 'صالة معيشة',       'photo' => 'required'],
                    ['emoji' => '🍽️', 'name_en' => 'Dining Room',      'name_ar' => 'غرفة طعام',        'photo' => 'optional'],
                    ['emoji' => '🍳',  'name_en' => 'Kitchen',          'name_ar' => 'مطبخ',             'photo' => 'required'],
                    ['emoji' => '🧹',  'name_en' => 'Maid Room',        'name_ar' => 'غرفة عاملة',       'photo' => 'optional'],
                    ['emoji' => '🚗',  'name_en' => 'Driver Room',      'name_ar' => 'غرفة سائق',        'photo' => 'optional'],
                ],
            ],
            [
                'name_en' => 'Outdoor Features',
                'name_ar' => 'المرافق الخارجية',
                'attributes' => [
                    ['emoji' => '🌳',  'name_en' => 'Green Areas',          'name_ar' => 'مسطحات خضراء',     'photo' => 'required'],
                    ['emoji' => '🪑',  'name_en' => 'Outdoor Seating',      'name_ar' => 'جلسات خارجية',     'photo' => 'required'],
                    ['emoji' => '🌅',  'name_en' => 'Terrace',              'name_ar' => 'تراس',             'photo' => 'required'],
                    ['emoji' => '🍖',  'name_en' => 'BBQ Area',             'name_ar' => 'منطقة شواء',       'photo' => 'optional'],
                    ['emoji' => '🔥',  'name_en' => 'Fire Pit',             'name_ar' => 'مشب',              'photo' => 'required'],
                    ['emoji' => '🌴',  'name_en' => 'Palm Trees',           'name_ar' => 'نخيل',             'photo' => 'optional'],
                    ['emoji' => '🌺',  'name_en' => 'Garden',               'name_ar' => 'حديقة',            'photo' => 'required'],
                    ['emoji' => '🍽️', 'name_en' => 'Outdoor Dining Area', 'name_ar' => 'منطقة طعام خارجية', 'photo' => 'optional'],
                    ['emoji' => '⛲',  'name_en' => 'Water Fountain',       'name_ar' => 'نافورة',           'photo' => 'optional'],
                    ['emoji' => '🌿',  'name_en' => 'Pergola',              'name_ar' => 'عريشة',            'photo' => 'optional'],
                    ['emoji' => '🚶',  'name_en' => 'Walking Paths',        'name_ar' => 'ممرات للمشي',      'photo' => 'optional'],
                    ['emoji' => '🪟',  'name_en' => 'Glass House',          'name_ar' => 'بيت زجاجي',        'photo' => 'required'],
                    ['emoji' => '⛺',  'name_en' => 'Tent',                 'name_ar' => 'خيمة',             'photo' => 'required'],
                    ['emoji' => '✨',  'name_en' => 'Luxury Tent',          'name_ar' => 'خيمة فاخرة',       'photo' => 'required'],
                ],
            ],
            [
                'name_en' => 'Activities & Amenities',
                'name_ar' => 'الأنشطة والتجهيزات',
                'attributes' => [
                    ['emoji' => '🏊',  'name_en' => 'Swimming Pool',             'name_ar' => 'مسبح',               'photo' => 'required'],
                    ['emoji' => '👶',  'name_en' => 'Kids Pool',                 'name_ar' => 'مسبح أطفال',         'photo' => 'required'],
                    ['emoji' => '♨️',  'name_en' => 'Jacuzzi',                   'name_ar' => 'جاكوزي',             'photo' => 'required'],
                    ['emoji' => '📺',  'name_en' => 'Smart TV',                  'name_ar' => 'تلفاز ذكي',          'photo' => 'optional'],
                    ['emoji' => '🎮',  'name_en' => 'PlayStation',               'name_ar' => 'بلايستيشن',          'photo' => 'optional'],
                    ['emoji' => '🕹️', 'name_en' => 'Xbox',                      'name_ar' => 'إكس بوكس',           'photo' => 'optional'],
                    ['emoji' => '🎱',  'name_en' => 'Billiards',                 'name_ar' => 'بلياردو',            'photo' => 'required'],
                    ['emoji' => '🏓',  'name_en' => 'Table Tennis',              'name_ar' => 'تنس طاولة',          'photo' => 'required'],
                    ['emoji' => '⚽',  'name_en' => 'Foosball',                  'name_ar' => 'كرة قدم طاولة',      'photo' => 'required'],
                    ['emoji' => '🎬',  'name_en' => 'Cinema Room',               'name_ar' => 'غرفة سينما',         'photo' => 'required'],
                    ['emoji' => '🔊',  'name_en' => 'Sound System',              'name_ar' => 'نظام صوتي',          'photo' => 'optional'],
                    ['emoji' => '📺',  'name_en' => 'Sports Match Broadcasting', 'name_ar' => 'بث مباريات',         'photo' => 'required'],
                    ['emoji' => '🎞️', 'name_en' => 'Netflix',                   'name_ar' => 'نتفليكس',            'photo' => 'optional'],
                    ['emoji' => '▶️',  'name_en' => 'Shahid VIP',                'name_ar' => 'شاهد VIP',           'photo' => 'optional'],
                    ['emoji' => '♟️',  'name_en' => 'Board Games',               'name_ar' => 'ألعاب جماعية',       'photo' => 'optional'],
                    ['emoji' => '📶',  'name_en' => 'WiFi',                      'name_ar' => 'واي فاي',            'photo' => 'none'],
                    ['emoji' => '🚀',  'name_en' => 'High-Speed Internet',       'name_ar' => 'إنترنت عالي السرعة', 'photo' => 'none'],
                    ['emoji' => '❄️',  'name_en' => 'Air Conditioning',          'name_ar' => 'تكييف',              'photo' => 'none'],
                    ['emoji' => '🔥',  'name_en' => 'Heating',                   'name_ar' => 'تدفئة',              'photo' => 'none'],
                    ['emoji' => '☕',  'name_en' => 'Coffee Machine',            'name_ar' => 'ماكينة قهوة',        'photo' => 'optional'],
                    ['emoji' => '🍵',  'name_en' => 'Tea Corner',                'name_ar' => 'ركن شاي',            'photo' => 'optional'],
                    ['emoji' => '🍲',  'name_en' => 'Microwave',                 'name_ar' => 'مايكرويف',           'photo' => 'optional'],
                    ['emoji' => '🔥',  'name_en' => 'Oven',                      'name_ar' => 'فرن',                'photo' => 'optional'],
                    ['emoji' => '🧊',  'name_en' => 'Refrigerator',              'name_ar' => 'ثلاجة',              'photo' => 'optional'],
                    ['emoji' => '❄️',  'name_en' => 'Freezer',                   'name_ar' => 'فريزر',              'photo' => 'optional'],
                    ['emoji' => '🧺',  'name_en' => 'Washing Machine',           'name_ar' => 'غسالة',              'photo' => 'optional'],
                    ['emoji' => '🌬️', 'name_en' => 'Dryer',                     'name_ar' => 'نشافة',              'photo' => 'optional'],
                    ['emoji' => '👔',  'name_en' => 'Iron',                      'name_ar' => 'مكواة',              'photo' => 'optional'],
                    ['emoji' => '🙏',  'name_en' => 'Prayer Area',               'name_ar' => 'مصلى',               'photo' => 'optional'],
                    ['emoji' => '🧎',  'name_en' => 'Prayer Mats',               'name_ar' => 'سجاد صلاة',          'photo' => 'none'],
                    ['emoji' => '🏟️', 'name_en' => 'Football Field',            'name_ar' => 'ملعب كرة قدم',       'photo' => 'required'],
                    ['emoji' => '🏐',  'name_en' => 'Volleyball Court',          'name_ar' => 'ملعب كرة طائرة',     'photo' => 'required'],
                    ['emoji' => '🏀',  'name_en' => 'Basketball Hoop',           'name_ar' => 'سلة كرة سلة',        'photo' => 'required'],
                    ['emoji' => '🐎',  'name_en' => 'Horse Riding',              'name_ar' => 'ركوب الخيل',         'photo' => 'required'],
                    ['emoji' => '🐴',  'name_en' => 'Horse Stable',              'name_ar' => 'إسطبل خيل',          'photo' => 'required'],
                    ['emoji' => '🐪',  'name_en' => 'Camel Riding',              'name_ar' => 'ركوب الجمال',        'photo' => 'optional'],
                    ['emoji' => '🐐',  'name_en' => 'Animal Feeding',            'name_ar' => 'إطعام الحيوانات',    'photo' => 'required'],
                    ['emoji' => '🐦',  'name_en' => 'Bird Aviary',               'name_ar' => 'بيت طيور',           'photo' => 'required'],
                    ['emoji' => '🛝',  'name_en' => "Children's Playground",     'name_ar' => 'منطقة ألعاب أطفال',  'photo' => 'required'],
                    ['emoji' => '🏎️', 'name_en' => 'Karting',                   'name_ar' => 'كارتينج',            'photo' => 'required'],
                    ['emoji' => '🚴',  'name_en' => 'Cycling',                   'name_ar' => 'دراجات هوائية',      'photo' => 'optional'],
                ],
            ],
            [
                'name_en' => 'Privacy',
                'name_ar' => 'الخصوصية',
                'attributes' => [
                    ['emoji' => '🔒', 'name_en' => 'Fully Private',              'name_ar' => 'خصوصية كاملة',     'photo' => 'none'],
                    ['emoji' => '🚪', 'name_en' => 'Private Entrance',           'name_ar' => 'مدخل خاص',         'photo' => 'optional'],
                    ['emoji' => '🏊', 'name_en' => 'Private Pool',               'name_ar' => 'مسبح خاص',         'photo' => 'required'],
                    ['emoji' => '👩', 'name_en' => "Separate Women's Section",   'name_ar' => 'قسم نسائي مستقل',  'photo' => 'optional'],
                    ['emoji' => '👨', 'name_en' => "Separate Men's Section",     'name_ar' => 'قسم رجالي مستقل',  'photo' => 'optional'],
                ],
            ],
        ];
    }
}
