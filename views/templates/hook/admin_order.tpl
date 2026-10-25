<div class="card mt-2" id="nitron-pay-order-card">
    <div class="card-header">
        <h3 class="card-header-title">
            <i class="material-icons">payment</i>
            پرداخت نیترون پی
        </h3>
    </div>
    <div class="card-body">
        {if $np_transaction}
            <p>
                <b>آخرین وضعیت:</b>
                {if $np_status_meta}
                    <span class="badge badge-{if $np_status_meta.class == 'default'}secondary{else}{$np_status_meta.class|escape:'html':'UTF-8'}{/if}">{$np_status_meta.label|escape:'html':'UTF-8'}</span>
                {else}
                    {$np_transaction->status|escape:'html':'UTF-8'}
                {/if}
            </p>
            <p><b>مبلغ:</b> {number_format($np_transaction->amount_rial)} ریال</p>
            <p class="mb-2"><b>کد پیگیری:</b> {$np_transaction->ref_id|default:'-'|escape:'html':'UTF-8'}</p>
            {if $np_timeline_url}
                <p class="mb-0">
                    <a href="{$np_timeline_url|escape:'html':'UTF-8'}">مشاهده مسیر مراحل</a>
                </p>
            {/if}
        {else}
            <p class="mb-0">هنوز تراکنش نیترون برای این سفارش ثبت نشده است.</p>
        {/if}

        {if $np_can_retry && $np_retry_url}
            <p class="mt-3 mb-2">این لینک را برای مشتری بفرستید تا همان سفارش را بدون ساخت سبد جدید پرداخت کند.</p>
            <input type="text" class="form-control" readonly="readonly" value="{$np_retry_url|escape:'html':'UTF-8'}" onclick="this.select();">
            <p class="mt-2 mb-0">
                <a class="btn btn-warning" href="{$np_retry_url|escape:'html':'UTF-8'}" target="_blank">
                    باز کردن لینک پرداخت
                </a>
            </p>
        {/if}
    </div>
</div>
