<?php
/**
 * استودیو طراحی ملکینو — کاتالوگ، sanitize، CSS امن.
 * ذخیره فقط در جدول settings موجود (بدون ALTER).
 */
declare(strict_types=1);

require_once __DIR__ . '/map-markers.php';

if (!function_exists('melkinoStudioLayouts')) {
    function melkinoStudioLayouts(): array
    {
        return [
            'classic'        => ['name' => 'Classic',        'fa' => 'کلاسیک',          'desc' => 'عکس بالا، اطلاعات زیر تصویر',           'base' => 'photo-top'],
            'luxury'         => ['name' => 'Luxury',         'fa' => 'لوکس',            'desc' => 'حاشیه طلایی و تصویر بزرگ',            'base' => 'photo-top'],
            'modern'         => ['name' => 'Modern',         'fa' => 'مدرن',            'desc' => 'مینیمال با قیمت برجسته',              'base' => 'photo-top'],
            'minimal'        => ['name' => 'Minimal',        'fa' => 'مینیمال',         'desc' => 'فضای خالی، تمرکز روی عکس و قیمت',    'base' => 'photo-top'],
            'horizontal'     => ['name' => 'Horizontal',     'fa' => 'افقی',            'desc' => 'عکس سمت چپ، اطلاعات سمت راست',        'base' => 'photo-left'],
            'split'          => ['name' => 'Split',          'fa' => 'دو ستونه',        'desc' => 'تقسیم ۵۰／۵۰ عکس و متن',               'base' => 'photo-left'],
            'overlay'        => ['name' => 'Full Overlay',   'fa' => 'پوششی',           'desc' => 'عکس تمام کارت، متن روی تصویر',        'base' => 'photo-full'],
            'bottom-overlay' => ['name' => 'Bottom Overlay', 'fa' => 'اورلی پایین',     'desc' => 'اطلاعات روی لبه پایین عکس',           'base' => 'photo-top'],
            'magazine'       => ['name' => 'Magazine',       'fa' => 'مجله‌ای',         'desc' => 'چیدمان ادیتوریال و عنوان بزرگ',       'base' => 'photo-editorial'],
            'floating'       => ['name' => 'Floating',       'fa' => 'شناور',           'desc' => 'عکس جدا و پنل شناور',                 'base' => 'photo-float'],
            'glass'          => ['name' => 'Glass',          'fa' => 'شیشه‌ای',         'desc' => 'پنل شفاف روی تصویر',                  'base' => 'photo-full'],
            'price-focus'    => ['name' => 'Price Focus',    'fa' => 'تمرکز قیمت',      'desc' => 'قیمت خیلی بزرگ، بقیه مینیمال',        'base' => 'photo-top'],
            'image-focus'    => ['name' => 'Image Focus',    'fa' => 'تمرکز تصویر',     'desc' => 'حدود ۷۰٪ کارت تصویر است',            'base' => 'photo-top'],
            'compact'        => ['name' => 'Compact',        'fa' => 'فشرده',           'desc' => 'ارتفاع کم برای لیست شلوغ',            'base' => 'photo-top'],
            'wide'           => ['name' => 'Wide',           'fa' => 'عریض',            'desc' => 'کارت پهن دسکتاپ',                     'base' => 'photo-left'],
            'vertical'       => ['name' => 'Vertical Luxury','fa' => 'عمودی لوکس',      'desc' => 'کارت بلند با عکس بزرگ',               'base' => 'photo-portrait'],
            'vip'            => ['name' => 'VIP',            'fa' => 'ویژه VIP',        'desc' => 'اکسنت طلایی و درخشش ظریف',           'base' => 'photo-top'],
            'featured'       => ['name' => 'Featured',       'fa' => 'ویژه',            'desc' => 'نشان ویژه و دکمه دعوت',               'base' => 'photo-top'],
            'dark'           => ['name' => 'Modern Dark',    'fa' => 'تیره مدرن',       'desc' => 'دارک با طلایی و سبزآبی',             'base' => 'photo-top'],
            'grid'           => ['name' => 'Smart Grid',     'fa' => 'گرید هوشمند',     'desc' => 'مربع، مناسب چند کارت کنار هم',        'base' => 'photo-collage'],
        ];
    }
}

