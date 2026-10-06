<div class="nitron-pay-admin-wrapper">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-filter"></i> فیلتر و جستجوی لاگ‌ها
            <span class="pull-left" style="margin-left: 10px;">
                <a href="{$config_url|escape:'html':'UTF-8'}" class="btn btn-xs btn-default" style="margin-right: 8px;">
                    <i class="icon-cogs"></i> بازگشت به تنظیمات
                </a>
            </span>
        </div>
        <div class="panel-body">
            <form action="{$current_url|escape:'html':'UTF-8'}" method="get" class="form-horizontal">
                <input type="hidden" name="controller" value="AdminNitronPayLogs">
                <input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">

                <div class="row">
                    <div class="col-lg-3">
                        <label>شناسه سفارش یا UUID:</label>
                        <input type="text" name="filter_site_order_id" value="{$filters.site_order_id|escape:'html':'UTF-8'}" class="form-control" placeholder="شناسه سفارش / UUID">
                    </div>
                    <div class="col-lg-3">
                        <label>نوع عملیات (Action):</label>
                        <input type="text" name="filter_action" value="{$filters.action|escape:'html':'UTF-8'}" class="form-control" placeholder="مثلاً: /api/v1/payments">
                    </div>
                    <div class="col-lg-2">
                        <label>از تاریخ:</label>
                        <input type="date" name="filter_date_from" value="{$filters.date_from|escape:'html':'UTF-8'}" class="form-control">
                    </div>
                    <div class="col-lg-2">
                        <label>تا تاریخ:</label>
                        <input type="date" name="filter_date_to" value="{$filters.date_to|escape:'html':'UTF-8'}" class="form-control">
                    </div>
                    <div class="col-lg-2">
                        <label>وضعیت پاسخ:</label>
                        <select name="filter_status" class="form-control">
                            <option value="">همه</option>
                            <option value="success" {if $filters.status == 'success'}selected="selected"{/if}>موفق (2xx)</option>
                            <option value="error" {if $filters.status == 'error'}selected="selected"{/if}>خطا یا ناموفق</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-search"></i> اعمال فیلتر
                    </button>
                    <a href="{$current_url|escape:'html':'UTF-8'}" class="btn btn-default">
                        <i class="icon-eraser"></i> پاکسازی فیلتر
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- جدول تراکنش‌های اخیر -->
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-credit-card"></i> آخرین تراکنش‌های ایجاد شده (۵۰ مورد اخیر)
        </div>
        <div class="panel-body">
            {if empty($transactions)}
                <div class="alert alert-info">هیچ تراکنشی یافت نشد.</div>
            {else}
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>شناسه تراکنش</th>
                                <th>شناسه سفارش سایت</th>
                                <th>شماره سفارش</th>
                                <th>درگاه</th>
                                <th>مبلغ (ریال)</th>
                                <th>وضعیت</th>
                                <th>کد پیگیری (Ref ID)</th>
                                <th>کارت بانکی</th>
                                <th>تاریخ</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$transactions item=tx}
                                <tr>
                                    <td>{$tx.id_nitron_pay_transaction|escape:'html':'UTF-8'}</td>
                                    <td>
                                        <span class="np-copyable" data-clipboard-text="{$tx.site_order_id|escape:'html':'UTF-8'}" title="کلیک جهت کپی">
                                            <code>{$tx.site_order_id|escape:'html':'UTF-8'}</code>
                                            <i class="icon-copy" style="font-size:10px; color:#999; margin-right:3px;"></i>
                                        </span>
                                    </td>
                                    <td>
                                        {if $tx.id_order}
                                            <a href="?controller=AdminOrders&id_order={$tx.id_order|escape:'html':'UTF-8'}&vieworder&token={Tools::getAdminTokenLite('AdminOrders')}" target="_blank">
                                                #{$tx.id_order|escape:'html':'UTF-8'} ({$tx.order_reference|escape:'html':'UTF-8'})
                                            </a>
                                        {else}
                                            -
                                        {/if}
                                    </td>
                                    <td>{$tx.gateway_name|default:'-'|escape:'html':'UTF-8'}</td>
                                    <td>{number_format($tx.amount_rial)} ریال</td>
                                    <td>
                                        {if $tx.status == 'verified'}
                                            <span class="label label-success">تایید شده</span>
                                        {elseif $tx.status == 'redirected_to_gateway'}
                                            <span class="label label-info">ارسال به درگاه</span>
                                        {elseif $tx.status == 'failed' || $tx.status == 'cancelled'}
                                            <span class="label label-danger">لغو / ناموفق</span>
                                        {else}
                                            <span class="label label-warning">{$tx.status|escape:'html':'UTF-8'}</span>
                                        {/if}
                                    </td>
                                    <td><b>{$tx.ref_id|default:'-'|escape:'html':'UTF-8'}</b></td>
                                    <td>{$tx.card_pan|default:'-'|escape:'html':'UTF-8'}</td>
                                    <td><small>{$tx.date_add|escape:'html':'UTF-8'}</small></td>
                                    <td>
                                        {if $tx.uuid}
                                            <a href="{$current_url|escape:'html':'UTF-8'}&view_transaction_uuid={$tx.uuid|escape:'html':'UTF-8'}" class="btn btn-xs btn-info" title="مشاهده جزئیات و مسیر مراحل">
                                                <i class="icon-eye"></i> مسیر مراحل
                                            </a>
                                        {/if}
                                        {if $tx.status != 'failed' && $tx.status != 'cancelled' && $tx.status != 'fail' && $tx.status != 'canceled'}
                                            <form action="{$current_url|escape:'html':'UTF-8'}" method="post" style="display:inline-block;">
                                                <input type="hidden" name="site_order_id" value="{$tx.site_order_id|escape:'html':'UTF-8'}">
                                                <input type="hidden" name="uuid" value="{$tx.uuid|escape:'html':'UTF-8'}">
                                                <button type="submit" name="actionInquiry" class="btn btn-xs btn-default" title="استعلام آخرین وضعیت از بانک">
                                                    <i class="icon-refresh"></i> استعلام
                                                </button>
                                            </form>
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            {/if}
        </div>
    </div>

    <!-- جدول لاگ‌های دقیق -->
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-list-alt" style="margin-left: 8px;"></i> گزارش ریز لاگ‌های درخواست و پاسخ 
            <span class="badge badge-info" style="margin-right: 5px;">مجموع کل در دیتابیس: {$total_all_logs|default:0|number_format}</span>
            {if $total_filtered_logs != $total_all_logs}
                <span class="badge badge-warning" style="margin-right: 5px;">یافت‌شده با فیلتر: {$total_filtered_logs|number_format}</span>
            {/if}

            <div class="pull-left" style="display:flex; align-items:center; margin-left: 10px;">
                <!-- فرم پاکسازی انتخابی لاگ‌ها -->
                <form action="{$current_url|escape:'html':'UTF-8'}" method="post" style="display:inline-block; margin-left: 15px;" onsubmit="return confirm('آیا از پاکسازی لاگ‌ها اطمینان دارید؟');">
                    <div class="btn-group">
                        <button type="button" class="btn btn-xs btn-danger dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="margin-left: 15px;">
                            <i class="icon-trash" style="margin-left: 5px;"></i> پاکسازی لاگ‌ها <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-right">
                            <li>
                                <button type="submit" name="actionClearLogs" value="1" class="btn btn-link" style="width:100%; text-align:right; color:#d9534f;">
                                    <i class="icon-trash"></i> پاکسازی تمام لاگ‌ها (خالی کردن کامل)
                                </button>
                            </li>
                            <li role="separator" class="divider"></li>
                            <li>
                                <button type="submit" name="actionClearLogs" value="1" onclick="document.getElementById('keep_last_input').value='100';" class="btn btn-link" style="width:100%; text-align:right;">
                                    <i class="icon-filter"></i> حذف قدیمی‌ها (نگهداری ۱۰۰ لاگ اخیر)
                                </button>
                            </li>
                            <li>
                                <button type="submit" name="actionClearLogs" value="1" onclick="document.getElementById('keep_last_input').value='1000';" class="btn btn-link" style="width:100%; text-align:right;">
                                    <i class="icon-filter"></i> حذف قدیمی‌ها (نگهداری ۱,۰۰۰ لاگ اخیر)
                                </button>
                            </li>
                        </ul>
                    </div>
                    <input type="hidden" name="keep_last" id="keep_last_input" value="0">
                </form>
            </div>
        </div>
        <div class="panel-body">
            {if empty($logs)}
                <div class="alert alert-info">هیچ لاگی با فیلترهای مشخص‌شده یافت نشد.</div>
            {else}
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" style="vertical-align: middle;">
                        <thead>
                            <tr>
                                <th style="width: 135px;">تاریخ و زمان</th>
                                <th style="width: 170px;">شناسه سفارش / UUID</th>
                                <th style="width: 220px;">عملیات (Action)</th>
                                <th style="width: 80px; text-align: center;">کد HTTP</th>
                                <th>توضیحات و پیام</th>
                                <th style="width: 170px; text-align: center;">جزئیات داده‌ها (Payload)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$logs item=lg}
                                <tr>
                                    <td><small style="font-family: monospace;">{$lg.date_add|escape:'html':'UTF-8'}</small></td>
                                    <td>
                                        {if $lg.site_order_id}
                                            <div style="margin-bottom: 3px;">
                                                <small class="text-muted">Order:</small>
                                                <span class="np-copyable" data-clipboard-text="{$lg.site_order_id|escape:'html':'UTF-8'}" title="کلیک جهت کپی">
                                                    <code>{$lg.site_order_id|escape:'html':'UTF-8'}</code>
                                                    <i class="icon-copy" style="font-size:10px; color:#999; margin-right:3px;"></i>
                                                </span>
                                            </div>
                                        {/if}
                                        {if $lg.uuid}
                                            <div>
                                                <small class="text-muted">UUID:</small>
                                                <span class="np-copyable" data-clipboard-text="{$lg.uuid|escape:'html':'UTF-8'}" title="کلیک جهت کپی">
                                                    <code style="color:#d9534f;">{$lg.uuid|substr:0:8|escape:'html':'UTF-8'}...</code>
                                                    <i class="icon-copy" style="font-size:10px; color:#999; margin-right:3px;"></i>
                                                </span>
                                            </div>
                                        {/if}
                                    </td>
                                    <td>
                                        <!-- ستون عملیات به صورت کوتاه با تولتیپ و قابلیت کپی با کلیک -->
                                        <div class="np-action-col np-copyable" data-toggle="tooltip" data-placement="top" title="{$lg.action|escape:'html':'UTF-8'} (برای کپی کلیک کنید)" data-clipboard-text="{$lg.action|escape:'html':'UTF-8'}">
                                            <b>{$lg.action|escape:'html':'UTF-8'}</b>
                                            <i class="icon-copy" style="font-size:10px; color:#999; margin-right:4px;"></i>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        {if $lg.status_code >= 200 && $lg.status_code < 300}
                                            <span class="label label-success" style="font-weight: bold;">{$lg.status_code|escape:'html':'UTF-8'}</span>
                                        {elseif $lg.status_code}
                                            <span class="label label-danger" style="font-weight: bold;">{$lg.status_code|escape:'html':'UTF-8'}</span>
                                        {else}
                                            -
                                        {/if}
                                    </td>
                                    <td>{$lg.message|escape:'html':'UTF-8'}</td>
                                    <td style="text-align: center;">
                                        {if $lg.request_payload || $lg.response_payload}
                                            <button type="button" class="btn btn-xs btn-default btn-view-payload" 
                                                    data-id="{$lg.id_nitron_pay_log|escape:'html':'UTF-8'}"
                                                    data-action="{$lg.action|escape:'html':'UTF-8'}"
                                                    data-time="{$lg.date_add|escape:'html':'UTF-8'}"
                                                    data-status="{$lg.status_code|escape:'html':'UTF-8'}"
                                                    style="box-shadow: none;">
                                                <i class="icon-eye"></i> مشاهده در مودال
                                            </button>
                                            <!-- نگهدارنده داده مخفی -->
                                            <div id="payload_req_{$lg.id_nitron_pay_log}" style="display:none;">{$lg.request_payload|escape:'html':'UTF-8'}</div>
                                            <div id="payload_res_{$lg.id_nitron_pay_log}" style="display:none;">{$lg.response_payload|escape:'html':'UTF-8'}</div>
                                        {else}
                                            <span class="text-muted">-</span>
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                <!-- صفحه‌بندی لاگ‌ها -->
                {if $total_pages > 1}
                    <div class="row" style="margin-top: 15px; display:flex; align-items:center; justify-content:space-between;">
                        <div class="col-sm-6">
                            <span class="text-muted">
                                نمایش صفحه {$current_page} از {$total_pages} (مجموع {$total_filtered_logs} لاگ)
                            </span>
                        </div>
                        <div class="col-sm-6 text-left">
                            <ul class="pagination" style="margin: 0;">
                                {if $current_page > 1}
                                    <li>
                                        <a href="{$current_url|escape:'html':'UTF-8'}&page={$current_page - 1}&filter_action={$filters.action|escape:'html':'UTF-8'}&filter_site_order_id={$filters.site_order_id|escape:'html':'UTF-8'}&filter_date_from={$filters.date_from|escape:'html':'UTF-8'}&filter_date_to={$filters.date_to|escape:'html':'UTF-8'}&filter_status={$filters.status|escape:'html':'UTF-8'}">
                                            &laquo; قبلی
                                        </a>
                                    </li>
                                {/if}

                                {assign var='startPage' value=max(1, $current_page - 2)}
                                {assign var='endPage' value=min($total_pages, $current_page + 2)}

                                {for $p=$startPage to $endPage}
                                    <li class="{if $p == $current_page}active{/if}">
                                        <a href="{$current_url|escape:'html':'UTF-8'}&page={$p}&filter_action={$filters.action|escape:'html':'UTF-8'}&filter_site_order_id={$filters.site_order_id|escape:'html':'UTF-8'}&filter_date_from={$filters.date_from|escape:'html':'UTF-8'}&filter_date_to={$filters.date_to|escape:'html':'UTF-8'}&filter_status={$filters.status|escape:'html':'UTF-8'}">
                                            {$p}
                                        </a>
                                    </li>
                                {/for}

                                {if $current_page < $total_pages}
                                    <li>
                                        <a href="{$current_url|escape:'html':'UTF-8'}&page={$current_page + 1}&filter_action={$filters.action|escape:'html':'UTF-8'}&filter_site_order_id={$filters.site_order_id|escape:'html':'UTF-8'}&filter_date_from={$filters.date_from|escape:'html':'UTF-8'}&filter_date_to={$filters.date_to|escape:'html':'UTF-8'}&filter_status={$filters.status|escape:'html':'UTF-8'}">
                                            بعدی &raquo;
                                        </a>
                                    </li>
                                {/if}
                            </ul>
                        </div>
                    </div>
                {/if}
            {/if}
        </div>
    </div>
