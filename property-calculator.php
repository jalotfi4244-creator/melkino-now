<?php
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    @session_start();
}
$_mkPage = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')));
$_mkAllow = ['login.php','logout.php','auth.php','auth-telegram.php','auth-bale.php','auth-eitaa.php','request-otp.php','verify-otp.php','admin-login.php','admin-logout.php','telegram.php','bale.php','eitaa.php','telegram-relay.php','identity-sync.php','bale-ok.php','r.php'];
if (
    $_mkPage !== ''
    && !in_array($_mkPage, $_mkAllow, true)
    && strncmp($_mkPage, 'admin-', 6) !== 0
    && empty($_SESSION['user_id'])
    && empty($_SESSION['reg_telegram_id'])
    && empty($_SESSION['reg_bale_id'])
    && empty($_SESSION['reg_eitaa_id'])
    && empty($_SESSION['user_phone'])
    && empty($_SESSION['is_admin'])
) {
    $here = (string) ($_SERVER['REQUEST_URI'] ?? $_mkPage);
    $here = preg_replace('#^/+#', '', $here) ?? $_mkPage;
    if ($here === '' || strpos($here, 'login.php') === 0) {
        $here = 'home.php';
    }
    if (!headers_sent()) {
        header('Location: login.php?redirect=' . rawurlencode($here), true, 302);
    }
    exit;
}
unset($_mkPage, $_mkAllow);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/melkino-calc-rates.php';
$calcRates = melkinoCalcRates();
$calcEnabled = melkinoCalcEnabled();
$calcIsAdmin = !empty($_SESSION['is_admin']);
require_once __DIR__ . '/header.php';