if (!function_exists('melkinoStudioLegacyAlias')) {
    function melkinoStudioLegacyAlias(string $key): string
    {
        $map = [
            'photo-top' => 'classic',
            'photo-full' => 'overlay',
            'photo-left' => 'split',
            'photo-right' => 'horizontal',
            'photo-float' => 'floating',
            'photo-collage' => 'grid',
            'photo-portrait' => 'vertical',
            'photo-editorial' => 'magazine',
        ];
        return $map[$key] ?? $key;
    }
}

if (!function_exists('melkinoMapCardSkins')) {
    /** مدل‌های آمادهٔ کارت نقشه — فقط ظاهر، بدون تغییر ساختار کارت */
    function melkinoMapCardSkins(): array
    {
        return [
            'classic' => ['fa' => 'کلاسیک ملکینو', 'desc' => 'سبز تیره با قاب طلایی', 'preview' => ['bg' => 'linear-gradient(160deg,#10201f,#0c1a19)', 'border' => '#d4af37', 'text' => '#ffffff', 'accent' => '#d4af37']],
            'gold'    => ['fa' => 'طلایی رویال',   'desc' => 'کرم روشن با قاب دوخط طلا', 'preview' => ['bg' => 'linear-gradient(160deg,#fffdf6,#f7efdc)', 'border' => '#d4af37', 'text' => '#153434', 'accent' => '#9a7a14']],
            'noir'    => ['fa' => 'نوآر',          'desc' => 'مشکی مات با خط طلایی نازک', 'preview' => ['bg' => 'linear-gradient(180deg,#141414,#0b0b0b)', 'border' => '#d4af37', 'text' => '#f4f1e8', 'accent' => '#e8c86a']],
            'glass'   => ['fa' => 'شیشه‌ای',        'desc' => 'بلور شفاف روی نقشه', 'preview' => ['bg' => 'linear-gradient(160deg,rgba(12,32,32,.75),rgba(12,32,32,.45))', 'border' => 'rgba(255,255,255,.5)', 'text' => '#f2fbfa', 'accent' => '#ffe9a8']],
            'white'   => ['fa' => 'مینیمال سفید',   'desc' => 'سفید تمیز و خوانا', 'preview' => ['bg' => '#ffffff', 'border' => 'rgba(6,78,78,.2)', 'text' => '#12201f', 'accent' => '#064e4e']],
            'emerald' => ['fa' => 'زمردی',          'desc' => 'گرادیان سبز لوکس', 'preview' => ['bg' => 'linear-gradient(145deg,#064e4e,#0f9b84)', 'border' => '#d4af37', 'text' => '#effffb', 'accent' => '#ffe08a']],
            'strip'   => ['fa' => 'نوار فشرده',     'desc' => 'کم‌ارتفاع، مناسب موبایل', 'preview' => ['bg' => '#0f1e1d', 'border' => 'rgba(212,175,55,.6)', 'text' => '#f2fbfa', 'accent' => '#d4af37']],
            'poster'  => ['fa' => 'پوستری',         'desc' => 'عکس تمام‌عرض بالای کارت', 'preview' => ['bg' => '#101f1e', 'border' => 'rgba(212,175,55,.7)', 'text' => '#f6fffd', 'accent' => '#ffdf8d']],
        ];
    }
}

