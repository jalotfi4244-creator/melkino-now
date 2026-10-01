<?php
/**
 * تبدیل تاریخ میلادی ↔ شمسی و نمایش وقت تهران.
 * زمان‌های DATETIME دیتابیس با NOW() سرور نوشته می‌شوند؛ این توابع
 * آن‌ها را با اختلاف UTC_TIMESTAMP()/NOW() به Asia/Tehran می‌آورند.
 */

if (!function_exists('melkinoGregorianToJalali')) {
    function melkinoGregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }
}

if (!function_exists('melkinoFaDigits')) {
    function melkinoFaDigits(string $s): string
    {
        return strtr($s, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}

if (!function_exists('melkinoServerOffsetFromUtcSeconds')) {
    function melkinoServerOffsetFromUtcSeconds(): int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = 0;
        global $pdo;
        if ($pdo instanceof PDO) {
            try {
                $cached = (int) $pdo->query('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())')->fetchColumn();
            } catch (Throwable $e) {
                $cached = 0;
            }
        }
        return $cached;
    }
}

if (!function_exists('melkinoMysqlToTehranDateTime')) {
    function melkinoMysqlToTehranDateTime(?string $datetime): ?DateTimeImmutable
    {
        $raw = trim((string) $datetime);
        if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
            return null;
        }
        $raw = substr($raw, 0, 19);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, new DateTimeZone('UTC'));
        if (!$dt) {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($raw, 0, 10), new DateTimeZone('UTC'));
        }
        if (!$dt) {
            return null;
        }
        $offset = melkinoServerOffsetFromUtcSeconds();
        if ($offset !== 0) {
            $dt = $dt->modify((-1 * $offset) . ' seconds');
        }
        try {
            $tz = new DateTimeZone('Asia/Tehran');
        } catch (Throwable $e) {
            $tz = new DateTimeZone('+03:30');
        }
        return $dt->setTimezone($tz);
    }
}

if (!function_exists('melkinoFormatTehranFa')) {
    function melkinoFormatTehranFa($datetime, $withTime = true)
    {
        try {
            $dt = melkinoMysqlToTehranDateTime($datetime === null ? null : (string) $datetime);
            if (!$dt) {
                return '—';
            }
            [$jy, $jm, $jd] = melkinoGregorianToJalali(
                (int) $dt->format('Y'),
                (int) $dt->format('n'),
                (int) $dt->format('j')
            );
            $date = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
            if (!$withTime) {
                return melkinoFaDigits($date);
            }
            return melkinoFaDigits($date . ' - ' . $dt->format('H:i'));
        } catch (Throwable $e) {
            $raw = trim((string) $datetime);
            return $raw !== '' ? $raw : '—';
        }
    }
}

if (!function_exists('melkinoJalaliCurrentYear')) {
    function melkinoJalaliCurrentYear(): int
    {
        static $y = null;
        if ($y !== null) {
            return $y;
        }
        try {
            $dt = new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
            [$jy] = melkinoGregorianToJalali(
                (int) $dt->format('Y'),
                (int) $dt->format('n'),
                (int) $dt->format('j')
            );
            $y = (int) $jy;
        } catch (Throwable $e) {
            $y = 1405;
        }
        return $y;
    }
}

