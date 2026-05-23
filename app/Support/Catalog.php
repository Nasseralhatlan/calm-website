<?php

namespace App\Support;

class Catalog
{
    /**
     * Facilities — countable physical spaces commonly found in Saudi
     * chalets, resthouses, camps and farms (المرافق).
     */
    public static function facilities(): array
    {
        return [
            ['key' => 'bedrooms',         'en' => 'Bedrooms',           'ar' => 'غرف نوم'],
            ['key' => 'bathrooms',        'en' => 'Bathrooms',          'ar' => 'دورات مياه'],
            ['key' => 'majlis_men',       'en' => "Men's majlis",       'ar' => 'مجلس رجال'],
            ['key' => 'majlis_women',     'en' => "Women's majlis",     'ar' => 'مجلس نساء'],
            ['key' => 'living_room',      'en' => 'Living rooms',       'ar' => 'صالات معيشة'],
            ['key' => 'dining_hall',      'en' => 'Dining halls',       'ar' => 'صالات طعام'],
            ['key' => 'kitchen',          'en' => 'Kitchens',           'ar' => 'مطابخ'],
            ['key' => 'outdoor_kitchen',  'en' => 'Outdoor kitchens',   'ar' => 'مطابخ خارجية'],
            ['key' => 'pool',             'en' => 'Pools',              'ar' => 'مسابح'],
            ['key' => 'kids_pool',        'en' => 'Kids pools',         'ar' => 'مسابح أطفال'],
            ['key' => 'jacuzzi',          'en' => 'Jacuzzis',           'ar' => 'جاكوزي'],
            ['key' => 'sauna',            'en' => 'Saunas',             'ar' => 'ساونا'],
            ['key' => 'bedouin_tent',     'en' => 'Bedouin tents',      'ar' => 'بيوت شعر'],
            ['key' => 'tent',             'en' => 'Tents',              'ar' => 'خيام'],
            ['key' => 'outdoor_seating',  'en' => 'Outdoor sittings',   'ar' => 'جلسات خارجية'],
            ['key' => 'bbq',              'en' => 'BBQ areas',          'ar' => 'مشاوي'],
            ['key' => 'coffee_corner',    'en' => 'Coffee corners',     'ar' => 'أركان قهوة'],
            ['key' => 'football_court',   'en' => 'Football courts',    'ar' => 'ملاعب كرة قدم'],
            ['key' => 'padel_court',      'en' => 'Padel courts',       'ar' => 'ملاعب بادل'],
            ['key' => 'tennis_court',     'en' => 'Tennis courts',      'ar' => 'ملاعب تنس'],
            ['key' => 'volleyball_court', 'en' => 'Volleyball courts',  'ar' => 'ملاعب كرة طائرة'],
            ['key' => 'kids_playground',  'en' => 'Kids playgrounds',   'ar' => 'ملاعب أطفال'],
            ['key' => 'lawn',             'en' => 'Green areas',        'ar' => 'مسطحات خضراء'],
            ['key' => 'parking',          'en' => 'Parking spots',      'ar' => 'مواقف'],
        ];
    }

