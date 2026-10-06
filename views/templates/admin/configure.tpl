<div class="nitron-pay-admin-wrapper">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-credit-card"></i> تنظیمات درگاه پرداخت اینترنتی نیترون پی (NitronPay)
            <span class="pull-left" style="margin-left: 10px;">
                <a href="{$logs_url|escape:'html':'UTF-8'}" class="btn btn-xs btn-default" style="margin-right: 8px;">
                    <i class="icon-list-alt"></i> مشاهده لاگ‌ها و تراکنش‌ها
                </a>
            </span>
        </div>
        <div class="panel-body">
            <form action="{$current_url|escape:'html':'UTF-8'}" method="post" class="form-horizontal">

                <div class="form-group">
                    <label class="control-label col-lg-3 required">آدرس وب‌سرویس (Base URL):</label>
                    <div class="col-lg-6">
                        <input type="text" name="NITRON_PAY_BASE_URL" value="{$base_url|escape:'html':'UTF-8'}" class="form-control" required>
                        <p class="help-block">آدرس پایه سرور درگاه نیترون پی (پیش‌فرض: https://pay.aryashid.ir)</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-3 required">کلید دسترسی (API Key):</label>
                    <div class="col-lg-6">
                        <input type="text" name="NITRON_PAY_API_KEY" value="{$api_key|escape:'html':'UTF-8'}" class="form-control" placeholder="کلید دریافتی از پنل نیترون" required>
                        <p class="help-block">این کلید در هدر X-API-Key ارسال می‌شود.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-3">کلید رمزنگاری امضا (API Secret):</label>
                    <div class="col-lg-6">
                        <input type="password" name="NITRON_PAY_API_SECRET" value="{$api_secret|escape:'html':'UTF-8'}" class="form-control" placeholder="اختیاری / جهت اعتبارسنجی امضای HMAC">
                        <p class="help-block">برای بررسی صحت امضای کال‌بک بازگشتی (HMAC-SHA256) استفاده می‌شود.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-3">عنوان درگاه در تسویه‌حساب:</label>
                    <div class="col-lg-6">
                        <input type="text" name="NITRON_PAY_TITLE" value="{$gateway_title|escape:'html':'UTF-8'}" class="form-control">
                        <p class="help-block">عنوانی که در مرحله نهایی انتخاب روش پرداخت به خریدار نمایش داده می‌شود.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-3">نحوه تبدیل واحد پولی به ریال:</label>
                    <div class="col-lg-6">
                        <select name="NITRON_PAY_CURRENCY_UNIT" class="form-control">
                            <option value="auto" {if $currency_unit == 'auto'}selected="selected"{/if}>تشخیص خودکار بر اساس واحد پولی سبد خرید (توصیه می‌شود)</option>
                            <option value="toman" {if $currency_unit == 'toman'}selected="selected"{/if}>همیشه ضرب‌در ۱۰ شود (واحد فروشگاه تومان است)</option>
                            <option value="rial" {if $currency_unit == 'rial'}selected="selected"{/if}>بدون تغییر ارسال شود (واحد فروشگاه ریال است)</option>
                        </select>
                        <p class="help-block">
                            مبالغ ارسالی به نیترون باید حتماً به <b>ریال</b> باشند. در حالت خودکار، اگر واحد پول فروشگاه تومان (TMN/IRT) باشد خودکار ضرب در ۱۰ خواهد شد.
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-3">استعلام مضاعف امنیتی (Inquiry):</label>
                    <div class="col-lg-6">
                        <span class="switch prestashop-switch fixed-width-lg">
                            <input type="radio" name="NITRON_PAY_ENABLE_INQUIRY" id="NITRON_PAY_ENABLE_INQUIRY_on" value="1" {if $enable_inquiry}checked="checked"{/if}>
                            <label for="NITRON_PAY_ENABLE_INQUIRY_on">بله</label>
                            <input type="radio" name="NITRON_PAY_ENABLE_INQUIRY" id="NITRON_PAY_ENABLE_INQUIRY_off" value="0" {if !$enable_inquiry}checked="checked"{/if}>
                            <label for="NITRON_PAY_ENABLE_INQUIRY_off">خیر</label>
                            <a class="slide-button btn"></a>
                        </span>
                        <p class="help-block">
                            در صورت فعال بودن (پیش‌فرض)، پس از بازگشت موفق، وضعیت نهایی تراکنش مستقیماً از بانک استعلام می‌شود تا از ثبت هرگونه پرداخت جعلی کاملاً جلوگیری گردد.
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-3">وضعیت سفارش پس از پرداخت موفق:</label>
                    <div class="col-lg-6">
                        <select name="NITRON_PAY_OS_SUCCESS" class="form-control">
                            {foreach from=$order_states item=os}
                                <option value="{$os.id_order_state|escape:'html':'UTF-8'}" {if $os_success == $os.id_order_state}selected="selected"{/if}>
                                    {$os.name|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-3">وضعیت سفارش در صورت لغو یا خطا:</label>
                    <div class="col-lg-6">
                        <select name="NITRON_PAY_OS_FAILED" class="form-control">
                            {foreach from=$order_states item=os}
                                <option value="{$os.id_order_state|escape:'html':'UTF-8'}" {if $os_failed == $os.id_order_state}selected="selected"{/if}>
                                    {$os.name|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    </div>
                </div>

                <div class="panel-footer">
                    <button type="submit" name="submitNitronPaySettings" class="btn btn-default pull-right">
                        <i class="process-icon-save"></i> ذخیره تنظیمات
                    </button>
                    <button type="submit" name="btnTestConnection" class="btn btn-info pull-left">
                        <i class="icon-refresh"></i> تست اتصال با سرور نیترون
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