$calcExtras = [];
if (!empty($calcRates['EXTRAS']) && is_array($calcRates['EXTRAS'])) {
    foreach ($calcRates['EXTRAS'] as $ex) {
        if (is_array($ex) && !empty($ex['enabled']) && trim((string)($ex['label'] ?? '')) !== '') {
            $calcExtras[] = $ex;
        }
    }
}
?>
<style>
.mkc-page {
    flex: 1;
    overflow-y: auto;
    padding: 14px 14px 96px;
    background:
        radial-gradient(circle at 100% 0%, rgba(212,175,55,.10), transparent 28%),
        linear-gradient(180deg, var(--bg) 0%, color-mix(in srgb, var(--bg) 94%, var(--primary) 6%) 100%);
}
.mkc-wrap { width: 100%; max-width: 760px; margin: 0 auto; }
.mkc-hero {
    position: relative;
    overflow: hidden;
    padding: 22px 20px;
    border-radius: 22px;
    background:
        radial-gradient(circle at 88% 12%, rgba(212,175,55,.18), transparent 32%),
        linear-gradient(135deg, #073737, #052727 70%, #031C1C);
    border: 1px solid rgba(212,175,55,.14);
    box-shadow: var(--shadow-card);
    margin-bottom: 14px;
    color: #fff;
}
.mkc-kicker {
    display: inline-flex; align-items: center; gap: 6px;
    color: #F0D36A; font-size: 10px; font-weight: 800; margin-bottom: 8px;
}
.mkc-kicker i {
    width: 6px; height: 6px; border-radius: 50%; background: #D4AF37;
    box-shadow: 0 0 10px rgba(212,175,55,.5); display: block;
}
.mkc-hero h1 { margin: 0; font-size: clamp(20px, 5.4vw, 30px); font-weight: 900; line-height: 1.4; }
.mkc-hero h1 span { color: #F0D36A; }
.mkc-hero p { margin: 7px 0 0; color: rgba(255,255,255,.58); font-size: 11.5px; line-height: 1.95; max-width: 560px; }
.mkc-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 16px;
    margin-bottom: 12px;
    box-shadow: var(--shadow-card);
}
.mkc-card h2 {
    margin: 0 0 14px;
    font-size: 14px;
    font-weight: 900;
    color: var(--primary);
    display: flex; align-items: center; gap: 8px;
}
.mkc-card h2::before {
    content: ""; width: 4px; height: 16px; border-radius: 99px;
    background: linear-gradient(180deg, var(--gold), #f0d673);
}
.mkc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.mkc-field { display: flex; flex-direction: column; gap: 6px; }
.mkc-field.full { grid-column: 1 / -1; }
.mkc-field label { font-size: 11.5px; font-weight: 800; color: var(--text-secondary); }
.mkc-field input, .mkc-field select {
    width: 100%; height: 44px; box-sizing: border-box;
    padding: 0 12px; border: 1px solid var(--border); border-radius: 12px;
    background: var(--bg); color: var(--text-primary);
    font-family: inherit; font-size: 13.5px; outline: none;
}
.mkc-field input:focus, .mkc-field select:focus {
    border-color: var(--primary); box-shadow: 0 0 0 3px rgba(6,78,78,.08);
}
.mkc-words {
    min-height: 18px;
    font-size: 11px;
    font-weight: 700;
    color: var(--gold-dark, #8a6d12);
    line-height: 1.7;
}
.mkc-hint { font-size: 10px; color: var(--text-muted); line-height: 1.7; }
.mkc-seg { display: flex; gap: 6px; flex-wrap: wrap; }
.mkc-seg button {
    flex: 1; min-width: 72px; height: 42px; border-radius: 12px;
    border: 1px solid var(--border); background: var(--bg);
    color: var(--text-secondary); font-family: inherit; font-weight: 800; font-size: 12.5px;
    cursor: pointer;
}
.mkc-seg button.on {
    background: linear-gradient(135deg, var(--primary), #0b5d5b);
    color: #fff; border-color: transparent;
}
.mkc-actions { display: flex; gap: 8px; flex-wrap: wrap; margin: 4px 0 14px; }
.mkc-btn {
    flex: 1; min-height: 50px; border: 0; border-radius: 14px;
    font-family: inherit; font-weight: 900; font-size: 14px; cursor: pointer;
}
.mkc-btn.primary {
    background: linear-gradient(135deg, var(--primary), #0b5d5b);
    color: #fff; box-shadow: 0 10px 22px rgba(6,78,78,.18);
}
.mkc-btn.ghost {
    background: var(--surface); color: var(--text-secondary);
    border: 1px solid var(--border);
}
.mkc-error {
    display: none; background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA;
    border-radius: 12px; padding: 10px 12px; font-size: 12px; line-height: 1.9; margin-bottom: 12px;
}
.mkc-error.show { display: block; }
.mkc-result { display: none; }
.mkc-result.show { display: block; }
.mkc-hero-price {
    background: linear-gradient(180deg, #fff, #fbfaf4);
    border: 1px solid rgba(212,175,55,.28);
    border-radius: 18px; padding: 18px 16px; text-align: center; margin-bottom: 12px;
}
.mkc-hero-price small { display: block; color: var(--text-secondary); font-size: 11px; font-weight: 800; }
.mkc-hero-price strong {
    display: block; margin-top: 6px; color: var(--primary);
    font-size: clamp(22px, 6vw, 32px); font-weight: 950; letter-spacing: -.4px;
}
.mkc-hero-price em {
    display: block; margin-top: 8px; font-style: normal;
    color: var(--gold-dark); font-size: 13px; font-weight: 800;
}
.mkc-hero-price .mkc-meter-words {
    display: block; margin-top: 4px; font-size: 11.5px; font-weight: 700;
    color: var(--text-secondary); line-height: 1.8;
}
.mkc-kv { display: grid; gap: 7px; }
.mkc-row {
    display: flex; justify-content: space-between; gap: 10px; align-items: baseline;
    padding: 9px 11px; border-radius: 12px; background: var(--bg); border: 1px solid var(--border);
}
.mkc-row span { font-size: 11.5px; color: var(--text-secondary); }
.mkc-row b { font-size: 12.5px; color: var(--text-primary); text-align: left; }
.mkc-row.cut b { color: #B45309; }
.mkc-row.add b { color: #047857; }
.mkc-row.total {
    background: rgba(6,78,78,.06); border-color: rgba(6,78,78,.16);
}
.mkc-row.total b { color: var(--primary); font-size: 14px; }
.mkc-formula {
    margin-top: 10px; padding: 12px; border-radius: 12px;
    background: #071c1c; color: #d7ece8; font-size: 11.5px; line-height: 2;
}
.mkc-formula h3 { margin: 0 0 6px; color: #F0D36A; font-size: 12px; }
.mkc-hidden { display: none !important; }
.mkc-off {
    text-align: center; padding: 28px 16px;
}
.mkc-off p { color: var(--text-secondary); line-height: 2; font-size: 13px; }
@media (max-width: 560px) {
    .mkc-grid { grid-template-columns: 1fr; }
    .mkc-field.full { grid-column: auto; }
    .mkc-page { padding: 10px 10px 96px; }
}
[data-theme="dark"] .mkc-hero-price { background: #142525; border-color: #294646; }
[data-theme="dark"] .mkc-hero-price strong { color: #E7C65A; }
[data-theme="dark"] .mkc-formula { background: #0b1a1a; }
[data-theme="dark"] .mkc-words { color: #E7C65A; }
</style>

<div class="mkc-page">
    <div class="mkc-wrap">
        <section class="mkc-hero">
            <div class="mkc-kicker"><i></i> ابزار مشاور املاک</div>
            <h1>ماشین‌حساب <span>قیمت‌گذاری ملک</span></h1>
            <p>قیمت تقریبی آپارتمان را با استهلاک سن بنا، وقفی، پارکینگ، آسانسور، حیاط و آیتم‌های اضافی مثل انباری محاسبه کنید.</p>
        </section>

<?php if (!$calcEnabled && !$calcIsAdmin): ?>
        <div class="mkc-card mkc-off">
            <h2>غیرفعال</h2>
            <p>ماشین‌حساب قیمت ملک فعلاً در دسترس نیست.</p>
        </div>
<?php else: ?>
        <?php if (!$calcEnabled && $calcIsAdmin): ?>
        <div class="mkc-error show">ماشین‌حساب برای عموم خاموش است؛ شما به‌عنوان مدیر می‌توانید تست کنید.</div>
        <?php endif; ?>

        <form id="mkcForm" novalidate>
            <div class="mkc-card">
                <h2>اطلاعات اصلی ملک</h2>
                <div class="mkc-grid">
                    <div class="mkc-field full">
                        <label for="mkcNewMeter">قیمت هر مترمربع آپارتمان نوساز (تومان)</label>
                        <input id="mkcNewMeter" name="newMeterPrice" inputmode="numeric" placeholder="مثلاً ۶۰٬۰۰۰٬۰۰۰" autocomplete="off">
                        <div class="mkc-words" id="mkcNewMeterWords"></div>
                        <div class="mkc-hint">عدد را به تومان وارد کنید؛ جداکنندهٔ سه‌رقمی خودکار است.</div>
                    </div>
                    <div class="mkc-field">
                        <label for="mkcArea">متراژ آپارتمان (متر)</label>
                        <input id="mkcArea" name="area" inputmode="decimal" placeholder="مثلاً ۱۰۰">
                    </div>
                    <div class="mkc-field">
                        <label for="mkcAge">سن بنا (سال)</label>
                        <input id="mkcAge" name="age" inputmode="decimal" placeholder="مثلاً ۸">
                    </div>
                    <div class="mkc-field">
                        <label for="mkcFloor">طبقه <span class="mkc-hint" style="font-weight:600">(همکف = ۰)</span></label>
                        <input id="mkcFloor" name="floor" inputmode="numeric" placeholder="مثلاً ۳">
                    </div>
                    <div class="mkc-field">
                        <label for="mkcFloors">تعداد طبقات ساختمان</label>
                        <input id="mkcFloors" name="totalFloors" inputmode="numeric" placeholder="مثلاً ۵">
                    </div>
                </div>
            </div>

            <div class="mkc-card">
                <h2>امکانات و وضعیت ملک</h2>
                <div class="mkc-grid">
                    <div class="mkc-field">
                        <label>آیا ملک وقفی است؟</label>
                        <div class="mkc-seg" data-mkc="waqf">
                            <button type="button" data-v="0" class="on">خیر</button>
                            <button type="button" data-v="1">بله</button>
                        </div>
                    </div>
                    <div class="mkc-field">
                        <label>آیا پارکینگ دارد؟</label>
                        <div class="mkc-seg" data-mkc="parking">
                            <button type="button" data-v="1" class="on">بله</button>
                            <button type="button" data-v="0">خیر</button>
                        </div>
                    </div>
                    <div class="mkc-field full mkc-hidden" id="mkcParkWrap">
                        <label for="mkcParkRate">درصد کاهش بابت نداشتن پارکینگ</label>
                        <input id="mkcParkRate" inputmode="decimal" step="0.001">
                        <div class="mkc-hint" id="mkcParkHint"></div>
                    </div>
                    <div class="mkc-field">
                        <label>آیا آسانسور دارد؟</label>
                        <div class="mkc-seg" data-mkc="elevator">
                            <button type="button" data-v="1" class="on">بله</button>
                            <button type="button" data-v="0">خیر</button>
                        </div>
                    </div>
                    <div class="mkc-field">
                        <label>آیا حیاط اختصاصی دارد؟</label>
                        <div class="mkc-seg" data-mkc="yard">
                            <button type="button" data-v="0" class="on">خیر</button>
                            <button type="button" data-v="1">بله</button>
                        </div>
                    </div>
                    <div class="mkc-field full mkc-hidden" id="mkcYardWrap">
                        <label for="mkcYardArea">متراژ حیاط اختصاصی (متر)</label>
                        <input id="mkcYardArea" name="yardArea" inputmode="decimal" placeholder="مثلاً ۳۰">
                    </div>
                    <?php foreach ($calcExtras as $ex):
                        $eid = htmlspecialchars((string)$ex['id'], ENT_QUOTES, 'UTF-8');
                        $elabel = htmlspecialchars((string)$ex['label'], ENT_QUOTES, 'UTF-8');
                        $emode = (string)($ex['mode'] ?? 'area_ratio');
                    ?>
                    <div class="mkc-field">
                        <label>آیا <?= $elabel ?> دارد؟</label>
                        <div class="mkc-seg" data-mkc="extra" data-extra-id="<?= $eid ?>">
                            <button type="button" data-v="0" class="on">خیر</button>
                            <button type="button" data-v="1">بله</button>
                        </div>
                    </div>
                    <?php if ($emode === 'area_ratio'): ?>
                    <div class="mkc-field mkc-hidden" id="mkcExtraArea_<?= $eid ?>">
                        <label for="mkcExtraAreaInput_<?= $eid ?>">متراژ <?= $elabel ?> (متر)</label>
                        <input id="mkcExtraAreaInput_<?= $eid ?>" inputmode="decimal" placeholder="مثلاً ۸">
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mkc-card">
                <h2>رهن و اجاره</h2>
                <div class="mkc-field">
                    <label>آیا محاسبه رهن و اجاره نیز انجام شود؟</label>
                    <div class="mkc-seg" data-mkc="rent">
                        <button type="button" data-v="0" class="on">خیر</button>
                        <button type="button" data-v="1">بله</button>
                    </div>
                </div>
                <div class="mkc-field mkc-hidden" id="mkcRentWrap" style="margin-top:10px;">
                    <label for="mkcExtraRent">اجاره ماهانه برای تبدیل به رهن معادل (تومان) — اختیاری</label>
                    <input id="mkcExtraRent" name="extraRentMonthly" inputmode="numeric" placeholder="مثلاً ۱۵٬۰۰۰٬۰۰۰">
                    <div class="mkc-words" id="mkcExtraRentWords"></div>
                    <div class="mkc-hint">اگر عددی وارد کنید، رهن معادل همان اجاره هم محاسبه می‌شود.</div>
                </div>
            </div>

            <div class="mkc-actions">
                <button type="submit" class="mkc-btn primary">محاسبه قیمت ملک</button>
                <button type="button" class="mkc-btn ghost" id="mkcReset">پاک کردن اطلاعات</button>
            </div>
        </form>

        <div class="mkc-error" id="mkcError"></div>

        <div class="mkc-result" id="mkcResult">
            <div class="mkc-hero-price">
                <small>قیمت تخمینی ملک</small>
                <strong id="mkcOutFinal">—</strong>
                <em>قیمت هر متر: <span id="mkcOutMeter">—</span></em>
                <span class="mkc-meter-words" id="mkcOutMeterWords"></span>
            </div>
            <div class="mkc-card">
                <h2>جزئیات محاسبه</h2>
                <div class="mkc-kv" id="mkcDetails"></div>
            </div>
            <div class="mkc-card mkc-hidden" id="mkcRentCard">
                <h2>بخش رهن و اجاره</h2>
                <div class="mkc-kv" id="mkcRentDetails"></div>
            </div>
            <div class="mkc-card">
                <h2>نحوه محاسبه</h2>
                <div class="mkc-formula" id="mkcFormula"></div>
            </div>
        </div>
<?php endif; ?>
    </div>
</div>

<?php if ($calcEnabled || $calcIsAdmin): ?>
<script>window.MELKINO_CALC_RATES = <?= json_encode($calcRates, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="property-calculator-engine.js?v=<?= (int)@filemtime(__DIR__ . '/property-calculator-engine.js') ?>"></script>
<script>
(function () {
    var C = window.MelkinoPropertyCalc;
    if (!C) return;
    var R = C.rates();
    var flags = { waqf: 0, parking: 1, elevator: 1, yard: 0, rent: 0 };
    var extraFlags = {};

    function pctShow(rate) {
        return C.formatPct(rate);
    }
    function fillPark() {
        var el = document.getElementById('mkcParkRate');
        var hint = document.getElementById('mkcParkHint');
        if (!el) return;
        var def = (R.NO_PARKING_DISCOUNT_DEFAULT || 0) * 100;
        var mn = (R.NO_PARKING_DISCOUNT_MIN || 0) * 100;
        var mx = (R.NO_PARKING_DISCOUNT_MAX || 0) * 100;
        el.value = C.toFaDigits(String(def));
        el.setAttribute('data-min', String(mn));
        el.setAttribute('data-max', String(mx));
        if (hint) hint.textContent = 'بازه مجاز: ' + pctShow(R.NO_PARKING_DISCOUNT_MIN) + ' تا ' + pctShow(R.NO_PARKING_DISCOUNT_MAX);
        if (Math.abs(mx - mn) < 0.0000001) {
            el.readOnly = true;
        }
    }
    fillPark();

    document.querySelectorAll('.mkc-seg').forEach(function (seg) {
        seg.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            seg.querySelectorAll('button').forEach(function (b) { b.classList.remove('on'); });
            btn.classList.add('on');
            var key = seg.getAttribute('data-mkc');
            var v = Number(btn.getAttribute('data-v'));
            if (key === 'extra') {
                var id = seg.getAttribute('data-extra-id');
                extraFlags[id] = v === 1;
                var wrap = document.getElementById('mkcExtraArea_' + id);
                if (wrap) wrap.classList.toggle('mkc-hidden', v !== 1);
                return;
            }
            flags[key] = v;
            document.getElementById('mkcParkWrap').classList.toggle('mkc-hidden', flags.parking === 1);
            document.getElementById('mkcYardWrap').classList.toggle('mkc-hidden', flags.yard !== 1);
            document.getElementById('mkcRentWrap').classList.toggle('mkc-hidden', flags.rent !== 1);
        });
    });

    function bindMoney(id, wordsId) {
        var el = document.getElementById(id);
        var w = wordsId ? document.getElementById(wordsId) : null;
        if (!el) return;
        el.addEventListener('input', function () {
            var n = C.parseNumber(el.value);
            if (!isFinite(n) || n < 0) {
                if (w) w.textContent = '';
                return;
            }
            var grouped = C.formatGrouped(Math.round(n));
            if (el.value !== grouped) el.value = grouped;
            if (w) w.textContent = n > 0 ? C.tomanToWords(n) : '';
        });
        el.addEventListener('blur', function () {
            var n = C.parseNumber(el.value);
            if (isFinite(n) && n > 0) el.value = C.formatGrouped(Math.round(n));
        });
    }
    bindMoney('mkcNewMeter', 'mkcNewMeterWords');
    bindMoney('mkcExtraRent', 'mkcExtraRentWords');

    function val(id) { return (document.getElementById(id) || {}).value || ''; }
    function row(label, value, cls) {
        return '<div class="mkc-row' + (cls ? ' ' + cls : '') + '"><span>' + label + '</span><b>' + value + '</b></div>';
    }
    function collectExtras() {
        var out = {};
        document.querySelectorAll('.mkc-seg[data-mkc="extra"]').forEach(function (seg) {
            var id = seg.getAttribute('data-extra-id');
            var areaEl = document.getElementById('mkcExtraAreaInput_' + id);
            out[id] = {
                has: extraFlags[id] === true,
                area: areaEl ? areaEl.value : ''
            };
        });
        return out;
    }

    document.getElementById('mkcForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var errEl = document.getElementById('mkcError');
        var box = document.getElementById('mkcResult');
        var parkPct = C.parseNumber(val('mkcParkRate'));
        var out = C.calculate({
            newMeterPrice: val('mkcNewMeter'),
            area: val('mkcArea'),
            age: val('mkcAge'),
            floor: val('mkcFloor'),
            totalFloors: val('mkcFloors'),
            isWaqf: flags.waqf === 1,
            hasParking: flags.parking === 1,
            hasElevator: flags.elevator === 1,
            hasYard: flags.yard === 1,
            yardArea: val('mkcYardArea'),
            wantRent: flags.rent === 1,
            noParkingDiscount: isFinite(parkPct) ? parkPct / 100 : R.NO_PARKING_DISCOUNT_DEFAULT,
            extraRentMonthly: val('mkcExtraRent'),
            extras: collectExtras()
        });
        if (!out.ok) {
            errEl.innerHTML = out.errors.map(function (x) { return '• ' + x; }).join('<br>');
            errEl.classList.add('show');
            box.classList.remove('show');
            errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        errEl.classList.remove('show');
        var r = out.result;
        document.getElementById('mkcOutFinal').textContent = C.formatToman(r.finalPrice);
        document.getElementById('mkcOutMeter').textContent = C.formatToman(r.meterPrice);
        document.getElementById('mkcOutMeterWords').textContent = r.meterPriceWords || C.tomanToWords(r.meterPrice);
        var html = '';
        html += row('قیمت نوساز', C.formatToman(r.newMeterPrice));
        html += row('سن بنا', C.toFaDigits(r.age) + ' سال');
        html += row('درصد استهلاک', C.formatPct(r.depRate));
        html += row('قیمت محاسبه‌شده هر متر', C.formatToman(r.meterPrice));
        html += row('قیمت هر متر به حروف', r.meterPriceWords || C.tomanToWords(r.meterPrice));
        html += row('قیمت پایه ملک', C.formatToman(r.baseTotal));
        html += row('کسر وقفی', r.isWaqf ? ('− ' + C.formatToman(r.waqfCut) + ' (' + C.formatPct(r.rates.VAGHFI_DISCOUNT) + ')') : 'اعمال نشد', r.isWaqf ? 'cut' : '');
        html += row('کسر نداشتن پارکینگ', r.hasParking ? 'اعمال نشد' : ('− ' + C.formatToman(r.parkingCut) + ' (' + C.formatPct(r.parkingRate) + ')'), r.hasParking ? '' : 'cut');
        html += row('کسر آسانسور', r.hasElevator ? 'اعمال نشد' : (r.elevPct > 0 ? ('− ' + C.formatToman(r.elevCut) + ' (' + C.formatPct(r.elevPct) + ')') : 'طبقه همکف/اول — بدون کاهش'), r.elevPct > 0 ? 'cut' : '');
        html += row('ارزش حیاط اختصاصی', r.hasYard ? C.formatToman(r.yardValue) : 'اعمال نشد', r.hasYard ? 'add' : '');
        (r.extras || []).forEach(function (ex) {
            if (!ex.has) {
                html += row(ex.label, 'اعمال نشد');
                return;
            }
            var cls = ex.mode === 'percent_cut' ? 'cut' : 'add';
            var sign = ex.mode === 'percent_cut' ? '− ' : '';
            html += row(ex.label, sign + C.formatToman(ex.value) + ' (' + C.formatPct(ex.ratio) + ')', cls);
        });
        html += row('قیمت نهایی', C.formatToman(r.finalPrice), 'total');
        document.getElementById('mkcDetails').innerHTML = html;

        var rentCard = document.getElementById('mkcRentCard');
        if (r.wantRent) {
            var rh = '';
            rh += row('رهن کامل پیشنهادی', C.formatToman(r.fullRent), 'total');
            rh += row('معادل اجاره ماهانه', C.formatToman(r.monthlyFromFull));
            rh += row('معادل رهن برای اجاره واردشده', r.rentToDeposit != null ? C.formatToman(r.rentToDeposit) : 'اجاره‌ای وارد نشده');
            document.getElementById('mkcRentDetails').innerHTML = rh;
            rentCard.classList.remove('mkc-hidden');
        } else {
            rentCard.classList.add('mkc-hidden');
        }

        var f = [];
        f.push('<h3>فرمول‌ها</h3>');
        f.push('قیمت هر متر = قیمت نوساز × (۱ − سن بنا × ' + C.formatPct(r.rates.ESTEHKLAK_RATE) + ')');
        f.push('قیمت هر متر = ' + C.formatToman(r.newMeterPrice) + ' × (۱ − ' + C.formatPct(r.depRate) + ') = ' + C.formatToman(r.meterPrice));
        f.push(r.meterPriceWords || '');
        f.push('قیمت پایه = متراژ × قیمت هر متر = ' + C.toFaDigits(r.area) + ' × ' + C.formatToman(r.meterPrice) + ' = ' + C.formatToman(r.baseTotal));
        if (r.isWaqf) f.push('وقفی: قیمت × (۱ − ' + C.formatPct(r.rates.VAGHFI_DISCOUNT) + ') → کسر ' + C.formatToman(r.waqfCut));
        if (!r.hasParking) f.push('بدون پارکینگ: قیمت × (۱ − ' + C.formatPct(r.parkingRate) + ') → کسر ' + C.formatToman(r.parkingCut));
        if (!r.hasElevator && r.elevPct > 0) f.push('بدون آسانسور: (طبقه − ۱) × ' + C.formatPct(r.rates.NO_ELEVATOR_RATE) + ' = ' + C.formatPct(r.elevPct) + ' → کسر ' + C.formatToman(r.elevCut));
        else if (!r.hasElevator) f.push('بدون آسانسور در همکف/طبقه اول: بدون کاهش');
        if (r.hasYard) f.push('حیاط: (قیمت هر متر × ' + C.formatPct(r.rates.YARD_RATIO) + ') × متراژ حیاط = ' + C.formatToman(r.yardPerMeter) + ' × ' + C.toFaDigits(r.yardArea) + ' = ' + C.formatToman(r.yardValue));
        (r.extras || []).forEach(function (ex) {
            if (!ex.applied) return;
            if (ex.mode === 'area_ratio') {
                f.push(ex.label + ': متراژ × قیمت هر متر × ' + C.formatPct(ex.ratio) + ' = ' + C.formatToman(ex.value));
            } else if (ex.mode === 'percent_cut') {
                f.push(ex.label + ': قیمت × ' + C.formatPct(ex.ratio) + ' → کسر ' + C.formatToman(ex.value));
            } else {
                f.push(ex.label + ': قیمت × ' + C.formatPct(ex.ratio) + ' → افزایش ' + C.formatToman(ex.value));
            }
        });
        f.push('قیمت نهایی = ' + C.formatToman(r.finalPrice));
        if (r.wantRent) {
            f.push('رهن کامل = قیمت نهایی ÷ ' + C.toFaDigits(r.rates.FULL_RENT_DIVISOR) + ' = ' + C.formatToman(r.fullRent));
            f.push('اجاره ماهانه = (رهن ÷ ' + C.formatToman(r.rates.RENT_BASE) + ') × ' + C.formatToman(r.rates.RENT_PER_100M) + ' = ' + C.formatToman(r.monthlyFromFull));
            if (r.rentToDeposit != null) f.push('رهن معادل اجاره = (اجاره ÷ ' + C.formatToman(r.rates.RENT_PER_100M) + ') × ' + C.formatToman(r.rates.RENT_BASE) + ' = ' + C.formatToman(r.rentToDeposit));
        }
        document.getElementById('mkcFormula').innerHTML = f.join('<br>');
        box.classList.add('show');
        box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    document.getElementById('mkcReset').addEventListener('click', function () {
        document.getElementById('mkcForm').reset();
        flags = { waqf: 0, parking: 1, elevator: 1, yard: 0, rent: 0 };
        extraFlags = {};
        fillPark();
        document.querySelectorAll('.mkc-seg').forEach(function (seg) {
            var key = seg.getAttribute('data-mkc');
            var want = key === 'extra' ? '0' : String(flags[key]);
            seg.querySelectorAll('button').forEach(function (b) {
                b.classList.toggle('on', b.getAttribute('data-v') === want);
            });
        });
        document.querySelectorAll('[id^="mkcExtraArea_"]').forEach(function (el) { el.classList.add('mkc-hidden'); });
        document.getElementById('mkcParkWrap').classList.add('mkc-hidden');
        document.getElementById('mkcYardWrap').classList.add('mkc-hidden');
        document.getElementById('mkcRentWrap').classList.add('mkc-hidden');
        document.getElementById('mkcNewMeterWords').textContent = '';
        var rw = document.getElementById('mkcExtraRentWords');
        if (rw) rw.textContent = '';
        document.getElementById('mkcError').classList.remove('show');
        document.getElementById('mkcResult').classList.remove('show');
    });
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