    /**
     * Amenities — features / benefits, grouped, tuned for Saudi short-term
     * rental expectations. Each item has an emoji for the chip UI.
     */
    public static function amenityGroups(): array
    {
        return [
            [
                'en' => 'Essentials', 'ar' => 'الأساسيات',
                'items' => [
                    ['key' => 'wifi',            'emoji' => '📶', 'en' => 'Wi-Fi',                'ar' => 'واي فاي'],
                    ['key' => 'ac',              'emoji' => '❄️', 'en' => 'Air conditioning',     'ar' => 'تكييف'],
                    ['key' => 'heating',         'emoji' => '🌡️', 'en' => 'Heating',              'ar' => 'تدفئة'],
                    ['key' => 'water',           'emoji' => '💧', 'en' => 'Drinking water',       'ar' => 'ماء شرب'],
                    ['key' => 'water_dispenser', 'emoji' => '🚰', 'en' => 'Water dispenser',      'ar' => 'برّاد ماء'],
                    ['key' => 'tv',              'emoji' => '📺', 'en' => 'TV',                   'ar' => 'تلفاز'],
                    ['key' => 'fridge',          'emoji' => '🧊', 'en' => 'Refrigerator',         'ar' => 'ثلاجة'],
                    ['key' => 'freezer',         'emoji' => '❄️', 'en' => 'Freezer',              'ar' => 'فريزر'],
                    ['key' => 'microwave',       'emoji' => '🍱', 'en' => 'Microwave',            'ar' => 'مايكروويف'],
                    ['key' => 'oven',            'emoji' => '🥧', 'en' => 'Oven',                 'ar' => 'فرن'],
                    ['key' => 'stove',           'emoji' => '🍳', 'en' => 'Stove',                'ar' => 'موقد طبخ'],
                    ['key' => 'utensils',        'emoji' => '🍴', 'en' => 'Cooking utensils',     'ar' => 'أدوات طبخ'],
                    ['key' => 'coffee_machine',  'emoji' => '☕', 'en' => 'Coffee machine',       'ar' => 'ماكينة قهوة'],
                    ['key' => 'kettle',          'emoji' => '🫖', 'en' => 'Kettle',               'ar' => 'غلاية'],
                    ['key' => 'iron',            'emoji' => '👔', 'en' => 'Iron',                 'ar' => 'مكواة'],
                    ['key' => 'washing_machine', 'emoji' => '🧺', 'en' => 'Washing machine',      'ar' => 'غسالة'],
                    ['key' => 'dryer',           'emoji' => '💨', 'en' => 'Dryer',                'ar' => 'نشافة'],
                    ['key' => 'hair_dryer',      'emoji' => '💇', 'en' => 'Hair dryer',           'ar' => 'مجفف شعر'],
                    ['key' => 'towels',          'emoji' => '🧖', 'en' => 'Fresh towels',         'ar' => 'مناشف نظيفة'],
                    ['key' => 'linens',          'emoji' => '🛏️', 'en' => 'Fresh linens',         'ar' => 'مفارش نظيفة'],
                    ['key' => 'toiletries',      'emoji' => '🧴', 'en' => 'Toiletries',           'ar' => 'مستلزمات استحمام'],
                    ['key' => 'prayer_rugs',     'emoji' => '🕌', 'en' => 'Prayer rugs',          'ar' => 'سجاد صلاة'],
                    ['key' => 'qibla_mark',      'emoji' => '🧭', 'en' => 'Qibla marked',         'ar' => 'اتجاه القبلة محدد'],
                    ['key' => 'quran',           'emoji' => '📖', 'en' => 'Qur\'an copies',       'ar' => 'نسخ من المصحف'],
                ],
            ],
            [
                'en' => 'Outdoor', 'ar' => 'الخارج',
                'items' => [
                    ['key' => 'private_pool',    'emoji' => '🏊', 'en' => 'Private pool',         'ar' => 'مسبح خاص'],
                    ['key' => 'heated_pool',     'emoji' => '♨️', 'en' => 'Heated pool',          'ar' => 'مسبح مسخن'],
                    ['key' => 'garden',          'emoji' => '🌿', 'en' => 'Garden',               'ar' => 'حديقة'],
                    ['key' => 'lawn_chairs',     'emoji' => '🪑', 'en' => 'Lawn seating',         'ar' => 'مقاعد خارجية'],
                    ['key' => 'firepit',         'emoji' => '🔥', 'en' => 'Firepit',              'ar' => 'وجار'],
                    ['key' => 'gazebo',          'emoji' => '⛱️', 'en' => 'Gazebo',               'ar' => 'مظلة'],
                    ['key' => 'balcony',         'emoji' => '🪟', 'en' => 'Balcony',              'ar' => 'شرفة'],
                    ['key' => 'terrace',         'emoji' => '🌅', 'en' => 'Terrace',              'ar' => 'تراس'],
                    ['key' => 'rooftop',         'emoji' => '🏙️', 'en' => 'Rooftop',              'ar' => 'سطح'],
                    ['key' => 'mountain_view',   'emoji' => '⛰️', 'en' => 'Mountain view',        'ar' => 'إطلالة جبلية'],
                    ['key' => 'sea_view',        'emoji' => '🌊', 'en' => 'Sea view',             'ar' => 'إطلالة بحرية'],
                    ['key' => 'desert_view',     'emoji' => '🏜️', 'en' => 'Desert view',          'ar' => 'إطلالة صحراوية'],
                    ['key' => 'farm_view',       'emoji' => '🌾', 'en' => 'Farm view',            'ar' => 'إطلالة مزرعة'],
                    ['key' => 'palm_trees',      'emoji' => '🌴', 'en' => 'Palm trees',           'ar' => 'نخيل'],
                    ['key' => 'shisha',          'emoji' => '💨', 'en' => 'Shisha',               'ar' => 'شيشة'],
                    ['key' => 'outdoor_shower',  'emoji' => '🚿', 'en' => 'Outdoor shower',       'ar' => 'دش خارجي'],
                    ['key' => 'animal_pen',      'emoji' => '🐐', 'en' => 'Animal pen',           'ar' => 'حظيرة حيوانات'],
                ],
            ],
            [
                'en' => 'Family & comfort', 'ar' => 'العائلة والراحة',
                'items' => [
                    ['key' => 'family_only',         'emoji' => '👨‍👩‍👧', 'en' => 'Families only',          'ar' => 'عوائل فقط'],
                    ['key' => 'women_section',       'emoji' => '👩',     'en' => 'Separate women section', 'ar' => 'قسم نسائي مستقل'],
                    ['key' => 'kids_area',           'emoji' => '🧸',     'en' => 'Kids area',              'ar' => 'منطقة أطفال'],
                    ['key' => 'prayer_room',         'emoji' => '🕌',     'en' => 'Prayer room',            'ar' => 'مصلى'],
                    ['key' => 'workspace',           'emoji' => '💻',     'en' => 'Workspace',              'ar' => 'مكان عمل'],
                    ['key' => 'high_chair',          'emoji' => '🍼',     'en' => 'High chair',             'ar' => 'كرسي أطفال'],
                    ['key' => 'crib',                'emoji' => '👶',     'en' => 'Crib',                   'ar' => 'سرير أطفال'],
                    ['key' => 'baby_bath',           'emoji' => '🛁',     'en' => 'Baby bath',              'ar' => 'حوض استحمام أطفال'],
                    ['key' => 'changing_table',      'emoji' => '🍼',     'en' => 'Changing table',         'ar' => 'طاولة تغيير'],
                    ['key' => 'wheelchair_access',   'emoji' => '♿',     'en' => 'Wheelchair accessible',  'ar' => 'مهيأ لذوي الاحتياجات'],
                    ['key' => 'elderly_friendly',    'emoji' => '🧓',     'en' => 'Elderly friendly',       'ar' => 'مناسب لكبار السن'],
                    ['key' => 'pet_friendly',        'emoji' => '🐕',     'en' => 'Pets allowed',           'ar' => 'يسمح بالحيوانات'],
                ],
            ],
            [
                'en' => 'Entertainment', 'ar' => 'الترفيه',
                'items' => [
                    ['key' => 'football_matches', 'emoji' => '📺', 'en' => 'Football matches (TV)', 'ar' => 'بث مباريات الكرة'],
                    ['key' => 'sound_system',  'emoji' => '🔊', 'en' => 'Sound system',  'ar' => 'نظام صوتي'],
                    ['key' => 'billiards',     'emoji' => '🎱', 'en' => 'Billiards',     'ar' => 'بلياردو'],
                    ['key' => 'foosball',      'emoji' => '🥅', 'en' => 'Foosball',      'ar' => 'بيبي فوت'],
                    ['key' => 'table_tennis',  'emoji' => '🏓', 'en' => 'Table tennis',  'ar' => 'تنس طاولة'],
                    ['key' => 'playstation',   'emoji' => '🎮', 'en' => 'PlayStation',   'ar' => 'بلايستيشن'],
                    ['key' => 'cinema_room',   'emoji' => '🎬', 'en' => 'Cinema room',   'ar' => 'غرفة سينما'],
                    ['key' => 'projector',     'emoji' => '📽️', 'en' => 'Projector',     'ar' => 'بروجكتر'],
                    ['key' => 'board_games',   'emoji' => '🎲', 'en' => 'Board games',   'ar' => 'ألعاب طاولة'],
                    ['key' => 'trampoline',    'emoji' => '🤸', 'en' => 'Trampoline',    'ar' => 'ترامبولين'],
                    ['key' => 'bicycles',      'emoji' => '🚲', 'en' => 'Bicycles',      'ar' => 'دراجات'],
                ],
            ],
        ];
    }

