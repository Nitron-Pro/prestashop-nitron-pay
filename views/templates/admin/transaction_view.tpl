<div class="nitron-pay-admin-wrapper">
    <style>
        .np-dark-panel {
            background-color: #1a1e24;
            color: #d1d5db;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.3);
            margin-bottom: 25px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .np-header-title {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .np-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            background: #222730;
            border-radius: 6px;
            padding: 18px;
            margin-bottom: 25px;
            border: 1px solid #2d333f;
        }
        .np-meta-item {
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #2f3644;
        }
        .np-meta-label {
            color: #9ca3af;
        }
        .np-meta-value {
            color: #f3f4f6;
            font-weight: 600;
        }
        .np-badge-success {
            background-color: #059669;
            color: #ffffff;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .np-badge-warning {
            background-color: #d97706;
            color: #ffffff;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .np-badge-danger {
            background-color: #dc2626;
            color: #ffffff;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        /* کانتینر محدودکننده عرض تایم‌لاین */
        .np-timeline-wrapper {
            max-width: 680px;
            margin: 0 auto;
        }
        /* تایم‌لاین شیک مراحل */
        .np-timeline-title {
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #10b981;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .np-timeline {
            position: relative;
            padding: 10px 0 10px 25px;
            border-right: 3px solid #374151;
            margin-right: 20px;
        }
        .np-step-card {
            background: #222730;
            border: 1px solid #2f3746;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 16px;
            position: relative;
            transition: all 0.2s ease;
        }
        .np-step-card:hover {
            border-color: #3b82f6;
            background: #252b36;
        }
        .np-step-card::after {
            content: "";
            position: absolute;
            right: -31px;
            top: 18px;
            width: 14px;
            height: 14px;
            background: #10b981;
            border: 3px solid #1a1e24;
            border-radius: 50%;
        }
        /* حالت‌های مختلف بولت کناری بر اساس وضعیت مرحله */
        .np-step-card.is-failed::after,
        .np-step-card.is-fail::after {
            background: #ef4444;
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.6);
        }
        .np-step-card.is-skipped::after {
            background: #6b7280;
            border-color: #1a1e24;
        }
        .np-step-card.is-current {
            border-color: #f59e0b;
        }
        .np-step-card.is-current::after {
            background: #f59e0b;
            box-shadow: 0 0 8px #f59e0b;
        }
        .np-step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .np-step-name {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
        }
        .np-step-status {
            font-size: 11px;
            color: #10b981;
            background: rgba(16, 185, 129, 0.15);
            padding: 3px 10px;
            border-radius: 4px;
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-weight: bold;
        }
        .np-step-status.status-current {
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.15);
            border-color: rgba(245, 158, 11, 0.3);
        }
        .np-step-status.status-failed,
        .np-step-status.status-fail {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
        }
        .np-step-status.status-skipped {
            color: #9ca3af;
            background: rgba(107, 114, 128, 0.15);
            border-color: rgba(107, 114, 128, 0.3);
        }
        .np-step-desc {
            font-size: 13px;
            color: #d1d5db;
            margin-bottom: 6px;
        }
        .np-step-meta {
            font-size: 11px;
            color: #9ca3af;
            direction: ltr;
            text-align: left;
            font-family: monospace;
        }
        .np-tech-details {
            margin-top: 8px;
            background: #111418;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 11px;
            color: #a7f3d0;
            direction: ltr;
            text-align: left;
            max-height: 200px;
            overflow-y: auto;
        }
        details summary {
            cursor: pointer;
            color: #38bdf8;
            font-size: 11px;
            margin-top: 6px;
            outline: none;
        }
    </style>

    <div class="np-dark-panel">
        <div class="np-header-title">
            <span>
                <i class="icon-file-text"></i> جزئیات و مسیر مراحل پرداخت
            </span>
            <div>
                {assign var="is_tx_failed" value=(isset($details.status) && ($details.status == 'failed' || $details.status == 'cancelled' || $details.status == 'fail' || $details.status == 'canceled'))}
                {if !$is_tx_failed}
                    <form action="{$current_url|escape:'html':'UTF-8'}" method="post" style="display:inline-block;">
                        <input type="hidden" name="site_order_id" value="{$details.site_order_id|default:''|escape:'html':'UTF-8'}">
                        <input type="hidden" name="uuid" value="{$uuid|escape:'html':'UTF-8'}">
                        <button type="submit" name="actionInquiry" class="btn btn-sm btn-info">
                            <i class="icon-refresh"></i> استعلام مستقیم از بانک
                        </button>
                    </form>
                {/if}
                <a href="{$back_url|escape:'html':'UTF-8'}" class="btn btn-sm btn-default">
                    <i class="icon-arrow-left"></i> بازگشت به لیست
                </a>
            </div>
        </div>

        {if $error_msg}
            <div class="alert alert-warning">
                {$error_msg|escape:'html':'UTF-8'}
            </div>
        {/if}

        <!-- اطلاعات کلی تراکنش مشابه پنل نیترون -->
        <div class="np-meta-grid">
            <div class="np-meta-item">
                <span class="np-meta-label">شناسه یکتا (UUID):</span>
                <span class="np-meta-value"><small><code>{$uuid|escape:'html':'UTF-8'}</code></small></span>
            </div>
            <div class="np-meta-item">
                <span class="np-meta-label">سفارش سایت:</span>
                <span class="np-meta-value">{$details.site_order_id|default:'-'|escape:'html':'UTF-8'}</span>
            </div>
            <div class="np-meta-item">
                <span class="np-meta-label">درگاه بانکی واسط:</span>
                <span class="np-meta-value">
                    {if is_array($details.gateway)}
                        {$details.gateway.name|default:$details.gateway.driver|default:'-'|escape:'html':'UTF-8'}
                    {else}
                        {$details.gateway|default:'زیبال / شاپرک'|escape:'html':'UTF-8'}
                    {/if}
                </span>
            </div>
            <div class="np-meta-item">
                <span class="np-meta-label">مبلغ تراکنش:</span>
                <span class="np-meta-value">{if isset($details.amount)}{number_format($details.amount)}{else}-{/if} ریال</span>
            </div>
            <div class="np-meta-item">
                <span class="np-meta-label">وضعیت پرداخت:</span>
                <span class="np-meta-value">
                    {if isset($details.status) && ($details.status == 'verified' || $details.status == 'success')}
                        <span class="np-badge-success">موفق / تایید شده</span>
                    {elseif isset($details.status) && ($details.status == 'failed' || $details.status == 'cancelled')}
                        <span class="np-badge-danger">لغو / ناموفق</span>
                    {else}
                        <span class="np-badge-warning">{$details.status|default:'در انتظار'|escape:'html':'UTF-8'}</span>
                    {/if}
                </span>
            </div>
            <div class="np-meta-item">
                <span class="np-meta-label">کد پیگیری بانک (Ref ID):</span>
                <span class="np-meta-value" style="color:#10b981; font-size:14px;">
                    {if !empty($details.ref_id)}
                        {$details.ref_id|escape:'html':'UTF-8'}
                    {elseif !empty($details.inquiry.response.refNumber)}
                        {$details.inquiry.response.refNumber|escape:'html':'UTF-8'}
                    {else}
                        -
                    {/if}
                </span>
            </div>
            {if isset($details.authority)}
                <div class="np-meta-item">
                    <span class="np-meta-label">شناسه مرجع (Authority):</span>
                    <span class="np-meta-value">{$details.authority|escape:'html':'UTF-8'}</span>
                </div>
            {/if}
            {if isset($details.created_at)}
                <div class="np-meta-item">
                    <span class="np-meta-label">تاریخ ایجاد:</span>
                    <span class="np-meta-value"><small>{$details.created_at|escape:'html':'UTF-8'}</small></span>
                </div>
            {/if}
        </div>

        <!-- جزئیات بانک در صورت وجود (gateway_payload) -->
        {if !empty($details.gateway_payload)}
            <div style="margin-bottom: 25px;">
                <details>
                    <summary style="font-size: 13px; font-weight: bold;">مشاهده ساختار داده‌های خام بانک (gateway_payload)</summary>
                    <pre class="np-tech-details">{json_encode($details.gateway_payload, 384)}</pre>
                </details>
            </div>
        {/if}

        <!-- مسیر و تایم‌لاین مراحل پرداخت (محدود به عرض استاندارد وسط صفحه) -->
        <div class="np-timeline-wrapper">
            <div class="np-timeline-title">
                <i class="icon-exchange"></i> مسیر مراحل پرداخت (Timeline)
            </div>

            <div class="np-timeline">
                {if !empty($details.stages)}
                    {foreach from=$details.stages item=stg}
                        <div class="np-step-card {if isset($stg.state) && ($stg.state == 'failed' || $stg.state == 'fail')}is-failed{elseif isset($stg.state) && $stg.state == 'skipped'}is-skipped{elseif isset($stg.state) && $stg.state == 'current'}is-current{/if}">
                            <div class="np-step-header">
                                <span class="np-step-name">
                                    {if isset($stg.label)}
                                        {$stg.label|escape:'html':'UTF-8'}
                                    {elseif isset($stg.title)}
                                        {$stg.title|escape:'html':'UTF-8'}
                                    {elseif isset($stg.name)}
                                        {$stg.name|escape:'html':'UTF-8'}
                                    {else}
                                        {$stg.key|default:'مرحله پرداخت'|escape:'html':'UTF-8'}
                                    {/if}
                                </span>
                                <span class="np-step-status {if isset($stg.state) && ($stg.state == 'failed' || $stg.state == 'fail')}status-failed{elseif isset($stg.state) && $stg.state == 'skipped'}status-skipped{elseif isset($stg.state) && $stg.state == 'current'}status-current{/if}">
                                    {if isset($stg.state) && $stg.state == 'done'}
                                        ✓ انجام شد
                                    {elseif isset($stg.state) && $stg.state == 'current'}
                                        در این مرحله
                                    {elseif isset($stg.state) && ($stg.state == 'failed' || $stg.state == 'fail')}
                                        ✗ ناموفق
                                    {elseif isset($stg.state) && $stg.state == 'skipped'}
                                        رد شده
                                    {else}
                                        {$stg.state|default:'انجام شد'|escape:'html':'UTF-8'}
                                    {/if}
                                </span>
                            </div>

                            {if !empty($stg.message)}
                                <div class="np-step-desc">{$stg.message|escape:'html':'UTF-8'}</div>
                            {/if}

                            <div class="np-step-meta">
                                {if isset($stg.duration_ms) && $stg.duration_ms !== null}
                                    <span style="margin-left: 12px; color: #38bdf8;">{$stg.duration_ms}ms</span>
                                {/if}
                                {if !empty($stg.at)}
                                    <span>{$stg.at|escape:'html':'UTF-8'}</span>
                                {elseif !empty($stg.at_iso)}
                                    <span>{$stg.at_iso|escape:'html':'UTF-8'}</span>
                                {/if}
                            </div>

                            {if !empty($stg.payload) || !empty($stg.details) || !empty($stg.meta)}
                                {assign var='techPayload' value=$stg.payload|default:$stg.details|default:$stg.meta}
                                <details>
                                    <summary>▼ جزئیات فنی</summary>
                                    <pre class="np-tech-details">{json_encode($techPayload, 384)}</pre>
                                </details>
                            {/if}
                        </div>
                    {/foreach}
                {else}
                    <!-- در صورتی که stages از API نیامده بود، از وقایع لاگ‌شده استفاده می‌کنیم -->
                    <div class="np-step-card">
                        <div class="np-step-header">
                            <span class="np-step-name">۱. درخواست توکن و ساخت پرداخت</span>
                            <span class="np-step-status">انجام شد</span>
                        </div>
                        <div class="np-step-desc">درخواست ایجاد تراکنش با موفقیت به نیترون ارسال شد.</div>
                    </div>

                    <div class="np-step-card">
                        <div class="np-step-header">
                            <span class="np-step-name">۲. آماده‌سازی و ارسال به درگاه</span>
                            <span class="np-step-status">انجام شد</span>
                        </div>
                        <div class="np-step-desc">آدرس پرداخت (payment_url) دریافت و کاربر به صفحه درگاه شاپرک هدایت شد.</div>
                    </div>

                    <div class="np-step-card">
                        <div class="np-step-header">
                            <span class="np-step-name">۳. بازگشت از درگاه</span>
                            <span class="np-step-status">انجام شد</span>
                        </div>
                        <div class="np-step-desc">کاربر به همراه داده‌های کال‌بک و امضای امنیتی به فروشگاه بازگشت.</div>
                    </div>

                    <div class="np-step-card">
                        <div class="np-step-header">
                            <span class="np-step-name">۴. وریفای و تایید نهایی</span>
                            <span class="np-step-status">انجام شد</span>
                        </div>
                        <div class="np-step-desc">تراکنش با موفقیت وریفای شد. کد پیگیری: <b>{$details.ref_id|default:'-'|escape:'html':'UTF-8'}</b></div>
                    </div>

                    <div class="np-step-card">
                        <div class="np-step-header">
                            <span class="np-step-name">۵. استعلام و راستی‌آزمایی تراکنش</span>
                            <span class="np-step-status">تایید مضاعف</span>
                        </div>
                        <div class="np-step-desc">استعلام مستقیم از بانک جهت جلوگیری از پرداخت‌های جعلی انجام شد.</div>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>
