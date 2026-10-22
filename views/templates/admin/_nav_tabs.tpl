<div class="panel" style="margin-bottom: 15px;">
    <div class="panel-body" style="padding: 10px 15px;">
        <ul class="nav nav-pills" style="margin: 0;">
            <li class="{if $active_np_tab == 'transactions'}active{/if}">
                <a href="{$transactions_url|escape:'html':'UTF-8'}">
                    <i class="icon-credit-card"></i> تراکنش‌ها و مشتریان
                </a>
            </li>
            <li class="{if $active_np_tab == 'logs'}active{/if}">
                <a href="{$logs_tab_url|escape:'html':'UTF-8'}">
                    <i class="icon-list-alt"></i> لاگ‌های فنی
                </a>
            </li>
            <li class="{if $active_np_tab == 'reports'}active{/if}">
                <a href="{$reports_url|escape:'html':'UTF-8'}">
                    <i class="icon-bar-chart"></i> گزارش پرداخت‌های موفق
                </a>
            </li>
            <li>
                <a href="{$config_url|escape:'html':'UTF-8'}">
                    <i class="icon-cogs"></i> تنظیمات
                </a>
            </li>
        </ul>
        <p class="help-block" style="margin: 8px 0 0;">
            هر تب جداگانه لود می‌شود تا صفحه برای فروشگاه‌های پربازدید سنگین نشود.
        </p>
    </div>
</div>