    public static function placeTypes(): array
    {
        return [
            ['key' => 'chalet',    'en' => 'Chalet',     'ar' => 'شاليه'],
            ['key' => 'resthouse', 'en' => 'Resthouse',  'ar' => 'استراحة'],
            ['key' => 'camp',      'en' => 'Camp / Farm','ar' => 'مخيم / مزرعة'],
        ];
    }

    public static function facilityLabel(string $key, string $locale): string
    {
        foreach (self::facilities() as $f) {
            if ($f['key'] === $key) {
                return $f[$locale] ?? $f['en'];
            }
        }
        return $key;
    }

    public static function amenityLabel(string $key, string $locale): string
    {
        foreach (self::amenityGroups() as $group) {
            foreach ($group['items'] as $a) {
                if ($a['key'] === $key) {
                    return $a[$locale] ?? $a['en'];
                }
            }
        }
        return $key;
    }

    public static function amenityEmoji(string $key): string
    {
        foreach (self::amenityGroups() as $group) {
            foreach ($group['items'] as $a) {
                if ($a['key'] === $key) {
                    return $a['emoji'] ?? '';
                }
            }
        }
        return '';
    }

    public static function placeTypeLabel(string $key, string $locale): string
    {
        foreach (self::placeTypes() as $t) {
            if ($t['key'] === $key) {
                return $t[$locale] ?? $t['en'];
            }
        }
        return $key;
    }
}