if (!function_exists('melkinoStudioDefaultTheme')) {
    function melkinoStudioDefaultTheme(): array
    {
        return [
            'version' => 1,
            'name' => 'Melkino Luxury',
            'layout' => 'classic',
            'favorites' => ['classic', 'luxury', 'modern'],
            'custom' => [],
            'order' => ['image', 'badge', 'title', 'location', 'features', 'price', 'amen', 'button'],
            'elements' => [
                'image' => [
                    'show' => true, 'ratio' => '16/10', 'height' => 210, 'radius' => 0,
                    'fit' => 'cover', 'overlay' => 'none', 'opacity' => 1, 'brightness' => 1, 'contrast' => 1,
                    'posX' => 50, 'posY' => 50,
                ],
                'badge' => [
                    'show' => true, 'pos' => 'tr', 'radius' => 999, 'opacity' => 1,
                ],
                'title' => [
                    'show' => true, 'size' => 14, 'weight' => 800, 'align' => 'right', 'mb' => 5, 'color' => '',
                ],
                'price' => [
                    'show' => true, 'size' => 14, 'weight' => 900, 'align' => 'left', 'color' => '',
                    'prefix' => '', 'suffix' => ' تومان',
                ],
                'features' => [
                    'show' => true,
                    'items' => ['area' => true, 'rooms' => true, 'floor' => true, 'year' => true],
                ],
                'button' => ['show' => true],
                'favorite' => ['show' => true],
                'location' => ['show' => true],
                'amen' => ['show' => true],
            ],
            'card' => [
                'radius' => 16,
                'shadow' => 'soft',
                'border' => 'subtle',
                'hover' => 'lift',
                'hoverMs' => 200,
                'bg' => '',
                'style' => 'soft',
            ],
            'grid' => ['desktop' => 2, 'tablet' => 2, 'mobile' => 1],
            // کارت بازشونده روی نقشه (mkMapSheet) — طراحی‌پذیر از استودیو
            'mapCard' => [
                'skin' => 'classic',
                'width' => 360,
                'radius' => 18,
                'imageHeight' => 110,
                'shadow' => 'strong',
                'pos' => 'bottom',
                'bg' => '',
                'showImage' => true,
                'showKind' => true,
                'showAmen' => true,
                'showPrice' => true,
                'showButton' => true,
                'buttonText' => 'مشاهده فایل',
            ],
            // شکل و رنگ مارکر روی نقشه
            'marker' => [
                'shape' => 'pin',
                'size' => 34,
                'color' => '',
                'vipColor' => '#D4AF37',
                'stroke' => '#FFFFFF',
                'shadow' => true,
                'pulse' => false,
                'label' => 'none',
            ],
            'colors' => [
                'primary' => '#064E4E',
                'secondary' => '#0F766E',
                'gold' => '#D4AF37',
                'background' => '#FAFAF7',
                'surface' => '#FFFFFF',
                'title' => '',
                'price' => '',
                'border' => '',
            ],
            'darkColors' => [
                'primary' => '#72D2CC',
                'secondary' => '#0F766E',
                'gold' => '#D4AF37',
                'background' => '#0B1717',
                'surface' => '#142525',
                'title' => '',
                'price' => '',
                'border' => '',
            ],
            'mode' => 'system',
            'preset' => 'teal',
            'deviceOverrides' => [
                'mobile' => ['imageHeight' => 180, 'titleSize' => 13, 'padding' => 10],
                'tablet' => ['imageHeight' => 200, 'titleSize' => 14, 'padding' => 12],
                'desktop' => ['imageHeight' => 210, 'titleSize' => 14, 'padding' => 12],
            ],
        ];
    }
}

if (!function_exists('melkinoStudioHex')) {
    function melkinoStudioHex($v, string $fallback = ''): string
    {
        $s = strtoupper(trim((string) $v));
        if ($s === '') {
            return $fallback;
        }
        if (preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6}|[0-9A-F]{8})$/', $s)) {
            return $s;
        }
        return $fallback;
    }
}

