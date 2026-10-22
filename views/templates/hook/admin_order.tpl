<div class="panel">
    <div class="panel-heading">
        <i class="icon-credit-card"></i> پرداخت نیترون پی
    </div>
    <div class="panel-body">
        {if $np_transaction}
            <p>
                <b>آخرین وضعیت:</b>
                {if $np_status_meta}
                    <span class="label label-{$np_status_meta.class|escape:'html':'UTF-8'}">{$np_status_meta.label|escape:'html':'UTF-8'}</span>
                {else}
                    {$np_transaction->status|escape:'html':'UTF-8'}
                {/if}
            </p>
            <p><b>مبلغ:</b> {number_format($np_transaction->amount_rial)} ریال</p>
            <p><b>کد پیگیری:</b> {$np_transaction->ref_id|default:'-'|escape:'html':'UTF-8'}</p>
            {if $np_timeline_url}
                <p>
                    <a href="{$np_timeline_url|escape:'html':'UTF-8'}">مشاهده مسیر مراحل</a>
                </p>
            {/if}
        {else}
            <p>هنوز تراکنش نیترون برای این سفارش ثبت نشده است.</p>
        {/if}

        {if $np_can_retry && $np_retry_url}
            <p class="help-block">این لینک را برای مشتری بفرستید تا همان سفارش را بدون ساخت سبد جدید پرداخت کند.</p>
            <input type="text" class="form-control" readonly="readonly" value="{$np_retry_url|escape:'html':'UTF-8'}" onclick="this.select();">
            <p style="margin-top:8px;">
                <a class="btn btn-warning" href="{$np_retry_url|escape:'html':'UTF-8'}" target="_blank">
                    <i class="icon-link"></i> باز کردن لینک پرداخت
                </a>
            </p>
        {/if}
    </div>
</div>
