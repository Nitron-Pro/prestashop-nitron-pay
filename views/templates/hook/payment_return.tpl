{if $status == 'ok'}
    <div class="box order-confirmation alert alert-success">
        <h3 class="alert-heading">{l s='پرداخت با موفقیت انجام شد' mod='nitron_pay'}</h3>
        <p>{l s='سفارش شما در فروشگاه %s با موفقیت ثبت شد.' sprintf=[$shop_name] mod='nitron_pay'}</p>
        <ul>
            <li>{l s='شماره سفارش:' mod='nitron_pay'} <strong>#{$order->id|escape:'html':'UTF-8'}</strong></li>
            <li>{l s='کد مرجع سفارش:' mod='nitron_pay'} <strong>{$order->reference|escape:'html':'UTF-8'}</strong></li>
            {if $transaction && $transaction->ref_id}
                <li>{l s='کد رهگیری بانکی (شماره پیگیری):' mod='nitron_pay'} <strong>{$transaction->ref_id|escape:'html':'UTF-8'}</strong></li>
            {/if}
            {if $transaction && $transaction->card_pan}
                <li>{l s='شماره کارت پرداخت‌کننده:' mod='nitron_pay'} <strong>{$transaction->card_pan|escape:'html':'UTF-8'}</strong></li>
            {/if}
            <li>{l s='مبلغ پرداخت‌شده:' mod='nitron_pay'} <strong>{if isset($total_to_pay)}{$total_to_pay}{else}{Tools::displayPrice($order->total_paid, $order_currency)}{/if}</strong></li>
        </ul>
        <p>{l s='ایمیل تایید سفارش به آدرس ایمیل شما ارسال شده است.' mod='nitron_pay'}</p>
    </div>
{else}
    <div class="box order-confirmation alert alert-warning">
        <h3 class="alert-heading">{l s='وضعیت پرداخت نامشخص یا ناتمام است' mod='nitron_pay'}</h3>
        <p>{l s='سفارش شما با شناسه #%s ثبت شده است اما هنوز تایید پرداخت قطعی از درگاه دریافت نشده است.' sprintf=[$order->id] mod='nitron_pay'}</p>
        <p>{l s='در صورت کسر وجه از حساب شما و عدم بازگشت طی ۷۲ ساعت، با پشتیبانی فروشگاه تماس بگیرید.' mod='nitron_pay'}</p>
    </div>
{/if}