if (!function_exists('melkinoStudioSanitize')) {
    function melkinoStudioSanitize($raw): array
    {
        $base = melkinoStudioDefaultTheme();
        if (!is_array($raw)) {
            return $base;
        }
        $layouts = array_keys(melkinoStudioLayouts());
        $out = $base;

        $out['version'] = 1;
        $name = trim(preg_replace('/[<>\x00-\x1F]/', '', (string) ($raw['name'] ?? $base['name'])));
        $out['name'] = $name !== '' ? mb_substr($name, 0, 80) : $base['name'];

        $layout = (string) ($raw['layout'] ?? 'classic');
        $layout = melkinoStudioLegacyAlias($layout);
        $out['layout'] = in_array($layout, $layouts, true) ? $layout : 'classic';

        $fav = [];
        if (isset($raw['favorites']) && is_array($raw['favorites'])) {
            foreach ($raw['favorites'] as $f) {
                $f = melkinoStudioLegacyAlias((string) $f);
                if (in_array($f, $layouts, true) && !in_array($f, $fav, true)) {
                    $fav[] = $f;
                }
            }
        }
        $out['favorites'] = array_slice($fav, 0, 12);

        $allowedEl = ['image', 'badge', 'title', 'location', 'features', 'price', 'amen', 'button'];
        $order = [];
        if (isset($raw['order']) && is_array($raw['order'])) {
            foreach ($raw['order'] as $el) {
                $el = (string) $el;
                if (in_array($el, $allowedEl, true) && !in_array($el, $order, true)) {
                    $order[] = $el;
                }
            }
        }
        foreach ($allowedEl as $el) {
            if (!in_array($el, $order, true)) {
                $order[] = $el;
            }
        }
        $out['order'] = $order;

        $elIn = is_array($raw['elements'] ?? null) ? $raw['elements'] : [];
        $img = is_array($elIn['image'] ?? null) ? $elIn['image'] : [];
        $ratios = ['1/1', '4/3', '3/2', '16/9', '16/10', '4/5', 'custom'];
        $fits = ['cover', 'contain', 'fill'];
        $overlays = ['none', 'dark', 'gradient', 'soft', 'custom'];
        $out['elements']['image'] = [
            'show' => !isset($img['show']) || (bool) $img['show'],
            'ratio' => in_array((string) ($img['ratio'] ?? '16/10'), $ratios, true) ? (string) $img['ratio'] : '16/10',
            'height' => max(80, min(480, (int) ($img['height'] ?? 210))),
            'radius' => max(0, min(48, (int) ($img['radius'] ?? 0))),
            'fit' => in_array((string) ($img['fit'] ?? 'cover'), $fits, true) ? (string) $img['fit'] : 'cover',
            'overlay' => in_array((string) ($img['overlay'] ?? 'none'), $overlays, true) ? (string) $img['overlay'] : 'none',
            'opacity' => max(0.2, min(1, (float) ($img['opacity'] ?? 1))),
            'brightness' => max(0.4, min(1.8, (float) ($img['brightness'] ?? 1))),
            'contrast' => max(0.4, min(1.8, (float) ($img['contrast'] ?? 1))),
            'posX' => max(0, min(100, (int) ($img['posX'] ?? 50))),
            'posY' => max(0, min(100, (int) ($img['posY'] ?? 50))),
        ];
        $bdg = is_array($elIn['badge'] ?? null) ? $elIn['badge'] : [];
        $pos = ['tr', 'tl', 'br', 'bl'];
        $out['elements']['badge'] = [
            'show' => !isset($bdg['show']) || (bool) $bdg['show'],
            'pos' => in_array((string) ($bdg['pos'] ?? 'tr'), $pos, true) ? (string) $bdg['pos'] : 'tr',
            'radius' => max(0, min(999, (int) ($bdg['radius'] ?? 999))),
            'opacity' => max(0.2, min(1, (float) ($bdg['opacity'] ?? 1))),
        ];
        $ttl = is_array($elIn['title'] ?? null) ? $elIn['title'] : [];
        $aligns = ['right', 'left', 'center'];
        $out['elements']['title'] = [
            'show' => !isset($ttl['show']) || (bool) $ttl['show'],
            'size' => max(11, min(28, (int) ($ttl['size'] ?? 14))),
            'weight' => max(400, min(900, (int) ($ttl['weight'] ?? 800))),
            'align' => in_array((string) ($ttl['align'] ?? 'right'), $aligns, true) ? (string) $ttl['align'] : 'right',
            'mb' => max(0, min(32, (int) ($ttl['mb'] ?? 5))),
            'color' => melkinoStudioHex($ttl['color'] ?? '', ''),
        ];
        $prc = is_array($elIn['price'] ?? null) ? $elIn['price'] : [];
        $pfx = trim(preg_replace('/[<>\x00-\x1F]/', '', (string) ($prc['prefix'] ?? '')));
        $sfx = trim(preg_replace('/[<>\x00-\x1F]/', '', (string) ($prc['suffix'] ?? ' تومان')));
        $out['elements']['price'] = [
            'show' => !isset($prc['show']) || (bool) $prc['show'],
            'size' => max(11, min(36, (int) ($prc['size'] ?? 14))),
            'weight' => max(400, min(900, (int) ($prc['weight'] ?? 900))),
            'align' => in_array((string) ($prc['align'] ?? 'left'), $aligns, true) ? (string) $prc['align'] : 'left',
            'color' => melkinoStudioHex($prc['color'] ?? '', ''),
            'prefix' => mb_substr($pfx, 0, 24),
            'suffix' => mb_substr($sfx !== '' ? $sfx : ' تومان', 0, 24),
        ];
        $ft = is_array($elIn['features'] ?? null) ? $elIn['features'] : [];
        $items = is_array($ft['items'] ?? null) ? $ft['items'] : [];
        $out['elements']['features'] = [
            'show' => !isset($ft['show']) || (bool) $ft['show'],
            'items' => [
                'area' => !isset($items['area']) || (bool) $items['area'],
                'rooms' => !isset($items['rooms']) || (bool) $items['rooms'],
                'floor' => !isset($items['floor']) || (bool) $items['floor'],
                'year' => !isset($items['year']) || (bool) $items['year'],
            ],
        ];
        foreach (['button', 'favorite', 'location', 'amen'] as $k) {
            $row = is_array($elIn[$k] ?? null) ? $elIn[$k] : [];
            $out['elements'][$k] = ['show' => !isset($row['show']) || (bool) $row['show']];
        }

        $card = is_array($raw['card'] ?? null) ? $raw['card'] : [];
        $shadows = ['none', 'soft', 'medium', 'strong', 'floating', 'luxury'];
        $borders = ['none', 'subtle', 'solid', 'gold', 'gradient'];
        $hovers = ['none', 'lift', 'zoom', 'shadow', 'glow', 'overlay', 'scale'];
        $styles = ['minimal', 'soft', 'luxury', 'modern', 'glass'];
        $out['card'] = [
            'radius' => max(0, min(40, (int) ($card['radius'] ?? 16))),
            'shadow' => in_array((string) ($card['shadow'] ?? 'soft'), $shadows, true) ? (string) $card['shadow'] : 'soft',
            'border' => in_array((string) ($card['border'] ?? 'subtle'), $borders, true) ? (string) $card['border'] : 'subtle',
            'hover' => in_array((string) ($card['hover'] ?? 'lift'), $hovers, true) ? (string) $card['hover'] : 'lift',
            'hoverMs' => max(80, min(800, (int) ($card['hoverMs'] ?? 200))),
            'bg' => melkinoStudioHex($card['bg'] ?? '', ''),
            'style' => in_array((string) ($card['style'] ?? 'soft'), $styles, true) ? (string) $card['style'] : 'soft',
        ];

        $grid = is_array($raw['grid'] ?? null) ? $raw['grid'] : [];
        $out['grid'] = [
            'desktop' => max(1, min(4, (int) ($grid['desktop'] ?? 2))),
            'tablet' => max(1, min(3, (int) ($grid['tablet'] ?? 2))),
            'mobile' => max(1, min(2, (int) ($grid['mobile'] ?? 1))),
        ];

        $mc = is_array($raw['mapCard'] ?? null) ? $raw['mapCard'] : [];
        $btnText = trim(preg_replace('/[<>\x00-\x1F]/', '', (string) ($mc['buttonText'] ?? 'مشاهده فایل')));
        $skins = array_keys(melkinoMapCardSkins());
        $out['mapCard'] = [
            'skin' => in_array((string) ($mc['skin'] ?? 'classic'), $skins, true) ? (string) $mc['skin'] : 'classic',
            'width' => max(240, min(520, (int) ($mc['width'] ?? 360))),
            'radius' => max(0, min(40, (int) ($mc['radius'] ?? 18))),
            'imageHeight' => max(0, min(240, (int) ($mc['imageHeight'] ?? 110))),
            'shadow' => in_array((string) ($mc['shadow'] ?? 'strong'), $shadows, true) ? (string) $mc['shadow'] : 'strong',
            'pos' => in_array((string) ($mc['pos'] ?? 'bottom'), ['bottom', 'top', 'center'], true) ? (string) $mc['pos'] : 'bottom',
            'bg' => melkinoStudioHex($mc['bg'] ?? '', ''),
            'showImage' => !isset($mc['showImage']) || (bool) $mc['showImage'],
            'showKind' => !isset($mc['showKind']) || (bool) $mc['showKind'],
            'showAmen' => !isset($mc['showAmen']) || (bool) $mc['showAmen'],
            'showPrice' => !isset($mc['showPrice']) || (bool) $mc['showPrice'],
            'showButton' => !isset($mc['showButton']) || (bool) $mc['showButton'],
            'buttonText' => mb_substr($btnText !== '' ? $btnText : 'مشاهده فایل', 0, 30),
        ];

        $mk = is_array($raw['marker'] ?? null) ? $raw['marker'] : [];
        $out['marker'] = [
            'shape' => in_array((string) ($mk['shape'] ?? 'pin'), melkinoMarkerShapeKeys(), true) ? (string) $mk['shape'] : 'pin',
            'size' => max(18, min(64, (int) ($mk['size'] ?? 34))),
            'color' => melkinoStudioHex($mk['color'] ?? '', ''),
            'vipColor' => melkinoStudioHex($mk['vipColor'] ?? '#D4AF37', '#D4AF37'),
            'stroke' => melkinoStudioHex($mk['stroke'] ?? '#FFFFFF', '#FFFFFF'),
            'shadow' => !isset($mk['shadow']) || (bool) $mk['shadow'],
            'pulse' => isset($mk['pulse']) && (bool) $mk['pulse'],
            'label' => in_array((string) ($mk['label'] ?? 'none'), ['none', 'price', 'type'], true) ? (string) $mk['label'] : 'none',
        ];

        foreach (['colors', 'darkColors'] as $ck) {
            $src = is_array($raw[$ck] ?? null) ? $raw[$ck] : [];
            foreach ($base[$ck] as $k => $def) {
                $out[$ck][$k] = melkinoStudioHex($src[$k] ?? $def, $def);
            }
        }

        $mode = (string) ($raw['mode'] ?? 'system');
        $out['mode'] = in_array($mode, ['light', 'dark', 'system'], true) ? $mode : 'system';
        $preset = preg_replace('/[^a-z0-9\-]/i', '', (string) ($raw['preset'] ?? 'teal'));
        $out['preset'] = $preset !== '' ? strtolower($preset) : 'teal';

        $dev = is_array($raw['deviceOverrides'] ?? null) ? $raw['deviceOverrides'] : [];
        foreach (['mobile', 'tablet', 'desktop'] as $d) {
            $row = is_array($dev[$d] ?? null) ? $dev[$d] : [];
            $out['deviceOverrides'][$d] = [
                'imageHeight' => max(80, min(480, (int) ($row['imageHeight'] ?? $base['deviceOverrides'][$d]['imageHeight']))),
                'titleSize' => max(11, min(28, (int) ($row['titleSize'] ?? $base['deviceOverrides'][$d]['titleSize']))),
                'padding' => max(4, min(28, (int) ($row['padding'] ?? $base['deviceOverrides'][$d]['padding']))),
            ];
        }

        $custom = [];
        if (isset($raw['custom']) && is_array($raw['custom'])) {
            foreach (array_slice($raw['custom'], 0, 20) as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $id = preg_replace('/[^a-z0-9\-]/i', '', (string) ($c['id'] ?? ''));
                $nm = trim(preg_replace('/[<>\x00-\x1F]/', '', (string) ($c['name'] ?? '')));
                $based = melkinoStudioLegacyAlias((string) ($c['basedOn'] ?? 'classic'));
                if ($id === '' || $nm === '' || !in_array($based, $layouts, true)) {
                    continue;
                }
                $custom[] = ['id' => substr($id, 0, 40), 'name' => mb_substr($nm, 0, 60), 'basedOn' => $based];
            }
        }
        $out['custom'] = $custom;

        return $out;
    }
}

