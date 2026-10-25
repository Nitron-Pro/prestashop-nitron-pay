<div class="nitron-pay-admin-wrapper">
    {include file='./_nav_tabs.tpl'}

    <div class="panel">
        <div class="panel-heading">
            <i class="icon-bar-chart"></i> گزارش پرداخت‌های موفق
        </div>
        <div class="panel-body">
            <form action="{$current_url|escape:'html':'UTF-8'}" method="get" class="form-horizontal">
                <input type="hidden" name="controller" value="AdminNitronPayReports">
                <input type="hidden" name="token" value="{$admin_token|escape:'html':'UTF-8'}">
                <div class="row">
                    <div class="col-lg-4">
                        <label>بازه زمانی:</label>
                        <select name="report_period" class="form-control" onchange="this.form.submit();">
                            <option value="daily" {if $report_period == 'daily'}selected="selected"{/if}>امروز</option>
                            <option value="weekly" {if $report_period == 'weekly'}selected="selected"{/if}>۷ روز اخیر</option>
                            <option value="monthly" {if $report_period == 'monthly'}selected="selected"{/if}>ماه جاری</option>
                            <option value="custom" {if $report_period == 'custom'}selected="selected"{/if}>بازه دلخواه</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label>از تاریخ:</label>
                        <input type="date" name="filter_date_from" value="{$filters.date_from|escape:'html':'UTF-8'}" class="form-control">
                    </div>
                    <div class="col-lg-3">
                        <label>تا تاریخ:</label>
                        <input type="date" name="filter_date_to" value="{$filters.date_to|escape:'html':'UTF-8'}" class="form-control">
                    </div>
                    <div class="col-lg-2" style="padding-top: 24px;">
                        <button type="submit" class="btn btn-primary"><i class="icon-search"></i> نمایش</button>
                    </div>
                </div>
            </form>

            <div class="np-kpi" style="margin-top: 18px;">
                <div class="np-kpi-box">
                    <span class="text-muted">بازه</span>
                    <b>{$range.label|escape:'html':'UTF-8'}</b>
                    <small class="text-muted">{$range.from|escape:'html':'UTF-8'} تا {$range.to|escape:'html':'UTF-8'}</small>
                </div>
                <div class="np-kpi-box">
                    <span class="text-muted">تعداد پرداخت موفق</span>
                    <b class="text-success">{$summary.count|intval}</b>
                </div>
                <div class="np-kpi-box">
                    <span class="text-muted">جمع مبلغ</span>
                    <b>{number_format($summary.sum_rial)} ریال</b>
                </div>
            </div>

            <form action="{$current_url|escape:'html':'UTF-8'}" method="post" style="margin-bottom: 15px;">
                <input type="hidden" name="report_period" value="{$report_period|escape:'html':'UTF-8'}">
                <input type="hidden" name="filter_date_from" value="{$filters.date_from|escape:'html':'UTF-8'}">
                <input type="hidden" name="filter_date_to" value="{$filters.date_to|escape:'html':'UTF-8'}">
                <button type="submit" name="exportExcel" value="1" class="btn btn-success">
                    <i class="icon-download"></i> خروجی xlsx همین بازه
                </button>
                <span class="help-block" style="display:inline; margin-right:10px;">
                    نام فایل شامل بازه و زمان خروجی است تا با فایل‌های قبلی قاطی نشود.
                </span>
            </form>

            {if empty($rows)}
                <div class="alert alert-info">در این بازه پرداخت موفقی ثبت نشده است.</div>
            {else}
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>تاریخ</th>
                                <th>مشتری</th>
                                <th>سفارش</th>
                                <th>مبلغ (ریال)</th>
                                <th>کد پیگیری</th>
                                <th>کارت</th>
                                <th>درگاه</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$rows item=row}
                                <tr>
                                    <td><small>{$row.date_add|escape:'html':'UTF-8'}</small></td>
                                    <td>
                                        <b>{$row.customer_name|default:'-'|escape:'html':'UTF-8'}</b>
                                        <div><small>{$row.customer_email|default:''|escape:'html':'UTF-8'}</small></div>
                                        <div><small>{$row.customer_phone|default:''|escape:'html':'UTF-8'}</small></div>
                                    </td>
                                    <td>
                                        {if $row.id_order}
                                            <a href="?controller=AdminOrders&id_order={$row.id_order|intval}&vieworder&token={Tools::getAdminTokenLite('AdminOrders')}">
                                                #{$row.id_order|intval} {if $row.order_reference}({$row.order_reference|escape:'html':'UTF-8'}){/if}
                                            </a>
                                        {else}-{/if}
                                    </td>
                                    <td>{number_format($row.amount_rial)}</td>
                                    <td><b>{$row.ref_id|default:'-'|escape:'html':'UTF-8'}</b></td>
                                    <td>{$row.card_pan|default:'-'|escape:'html':'UTF-8'}</td>
                                    <td>{$row.gateway_name|default:'-'|escape:'html':'UTF-8'}</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            {/if}
        </div>
    </div>
    {include file='./_admin_assets.tpl'}
</div>
