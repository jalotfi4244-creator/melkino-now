<style>
/* نوار تب ادمین sticky با z-index:8000 روی مودال می‌افتاد */
.modal-overlay {
    z-index: 20000 !important;
}
#adEditModal.modal-overlay,
#adDetailModal.modal-overlay,
#publishComposerModal.modal-overlay {
    z-index: 20000 !important;
}
body:has(.modal-overlay.active) .tabs-container,
body:has(.modal-overlay.active) .admin-body .tabs-container {
    z-index: 1 !important;
}
body:has(.modal-overlay.active) {
    overflow: hidden;
}
</style>
<!-- =========================================================
     DETAIL MODAL
     ========================================================= -->

<div
    class="modal-overlay"
    id="adDetailModal"
>

    <div class="modal-box">

        <div class="modal-header">

            <h3>
                جزئیات کامل آگهی
            </h3>

            <button
                class="modal-close"
                onclick="closeModal('adDetailModal')"
            >
                ✕
            </button>

        </div>


        <div
            id="adDetailContent"
            style="
                display:flex;
                flex-direction:column;
                gap:var(--space-2);
                flex:1 1 auto;
                min-height:0;
            "
        ></div>

    </div>

</div>


<!-- =========================================================
     EDIT MODAL
     ========================================================= -->

<div
    class="modal-overlay"
    id="adEditModal"
>

    <div class="modal-box">

        <div class="modal-header">

            <h3>
                <?= melkinoSvgIcon('edit') ?> ویرایش آگهی
            </h3>

            <button
                class="modal-close"
                onclick="closeModal('adEditModal')"
            >
                ✕
            </button>

        </div>


        <div
            id="adEditContent"
            style="
                display:flex;
                flex-direction:column;
                flex:1 1 auto;
                min-height:0;
            "
        ></div>

    </div>

</div>

<div class="modal-overlay" id="publishComposerModal">
    <div class="modal-box" style="max-width:920px;width:96%;">
        <div class="modal-header">
            <h3 id="pubCompTitle">پیش‌نمایش انتشار</h3>
            <button class="modal-close" type="button" onclick="closeModal('publishComposerModal')">✕</button>
        </div>
        <div id="pubCompBody" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:14px;padding:4px 2px 8px;">
            <div>
                <div style="font-size:12px;font-weight:800;margin-bottom:8px;">فیلدهای پیام — کم و زیاد کنید</div>
                <div id="pubCompFields" style="display:flex;flex-wrap:wrap;gap:6px;max-height:220px;overflow:auto;"></div>
                <label style="display:flex;gap:8px;align-items:center;margin:12px 0 8px;font-size:13px;">
                    <input type="checkbox" id="pubCompPhoto" checked> ارسال تصویر آگهی
                </label>
                <div style="font-size:12px;font-weight:800;margin-bottom:6px;">متن نهایی (قابل ویرایش)</div>
                <textarea id="pubCompText" rows="12" style="width:100%;box-sizing:border-box;padding:10px;border-radius:12px;border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-family:inherit;line-height:1.8;"></textarea>
            </div>
            <div>
                <div style="font-size:12px;font-weight:800;margin-bottom:8px;">پیش‌نمایش واقعی کانال</div>
                <div style="border:8px solid #111;border-radius:28px;background:#0e1715;min-height:420px;padding:14px;color:#e8efe9;max-width:360px;margin:0 auto;">
                    <div id="pubCompPhotoWrap" style="display:none;margin-bottom:10px;"><img id="pubCompImg" alt="" style="width:100%;border-radius:12px;max-height:180px;object-fit:cover;"></div>
                    <pre id="pubCompPreview" style="white-space:pre-wrap;font-family:inherit;font-size:13px;line-height:1.85;margin:0;"></pre>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;padding:8px 2px 4px;">
            <button type="button" class="btn-secondary" onclick="closeModal('publishComposerModal')">انصراف</button>
            <button type="button" class="btn-primary" id="pubCompConfirm">تأیید و انتشار</button>
        </div>
    </div>
</div>