if (!function_exists('melkinoParseBuildYear')) {
    function melkinoParseBuildYear($value): int
    {
        $s = trim((string) $value);
        if ($s === '') {
            return 0;
        }
        if (function_exists('melkinoVisitNormalizeDigits')) {
            $s = melkinoVisitNormalizeDigits($s);
        } else {
            $s = strtr($s, [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);
        }
        if (preg_match('/(13|14)\d{2}/', $s, $m)) {
            return (int) $m[0];
        }
        if (preg_match('/(19|20)\d{2}/', $s, $m)) {
            $gy = (int) $m[0];
            [$jy] = melkinoGregorianToJalali($gy, 6, 21);
            return (int) $jy;
        }
        if (preg_match('/\d{2,4}/', $s, $m)) {
            $n = (int) $m[0];
            if ($n >= 1300 && $n <= 1599) {
                return $n;
            }
            if ($n >= 70 && $n <= 99) {
                return 1300 + $n;
            }
            if ($n >= 0 && $n <= 69) {
                return 1400 + $n;
            }
        }
        return 0;
    }
}

if (!function_exists('melkinoBuildingAge')) {
    /** سن بنا = سال جاری شمسی − سال ساخت. نامعتبر → null */
    function melkinoBuildingAge($year): ?int
    {
        $y = is_int($year) && $year >= 1200 ? $year : melkinoParseBuildYear($year);
        if ($y < 1200 || $y > 1600) {
            return null;
        }
        $age = melkinoJalaliCurrentYear() - $y;
        if ($age < 0) {
            $age = 0;
        }
        if ($age > 200) {
            return null;
        }
        return $age;
    }
}

if (!function_exists('melkinoBuildingAgeDisplay')) {
    function melkinoBuildingAgeDisplay($year): string
    {
        $age = melkinoBuildingAge($year);
        if ($age === null) {
            return '';
        }
        $s = (string) $age;
        return function_exists('melkinoFaDigits') ? melkinoFaDigits($s) : $s;
    }
}

if (!function_exists('melkinoEnsureAdsBuildingAgeColumn')) {
    function melkinoEnsureAdsBuildingAgeColumn(?PDO $pdo = null): void
    {
        if (!($pdo instanceof PDO)) {
            global $pdo;
        }
        if (!($pdo instanceof PDO)) {
            return;
        }
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $has = false;
            foreach ($pdo->query('SHOW COLUMNS FROM ads')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if (strtolower((string) ($c['Field'] ?? '')) === 'building_age') {
                    $has = true;
                    break;
                }
            }
            if (!$has) {
                $pdo->exec('ALTER TABLE ads ADD COLUMN building_age INT NULL DEFAULT NULL');
            }
        } catch (Throwable $e) {
        }
        try {
            $st = $pdo->query("SELECT id, year, property_details FROM ads WHERE building_age IS NULL AND year IS NOT NULL AND year <> '' LIMIT 400");
            $upd = $pdo->prepare('UPDATE ads SET building_age = ? WHERE id = ?');
            foreach ($st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
                $age = melkinoBuildingAge($row['year'] ?? '');
                if ($age === null && !empty($row['property_details'])) {
                    $d = json_decode((string) $row['property_details'], true);
                    if (is_array($d)) {
                        $age = melkinoBuildingAge($d['year'] ?? $d['year_apt'] ?? $d['year_villa'] ?? $d['office_year'] ?? '');
                    }
                }
                if ($age !== null) {
                    $upd->execute([$age, $row['id']]);
                }
            }
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('melkinoBuildingAgeBindScript')) {
    function melkinoBuildingAgeBindScript(string $yearInputId, string $ageInputId = 'regBuildingAge'): string
    {
        $jy = (int) melkinoJalaliCurrentYear();
        $y = json_encode($yearInputId, JSON_UNESCAPED_UNICODE);
        $a = json_encode($ageInputId, JSON_UNESCAPED_UNICODE);
        return '<script>(function(){var Y=' . $jy . ',yi=' . $y . ',ai=' . $a . ';'
            . 'function fa(n){return String(n).replace(/[0-9]/g,function(d){return "۰۱۲۳۴۵۶۷۸۹"[d];});}'
            . 'function norm(s){return String(s||"").replace(/[۰-۹]/g,function(ch){return String("۰۱۲۳۴۵۶۷۸۹".indexOf(ch));}).replace(/[٠-٩]/g,function(ch){return String("٠١٢٣٤٥٦٧٨٩".indexOf(ch));});}'
            . 'function upd(){var el=document.getElementById(ai),inp=document.getElementById(yi);if(!el||!inp)return;var y=parseInt(norm(inp.value),10);if(!y||y<1200||y>1600){el.value="";return;}var age=Y-y;if(age<0)age=0;el.value=fa(age);}'
            . 'function bind(){var inp=document.getElementById(yi);if(!inp)return;inp.addEventListener("input",upd);inp.addEventListener("change",upd);upd();}'
            . 'if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",bind);else bind();})();</script>';
    }
}