</div>

<!-- مودال شیک برای مشاهده جزئیات Payload درخواست و پاسخ -->
<div class="modal fade" id="npPayloadModal" tabindex="-1" role="dialog" aria-labelledby="npPayloadModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width: 80%; max-width: 950px;">
        <div class="modal-content">
            <div class="modal-header" style="background:#f5f8fa; border-bottom:1px solid #e1e8ed;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="npPayloadModalLabel" style="font-weight: bold; color:#2c3e50;">
                    <i class="icon-file-code-o"></i> جزئیات ارسال و دریافت وب‌سرویس: <span id="modalActionTitle" style="color:#2980b9;"></span>
                </h4>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div style="margin-bottom: 15px; display:flex; justify-content:space-between; align-items:center;">
                    <span id="modalMetaInfo" class="text-muted" style="font-size:12px;"></span>
                </div>

                <div class="row">
                    <!-- درخواست -->
                    <div class="col-md-6" id="modalReqCol">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label style="color:#0077aa; font-weight:bold; font-size:13px; margin:0;">
                                <i class="icon-arrow-up"></i> داده‌های ارسالی (Request)
                            </label>
                            <button type="button" class="btn btn-xs btn-default btn-copy-block" data-target="modalReqContent">
                                <i class="icon-copy"></i> کپی
                            </button>
                        </div>
                        <pre id="modalReqContent" style="background:#1e293b; color:#38bdf8; border:1px solid #334155; border-radius:6px; padding:12px; height:340px; overflow-y:auto; font-size:12px; font-family:monospace; direction:ltr; text-align:left;"></pre>
                    </div>

                    <!-- پاسخ -->
                    <div class="col-md-6" id="modalResCol">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label style="color:#10b981; font-weight:bold; font-size:13px; margin:0;">
                                <i class="icon-arrow-down"></i> پاسخ وب‌سرویس (Response)
                            </label>
                            <button type="button" class="btn btn-xs btn-default btn-copy-block" data-target="modalResContent">
                                <i class="icon-copy"></i> کپی
                            </button>
                        </div>
                        <pre id="modalResContent" style="background:#1e293b; color:#4ade80; border:1px solid #334155; border-radius:6px; padding:12px; height:340px; overflow-y:auto; font-size:12px; font-family:monospace; direction:ltr; text-align:left;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e1e8ed;">
                <button type="button" class="btn btn-default" data-dismiss="modal">بستن پنجره</button>
            </div>
        </div>
    </div>