if (!function_exists('melkinoStudioShadow')) {
    function melkinoStudioShadow(string $key): string
    {
        switch ($key) {
            case 'none': return 'none';
            case 'medium': return '0 10px 28px rgba(6,78,78,.12)';
            case 'strong': return '0 18px 40px rgba(6,78,78,.18)';
            case 'floating': return '0 22px 50px rgba(6,78,78,.16)';
            case 'luxury': return '0 16px 36px rgba(212,175,55,.18)';
            default: return '0 8px 22px rgba(6,78,78,.08)';
        }
    }
}

if (!function_exists('melkinoStudioCss')) {
    function melkinoStudioCss(array $theme): string
    {
        $t = melkinoStudioSanitize($theme);
        $c = $t['colors'];
        $d = $t['darkColors'];
        $el = $t['elements'];
        $card = $t['card'];
        $g = $t['grid'];
        $img = $el['image'];
        $ttl = $el['title'];
        $prc = $el['price'];
        $bdg = $el['badge'];
        $dev = $t['deviceOverrides'];

        $border = '1px solid var(--border)';
        if ($card['border'] === 'none') {
            $border = '0';
        } elseif ($card['border'] === 'solid') {
            $border = '1px solid ' . ($c['border'] ?: $c['primary']);
        } elseif ($card['border'] === 'gold') {
            $border = '1px solid ' . $c['gold'];
        } elseif ($card['border'] === 'gradient') {
            $border = '1px solid ' . $c['gold'];
        }

        $hover = '';
        if ($card['hover'] === 'lift') {
            $hover = 'transform:translateY(-3px)';
        } elseif ($card['hover'] === 'zoom' || $card['hover'] === 'scale') {
            $hover = 'transform:scale(1.015)';
        } elseif ($card['hover'] === 'shadow') {
            $hover = 'box-shadow:0 18px 40px rgba(6,78,78,.16)';
        } elseif ($card['hover'] === 'glow') {
            $hover = 'box-shadow:0 0 0 1px ' . $c['gold'] . ',0 12px 28px rgba(212,175,55,.2)';
        }

        $pos = [
            'tr' => 'top:10px;right:10px;left:auto;bottom:auto',
            'tl' => 'top:10px;left:10px;right:auto;bottom:auto',
            'br' => 'bottom:10px;right:10px;left:auto;top:auto',
            'bl' => 'bottom:10px;left:10px;right:auto;top:auto',
        ][$bdg['pos']] ?? 'top:10px;right:10px;left:auto;bottom:auto';

        $overlay = '';
        if ($img['overlay'] === 'dark') {
            $overlay = 'background:rgba(0,0,0,.28)';
        } elseif ($img['overlay'] === 'gradient') {
            $overlay = 'background:linear-gradient(180deg,transparent 40%,rgba(8,24,24,.72))';
        } elseif ($img['overlay'] === 'soft') {
            $overlay = 'background:linear-gradient(180deg,rgba(6,78,78,.08),transparent 50%)';
        }

        $css = ":root{";
        $css .= "--primary:{$c['primary']};--secondary:{$c['secondary']};--gold:{$c['gold']};";
        $css .= "--bg:{$c['background']};--surface:" . ($card['bg'] ?: $c['surface']) . ";";
        $css .= "--mk-card-radius:{$card['radius']}px;";
        $css .= "--mk-card-shadow:" . melkinoStudioShadow($card['shadow']) . ";";
        $css .= "--mk-title-size:{$ttl['size']}px;--mk-price-size:{$prc['size']}px;";
        $css .= "--mk-img-height:{$img['height']}px;--mk-img-radius:{$img['radius']}px;";
        $css .= "--mk-img-pos:{$img['posX']}% {$img['posY']}%;";
        $css .= "}";
        $css .= "[data-theme=\"dark\"]{--primary:{$d['primary']};--secondary:{$d['secondary']};--gold:{$d['gold']};";
        $css .= "--bg:{$d['background']};--surface:" . ($d['surface']) . ";}";

        $css .= ".property-card{border-radius:var(--mk-card-radius);box-shadow:var(--mk-card-shadow);border:{$border};";
        $css .= "transition:transform {$card['hoverMs']}ms ease,box-shadow {$card['hoverMs']}ms ease;}";
        if ($hover !== '') {
            $css .= ".property-card:hover{{$hover};}";
        }
        $css .= ".property-title{font-size:var(--mk-title-size);font-weight:{$ttl['weight']};text-align:{$ttl['align']};margin-bottom:{$ttl['mb']}px;";
        if ($ttl['color']) {
            $css .= "color:{$ttl['color']};";
        }
        $css .= "}";
        $css .= ".property-price{font-size:var(--mk-price-size);font-weight:{$prc['weight']};text-align:{$prc['align']};";
        if ($prc['color']) {
            $css .= "color:{$prc['color']};";
        }
        $css .= "}";
        $css .= ".property-image{border-radius:var(--mk-img-radius);overflow:hidden;}";
        $css .= ".property-card:not(.mk-l-photo-left):not(.mk-l-photo-right):not(.mk-l-photo-editorial):not(.mk-l-horizontal):not(.mk-l-split):not(.mk-l-wide):not(.mk-l-magazine) .property-image{height:var(--mk-img-height);flex-basis:var(--mk-img-height);}";
        $css .= ".property-image>img{object-fit:{$img['fit']};object-position:{$img['posX']}% {$img['posY']}% !important;opacity:{$img['opacity']};filter:brightness({$img['brightness']}) contrast({$img['contrast']});}";
        $css .= ".property-image .mk-photo-wm{z-index:8!important;pointer-events:none;}";
        if ($overlay !== '') {
            $css .= ".property-image:before{content:\"\";position:absolute;inset:0;pointer-events:none;z-index:2;{$overlay};}";
        }
        $css .= ".property-badges{{$pos};}";
        $css .= ".property-badge{border-radius:{$bdg['radius']}px;opacity:{$bdg['opacity']};}";
        if (!$el['image']['show']) {
            $css .= ".property-image{display:none!important;}";
        }
        if (!$el['badge']['show']) {
            $css .= ".property-badges{display:none!important;}";
        }
        if (!$ttl['show']) {
            $css .= ".property-title{display:none!important;}";
        }
        if (!$prc['show']) {
            $css .= ".property-price{display:none!important;}";
        }
        if (!$el['features']['show']) {
            $css .= ".property-features{display:none!important;}";
        }
        if (!$el['button']['show']) {
            $css .= ".property-detail{display:none!important;}";
        }
        if (!$el['favorite']['show']) {
            $css .= ".property-like{display:none!important;}";
        }
        if (!$el['location']['show']) {
            $css .= ".property-location{display:none!important;}";
        }
        if (!$el['amen']['show']) {
            $css .= ".property-amen-row{display:none!important;}";
        }

        $css .= ".properties-list,.ads-section .properties-list,#mkHomeAdsList.properties-list{grid-template-columns:repeat({$g['desktop']},minmax(0,1fr))!important;}";
        $css .= "@media(max-width:1100px){.properties-list,.ads-section .properties-list,#mkHomeAdsList.properties-list{grid-template-columns:repeat({$g['tablet']},minmax(0,1fr))!important;}";
        $css .= ".property-title{font-size:{$dev['tablet']['titleSize']}px;}}";
        $css .= "@media(max-width:700px){.properties-list,.ads-section .properties-list,#mkHomeAdsList.properties-list{grid-template-columns:repeat({$g['mobile']},minmax(0,1fr))!important;}";
        $css .= ".property-card:not(.mk-l-photo-left):not(.mk-l-photo-right):not(.mk-l-photo-editorial) .property-image{height:{$dev['mobile']['imageHeight']}px;flex-basis:{$dev['mobile']['imageHeight']}px;}";
        $css .= ".property-title{font-size:{$dev['mobile']['titleSize']}px;}.property-content{padding:{$dev['mobile']['padding']}px;}}";

        // ---- کارت روی نقشه ----
        $mc = $t['mapCard'];
        $css .= ":root{--mk-mapcard-w:{$mc['width']}px;--mk-mapcard-radius:{$mc['radius']}px;";
        $css .= "--mk-mapcard-imgh:{$mc['imageHeight']}px;--mk-mapcard-shadow:" . melkinoStudioShadow($mc['shadow']) . ";";
        $css .= "}";
        // زمینه فقط وقتی اعمال می‌شود که ادمین صریحاً رنگ انتخاب کرده باشد،
        // وگرنه رنگ «مدل» انتخاب‌شده (data-skin) در map.css دست‌نخورده می‌ماند.
        $css .= ".mk-map-sheet{max-width:var(--mk-mapcard-w);border-radius:var(--mk-mapcard-radius);box-shadow:var(--mk-mapcard-shadow);}";
        if ($mc['bg'] !== '') {
            $css .= ".mk-map-sheet{background:{$mc['bg']}!important;}";
        }
        if ($mc['imageHeight'] > 0) {
            $css .= ".mk-map-thumb{height:var(--mk-mapcard-imgh);object-fit:cover;border-radius:calc(var(--mk-mapcard-radius) - 6px);}";
        }
        if (!$mc['showImage'] || $mc['imageHeight'] === 0) {
            $css .= ".mk-map-thumb{display:none!important;}";
        }
        if (!$mc['showKind']) {
            $css .= ".mk-map-kind{display:none!important;}";
        }
        if (!$mc['showAmen']) {
            $css .= ".mk-map-sheet .mk-amen-row{display:none!important;}";
        }
        if (!$mc['showPrice']) {
            $css .= ".mk-map-price{display:none!important;}";
        }
        if (!$mc['showButton']) {
            $css .= ".mk-map-sheet .mk-map-btn{display:none!important;}";
        }

        $splitLayouts = ['horizontal', 'split', 'wide', 'magazine'];
        if (in_array($t['layout'], $splitLayouts, true)) {
            $css .= ".properties-list[data-card-layout],#mkHomeAdsList.properties-list{grid-template-columns:1fr!important;}";
        }

        return $css;
    }
}

if (!function_exists('melkinoStudioContrastWarn')) {
    function melkinoStudioContrastWarn(array $theme): bool
    {
        $t = melkinoStudioSanitize($theme);
        $bg = $t['card']['bg'] ?: $t['colors']['surface'];
        $fg = $t['elements']['title']['color'] ?: '#111827';
        $rel = static function (string $hex): float {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if (strlen($hex) < 6) {
                return 0;
            }
            $r = hexdec(substr($hex, 0, 2)) / 255;
            $g = hexdec(substr($hex, 2, 2)) / 255;
            $b = hexdec(substr($hex, 4, 2)) / 255;
            $lin = static function ($c) {
                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            };
            return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
        };
        $l1 = $rel($bg) + 0.05;
        $l2 = $rel($fg) + 0.05;
        $ratio = $l1 > $l2 ? $l1 / $l2 : $l2 / $l1;
        return $ratio < 3.0;
    }
}
