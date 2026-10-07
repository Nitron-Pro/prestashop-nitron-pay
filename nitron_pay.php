<?php
/**
 * 2026 Nitron
 *
 * @author    nitron
 * @copyright 2026 Nitron
 * @license   Commercial License
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/NitronPayApi.php';
require_once __DIR__ . '/classes/NitronPayLogger.php';
require_once __DIR__ . '/classes/NitronPayTransaction.php';

class Nitron_Pay extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'nitron_pay';
        $this->tab = 'payments_gateways';
        $this->version = '1.2.0';
        $this->author = 'nitron';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->controllers = ['payment', 'validation'];

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('درگاه پرداخت نیترون (NitronPay)');
        $this->description = $this->l('درگاه پرداخت هوشمند اینترنتی چندبانکه نیترون پی برای پرستاشاپ');
        $this->confirmUninstall = $this->l('آیا از حذف ماژول درگاه پرداخت نیترون اطمینان دارید؟');
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (!$this->executeSqlFile(__DIR__ . '/sql/install.sql')) {
            return false;
        }

        if (!$this->installTabs()) {
            return false;
        }

        // مقادیر پیش‌فرض پیکربندی
        Configuration::updateValue('NITRON_PAY_API_KEY', '');
        Configuration::updateValue('NITRON_PAY_API_SECRET', '');
        Configuration::updateValue('NITRON_PAY_BASE_URL', 'https://app.nitron.pro/pay');
        Configuration::updateValue('NITRON_PAY_TITLE', 'پرداخت آنلاین نیترون (کارت‌های عضو شتاب)');
        Configuration::updateValue('NITRON_PAY_CURRENCY_UNIT', 'auto'); // auto, rial, toman
        Configuration::updateValue('NITRON_PAY_ENABLE_INQUIRY', 1); // استعلام مضاعف امنیتی از بانک
        Configuration::updateValue('NITRON_PAY_OS_SUCCESS', (int) Configuration::get('PS_OS_PAYMENT'));
        Configuration::updateValue('NITRON_PAY_OS_FAILED', (int) Configuration::get('PS_OS_ERROR'));

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayPaymentReturn');
    }

    public function uninstall()
    {
        $this->uninstallTabs();
        $this->executeSqlFile(__DIR__ . '/sql/uninstall.sql');

        Configuration::deleteByName('NITRON_PAY_API_KEY');
        Configuration::deleteByName('NITRON_PAY_API_SECRET');
        Configuration::deleteByName('NITRON_PAY_BASE_URL');
        Configuration::deleteByName('NITRON_PAY_TITLE');
        Configuration::deleteByName('NITRON_PAY_CURRENCY_UNIT');
        Configuration::deleteByName('NITRON_PAY_ENABLE_INQUIRY');
        Configuration::deleteByName('NITRON_PAY_OS_SUCCESS');
        Configuration::deleteByName('NITRON_PAY_OS_FAILED');

        return parent::uninstall();
    }

    private function executeSqlFile($filePath)
    {
        if (!file_exists($filePath)) {
            return true;
        }

        $sql = file_get_contents($filePath);
        if (empty($sql)) {
            return true;
        }

        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        $queries = preg_split('/;\s*[\r\n]+/', trim($sql));

        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!Db::getInstance()->execute($query)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * ثبت تب‌ها زیر منوی پرداخت (AdminParentPayment)
     */
    private function installTabs()
    {
        $parentId = (int) Tab::getIdFromClassName('AdminParentPayment');
        if ($parentId <= 0) {
            $parentId = (int) Tab::getIdFromClassName('AdminPayment');
        }

        // 1. تب پیکربندی و تنظیمات
        $this->createTab('AdminNitronPayConfig', 'تنظیمات نیترون پی', $parentId);

        // 2. تب لاگ‌ها و تراکنش‌ها با فیلتر
        $this->createTab('AdminNitronPayLogs', 'لاگ‌ها و تراکنش‌های نیترون پی', $parentId);

        return true;
    }

    private function createTab($className, $name, $parentId)
    {
        $idTab = (int) Tab::getIdFromClassName($className);
        if (!$idTab) {
            $tab = new Tab();
            $tab->class_name = $className;
            $tab->module = $this->name;
            $tab->id_parent = $parentId;
            $languages = Language::getLanguages(false);
            foreach ($languages as $lang) {
                $tab->name[$lang['id_lang']] = $name;
            }
            return $tab->add();
        }

        return true;
    }

    private function uninstallTabs()
    {
        $tabs = ['AdminNitronPayConfig', 'AdminNitronPayLogs'];
        foreach ($tabs as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);
            if ($idTab) {
                $tab = new Tab($idTab);
                $tab->delete();
            }
        }
    }

    /**
     * دکمه پیکربندی ماژول به ادمین کنترلر تنظیمات ریدایرکت می‌شود
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminNitronPayConfig'));
    }

    /**
     * هوک گزینه‌های پرداخت در مرحله تسویه‌حساب پرستاشاپ ۱.۷.۸
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }

        $apiKey = Configuration::get('NITRON_PAY_API_KEY');
        if (empty($apiKey)) {
            return [];
        }

        if (!$this->checkCurrency($params['cart'])) {
            return [];
        }

        $title = Configuration::get('NITRON_PAY_TITLE');
        if (empty($title)) {
            $title = $this->l('پرداخت آنلاین با کارت‌های بانکی (نیترون پی)');
        }

        $logoPath = $this->_path . 'logo.png';

        $paymentOption = new PaymentOption();
        $paymentOption->setCallToActionText($title)
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true))
            ->setModuleName($this->name);

        if (file_exists($this->local_path . 'logo.png')) {
            $paymentOption->setLogo($logoPath);
        }

        $this->context->smarty->assign([
            'module_name' => $this->name,
            'title' => $title,
        ]);
        $paymentOption->setAdditionalInformation($this->fetch('module:nitron_pay/views/templates/hook/payment_info.tpl'));

        return [$paymentOption];
    }

    /**
     * هوک صفحه تایید بازگشت سفارش
     */
    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return '';
        }

        $order = isset($params['order']) ? $params['order'] : null;
        if (!$order || $order->module !== $this->name) {
            return '';
        }

        $transaction = null;
        $idOrder = (int) $order->id;
        $transId = Db::getInstance()->getValue(
            'SELECT `id_nitron_pay_transaction` FROM `' . _DB_PREFIX_ . 'nitron_pay_transaction` ' .
            'WHERE `id_order` = ' . $idOrder . ' ORDER BY `id_nitron_pay_transaction` DESC'
        );
        if ($transId) {
            $transaction = new NitronPayTransaction((int) $transId);
        }

        $orderCurrency = new Currency((int) $order->id_currency);

        $this->context->smarty->assign([
            'order' => $order,
            'order_currency' => $orderCurrency,
            'total_to_pay' => Tools::displayPrice($order->total_paid, $orderCurrency),
            'transaction' => $transaction,
            'status' => ($order->getCurrentState() == Configuration::get('NITRON_PAY_OS_SUCCESS')) ? 'ok' : 'failed',
            'shop_name' => $this->context->shop->name,
        ]);

        return $this->fetch('module:nitron_pay/views/templates/hook/payment_return.tpl');
    }

    /**
     * بررسی پشتیبانی از ارز سبد خرید
     */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int) ($cart->id_currency));
        $currencies_module = $this->getCurrency((int) $cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * تبدیل مبلغ سبد به ریال بر اساس تنظیمات و ایزو کد ارز
     *
     * @param float $amount
     * @param Currency $currency
     * @return int
     */
    public function convertAmountToRial($amount, $currency)
    {
        $unitMode = Configuration::get('NITRON_PAY_CURRENCY_UNIT');
        $isoCode = Tools::strtoupper($currency->iso_code);

        // ۱. اگر کاربر دستی حالت را تعیین کرده باشد
        if ($unitMode === 'toman') {
            return (int) round($amount * 10);
        } elseif ($unitMode === 'rial') {
            return (int) round($amount);
        }

        // ۲. حالت خودکار (auto) بر اساس ایزو کد یا نام واحد پول
        if (in_array($isoCode, ['IRR', 'RL', 'RIAL'], true)) {
            return (int) round($amount);
        }

        if (in_array($isoCode, ['IRT', 'TMN', 'TOMAN'], true)) {
            return (int) round($amount * 10);
        }

        // بررسی بر اساس نام واحد اگر ایزو کد غیراستاندارد باشد
        $currName = Tools::strtolower($currency->name);
        if (strpos($currName, 'تومان') !== false || strpos($currName, 'toman') !== false) {
            return (int) round($amount * 10);
        }

        // در حالت پیش‌فرض برای فروشگاه‌های ایران معمولاً ریال یا تومان بر مبنای ریال است
        return (int) round($amount);
    }
}