</div>

<!-- توست نوتیفیکیشن کپی -->
<div id="npToast" style="display:none; position:fixed; bottom:30px; left:50%; transform:translateX(-50%); background:#1f2937; color:#fff; padding:10px 22px; border-radius:20px; font-size:13px; box-shadow:0 6px 18px rgba(0,0,0,0.3); z-index:99999; border:1px solid #374151;">
    <i class="icon-check" style="color:#10b981; margin-left:6px;"></i> <span id="npToastText">کپی شد!</span>
</div>

<style>
    .np-copyable {
        cursor: pointer;
        display: inline-block;
        transition: opacity 0.15s ease;
    }
    .np-copyable:hover {
        opacity: 0.8;
    }
    .np-action-col {
        max-width: 220px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        direction: ltr;
        text-align: left;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // فعال‌سازی Tooltip بوت‌استرپ
        if (typeof $ !== 'undefined' && $.fn.tooltip) {
            $('[data-toggle="tooltip"]').tooltip();
        }

        // تابع نمایش نوتیفیکیشن موقت
        function showToast(msg) {
            var toast = document.getElementById('npToast');
            var text = document.getElementById('npToastText');
            if (toast && text) {
                text.innerText = msg;
                toast.style.display = 'block';
                setTimeout(function () {
                    toast.style.display = 'none';
                }, 2200);
            }
        }

        // کپی متن با کلیک روی آیتم‌های np-copyable
        document.querySelectorAll('.np-copyable').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var textToCopy = this.getAttribute('data-clipboard-text') || this.innerText.trim();
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(textToCopy).then(function () {
                        showToast('با موفقیت کپی شد');
                    });
                } else {
                    var textarea = document.createElement('textarea');
                    textarea.value = textToCopy;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    showToast('با موفقیت کپی شد');
                }
            });
        });

        // باز کردن مودال نمایش داده‌های Payload
        document.querySelectorAll('.btn-view-payload').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.getAttribute('data-id');
                var action = this.getAttribute('data-action');
                var time = this.getAttribute('data-time');
                var status = this.getAttribute('data-status');

                var reqText = document.getElementById('payload_req_' + id) ? document.getElementById('payload_req_' + id).innerText : '';
                var resText = document.getElementById('payload_res_' + id) ? document.getElementById('payload_res_' + id).innerText : '';

                document.getElementById('modalActionTitle').innerText = action;
                document.getElementById('modalMetaInfo').innerText = 'زمان: ' + time + ' | کد وضعیت HTTP: ' + (status || '-');

                // فرمت‌بندی JSON در صورت امکان
                function tryFormat(raw) {
                    if (!raw || raw.trim() === '') return '(خالی)';
                    try {
                        var parsed = JSON.parse(raw);
                        return JSON.stringify(parsed, null, 2);
                    } catch (e) {
                        return raw;
                    }
                }

                document.getElementById('modalReqContent').innerText = tryFormat(reqText);
                document.getElementById('modalResContent').innerText = tryFormat(resText);

                if (typeof $ !== 'undefined') {
                    $('#npPayloadModal').modal('show');
                }
            });
        });

        // دکمه کپی کل محتوای بلاک داخل مودال
        document.querySelectorAll('.btn-copy-block').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = this.getAttribute('data-target');
                var targetEl = document.getElementById(targetId);
                if (targetEl) {
                    var text = targetEl.innerText;
                    navigator.clipboard.writeText(text).then(function () {
                        showToast('محتوا کپی شد');
                    });
                }
            });
        });
    });
</script>
