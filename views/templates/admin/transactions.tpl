<div class="nitron-pay-admin-wrapper">
    {include file='./_nav_tabs.tpl'}

    <div class="np-kpi">
        <div class="np-kpi-box">
            <span class="text-muted">موفق امروز</span>
            <b class="text-success">{$today_kpi.success_count|default:0|intval}</b>
        </div>
        <div class="np-kpi-box">
            <span class="text-muted">در انتظار / رهاشده امروز</span>
            <b class="text-info">{$today_kpi.pending_count|default:0|intval}</b>
        </div>
        <div class="np-kpi-box">
            <span class="text-muted">ناموفق امروز</span>
            <b class="text-danger">{$today_kpi.failed_count|default:0|intval}</b>
        </div>
        <div class="np-kpi-box">
            <span class="text-muted">مبلغ موفق امروز</span>
            <b>{if isset($today_kpi.success_sum)}{number_format($today_kpi.success_sum)}{else}0{/if} ریال</b>
        </div>
    </div>

    <div class="panel">
        <div class="panel-heading">
            <i class="icon-search"></i> جستجوی تراکنش، مشتری، سبد و سفارش
        </div>
        <div class="panel-body">
            <form action="{$current_url|escape:'html':'UTF-8'}" method="get" class="form-horizontal">
                <input type="hidden" name="controller" value="AdminNitronPayLogs">
                <input type="hidden" name="token" value="{$admin_token|escape:'html':'UTF-8'}">
                <input type="hidden" name="np_tab" value="transactions">
                <div class="row">
                    <div class="col-lg-4">
                        <label>جستجو:</label>
                        <input type="text" name="filter_q" value="{$filters.q|escape:'html':'UTF-8'}" class="form-control" placeholder="نام، ایمیل، موبایل، سفارش، سبد، UUID، کد پیگیری">
                    </div>
                    <div class="col-lg-2">
                        <label>وضعیت پرداخت:</label>
                        <select name="filter_tx_group" class="form-control">
                            <option value="">همه</option>
                            <option value="success" {if $filters.tx_group == 'success'}selected="selected"{/if}>موفق</option>
                            <option value="pending" {if $filters.tx_group == 'pending'}selected="selected"{/if}>در انتظار / ارسال به درگاه</option>
                            <option value="failed" {if $filters.tx_group == 'failed'}selected="selected"{/if}>ناموفق / لغو</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label>از تاریخ:</label>
                        <input type="date" name="filter_date_from" value="{$filters.date_from|escape:'html':'UTF-8'}" class="form-control">
                    </div>
                    <div class="col-lg-2">
                        <label>تا تاریخ:</label>
                        <input type="date" name="filter_date_to" value="{$filters.date_to|escape:'html':'UTF-8'}" class="form-control">
                    </div>
                    <div class="col-lg-2" style="padding-top: 24px;">
                        <button type="submit" class="btn btn-primary"><i class="icon-search"></i> اعمال</button>
                        <a href="{$transactions_url|escape:'html':'UTF-8'}" class="btn btn-default"><i class="icon-eraser"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-heading">
            <i class="icon-credit-card"></i> تراکنش‌ها
            <span class="badge">{$tx_total|default:0|number_format}</span>
        </div>
        <div class="panel-body">
            {if empty($transactions)}
                <div class="alert alert-info">تراکنشی با این فیلتر یافت نشد.</div>
            {else}
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>مشتری و تماس</th>
                                <th>سبد / سفارش</th>
                                <th>مبلغ</th>
                                <th>وضعیت پرداخت</th>
                                <th>پیگیری</th>
                                <th>تاریخ</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$transactions item=tx}
                                <tr>
                                    <td>
                                        {if $tx.customer_name}
                                            {if $tx.id_customer}
                                                <a href="?controller=AdminCustomers&id_customer={$tx.id_customer|intval}&viewcustomer&token={Tools::getAdminTokenLite('AdminCustomers')}">
                                                    <b>{$tx.customer_name|escape:'html':'UTF-8'}</b>
                                                </a>
                                            {else}
                                                <b>{$tx.customer_name|escape:'html':'UTF-8'}</b>
                                            {/if}
                                        {else}
                                            <span class="text-muted">مشتری ثبت نشده</span>
                                        {/if}
                                        <div>
                                            {if $tx.customer_email}
                                                <a href="mailto:{$tx.customer_email|escape:'html':'UTF-8'}">{$tx.customer_email|escape:'html':'UTF-8'}</a>
                                            {/if}
                                        </div>
                                        {if $tx.customer_phone}
                                            <div>
                                                <a href="tel:{$tx.customer_phone|escape:'html':'UTF-8'}">{$tx.customer_phone|escape:'html':'UTF-8'}</a>
                                                <span class="np-copyable" data-clipboard-text="{$tx.customer_phone|escape:'html':'UTF-8'}" title="کپی موبایل"><i class="icon-copy"></i></span>
                                            </div>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $tx.id_cart}
                                            <div>
                                                سبد:
                                                <a href="?controller=AdminCarts&id_cart={$tx.id_cart|intval}&viewcart&token={Tools::getAdminTokenLite('AdminCarts')}">#{$tx.id_cart|intval}</a>
                                            </div>
                                        {/if}
                                        {if $tx.id_order}
                                            <div>
                                                سفارش:
                                                <a href="?controller=AdminOrders&id_order={$tx.id_order|intval}&vieworder&token={Tools::getAdminTokenLite('AdminOrders')}">
                                                    #{$tx.id_order|intval}{if $tx.order_reference} ({$tx.order_reference|escape:'html':'UTF-8'}){/if}
                                                </a>
                                            </div>
                                        {else}
                                            <span class="label label-default">هنوز سفارش ندارد</span>
                                        {/if}
                                        <div>
                                            <small class="np-copyable" data-clipboard-text="{$tx.site_order_id|escape:'html':'UTF-8'}" title="کپی شناسه سایت">
                                                <code>{$tx.site_order_id|escape:'html':'UTF-8'}</code>
                                            </small>
                                        </div>
                                    </td>
                                    <td>{number_format($tx.amount_rial)} ریال</td>
                                    <td>
                                        <span class="label label-{$tx.status_meta.class|escape:'html':'UTF-8'}">
                                            {$tx.status_meta.label|escape:'html':'UTF-8'}
                                        </span>
                                    </td>
                                    <td>
                                        <b>{$tx.ref_id|default:'-'|escape:'html':'UTF-8'}</b>
                                        {if $tx.card_pan}<div><small>{$tx.card_pan|escape:'html':'UTF-8'}</small></div>{/if}
                                    </td>
                                    <td><small>{$tx.date_add|escape:'html':'UTF-8'}</small></td>
                                    <td>
                                        {if $tx.uuid}
                                            <a href="{$current_url|escape:'html':'UTF-8'}&view_transaction_uuid={$tx.uuid|escape:'html':'UTF-8'}" class="btn btn-xs btn-info">
                                                <i class="icon-eye"></i> جزئیات
                                            </a>
                                        {/if}
                                        {if $tx.retry_url}
                                            <button type="button" class="btn btn-xs btn-warning np-copyable" data-clipboard-text="{$tx.retry_url|escape:'html':'UTF-8'}" title="کپی لینک پرداخت برای ارسال به مشتری">
                                                <i class="icon-link"></i> لینک پرداخت
                                            </button>
                                        {/if}
                                        {if $tx.can_inquiry}
                                            <form action="{$current_url|escape:'html':'UTF-8'}" method="post" style="display:inline-block;">
                                                <input type="hidden" name="site_order_id" value="{$tx.site_order_id|escape:'html':'UTF-8'}">
                                                <input type="hidden" name="uuid" value="{$tx.uuid|escape:'html':'UTF-8'}">
                                                <button type="submit" name="actionInquiry" class="btn btn-xs btn-default">
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

                {if $total_pages > 1}
                    <ul class="pagination">
                        {for $p=1 to $total_pages}
                            {if $p > $current_page - 3 && $p < $current_page + 3}
                                <li class="{if $p == $current_page}active{/if}">
                                    <a href="{$current_url|escape:'html':'UTF-8'}&np_tab=transactions&page={$p}&filter_q={$filters.q|escape:'html':'UTF-8'}&filter_tx_group={$filters.tx_group|escape:'html':'UTF-8'}&filter_date_from={$filters.date_from|escape:'html':'UTF-8'}&filter_date_to={$filters.date_to|escape:'html':'UTF-8'}">{$p}</a>
                                </li>
                            {/if}
                        {/for}
                    </ul>
                {/if}
            {/if}
        </div>
    </div>
    {include file='./_admin_assets.tpl'}
</div>
