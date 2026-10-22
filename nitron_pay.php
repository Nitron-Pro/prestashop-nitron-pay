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
        $this->version = '1.3.0';
        $this->author = 'nitron';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->controllers = ['payment', 'validation', 'retry'];

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
        $this->ensurePendingOrderState();

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayPaymentReturn')
            && $this->registerHook('displayOrderDetail')
            && $this->registerHook('displayAdminOrder');
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
        Configuration::deleteByName('NITRON_PAY_OS_PENDING');

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

        $this->createTab('AdminNitronPayConfig', 'تنظیمات نیترون پی', $parentId);
        $this->createTab('AdminNitronPayLogs', 'تراکنش‌ها و لاگ‌های نیترون پی', $parentId);
        $this->createTab('AdminNitronPayReports', 'گزارش پرداخت‌های موفق نیترون پی', $parentId);

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
        $tabs = ['AdminNitronPayConfig', 'AdminNitronPayLogs', 'AdminNitronPayReports'];
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

    /**
     * دکمه پرداخت مجدد در جزئیات سفارش حساب مشتری
     */
    public function hookDisplayOrderDetail($params)
    {
        if (!$this->active || empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];
        if ($order->module !== $this->name || !$this->isOrderPayable($order)) {
            return '';
        }

        $customer = new Customer((int) $order->id_customer);
        $this->context->smarty->assign([
            'retry_url' => $this->getRetryPaymentUrl($order, $customer),
            'order' => $order,
        ]);

        return $this->fetch('module:nitron_pay/views/templates/hook/order_detail.tpl');
    }

    /**
     * جعبه پیگیری پرداخت در صفحه سفارش ادمین
     */
    public function hookDisplayAdminOrder($params)
    {
        $idOrder = 0;
        if (!empty($params['id_order'])) {
            $idOrder = (int) $params['id_order'];
        } elseif (!empty($params['order']) && Validate::isLoadedObject($params['order'])) {
            $idOrder = (int) $params['order']->id;
        }

        if ($idOrder <= 0) {
            return '';
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $transaction = NitronPayTransaction::getLatestByOrderId($idOrder);
        $customer = new Customer((int) $order->id_customer);
        $statusMeta = $transaction ? NitronPayTransaction::getStatusMeta($transaction->status) : null;

        $this->context->smarty->assign([
            'np_order' => $order,
            'np_transaction' => $transaction,
            'np_status_meta' => $statusMeta,
            'np_can_retry' => $this->isOrderPayable($order),
            'np_retry_url' => $this->getRetryPaymentUrl($order, $customer),
            'np_timeline_url' => ($transaction && $transaction->uuid)
                ? $this->context->link->getAdminLink('AdminNitronPayLogs') . '&view_transaction_uuid=' . urlencode($transaction->uuid)
                : '',
            'np_customer_url' => $this->context->link->getAdminLink('AdminCustomers', true, [], [
                'id_customer' => (int) $order->id_customer,
                'viewcustomer' => 1,
            ]),
        ]);

        return $this->fetch('module:nitron_pay/views/templates/hook/admin_order.tpl');
    }

    /**
     * آیا سفارش هنوز قابل پرداخت مجدد است؟
     *
     * @param Order $order
     * @return bool
     */
    public function isOrderPayable(Order $order)
    {
        if (!Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return false;
        }

        $successState = (int) Configuration::get('NITRON_PAY_OS_SUCCESS');
        if ($successState > 0 && (int) $order->getCurrentState() === $successState) {
            return false;
        }

        if (method_exists($order, 'hasBeenPaid') && $order->hasBeenPaid()) {
            return false;
        }

        $tx = NitronPayTransaction::getLatestByOrderId((int) $order->id);
        if ($tx && NitronPayTransaction::isSuccessStatus($tx->status)) {
            return false;
        }

        return true;
    }

    /**
     * لینک پرداخت سریع همان سفارش برای ارسال به مشتری
     *
     * @param Order $order
     * @param Customer|null $customer
     * @return string
     */
    public function getRetryPaymentUrl(Order $order, $customer = null)
    {
        if (!$customer || !Validate::isLoadedObject($customer)) {
            $customer = new Customer((int) $order->id_customer);
        }

        return $this->context->link->getModuleLink($this->name, 'retry', [
            'id_order' => (int) $order->id,
            'key' => $customer->secure_key,
        ], true);
    }

    /**
     * آدرس پیگیری سفارش برای مشتری (با کلید امنیتی)
     *
     * @param Order $order
     * @param Customer $customer
     * @return string
     */
    public function getOrderFollowUpUrl(Order $order, Customer $customer)
    {
        return $this->context->link->getPageLink('order-detail', true, null, [
            'id_order' => (int) $order->id,
            'key' => $customer->secure_key,
        ]);
    }

    /**
     * تغییر وضعیت سفارش بدون ساخت سفارش جدید
     *
     * @param int $idOrder
     * @param int $idOrderState
     * @return bool
     */
    public function changeExistingOrderState($idOrder, $idOrderState)
    {
        $idOrder = (int) $idOrder;
        $idOrderState = (int) $idOrderState;
        if ($idOrder <= 0 || $idOrderState <= 0) {
            return false;
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        if ((int) $order->current_state === $idOrderState) {
            return true;
        }

        $order->setCurrentState($idOrderState);

        return true;
    }

    /**
     * ثبت سفارش در انتظار پرداخت قبل از رفتن به درگاه
     *
     * @param Cart $cart
     * @param Customer $customer
     * @param Currency $currency
     * @param float $totalOrder
     * @return Order|null
     */
    public function createPendingOrder(Cart $cart, Customer $customer, Currency $currency, $totalOrder)
    {
        $existingId = (int) Order::getIdByCartId((int) $cart->id);
        if ($existingId > 0) {
            $order = new Order($existingId);
            if (Validate::isLoadedObject($order)) {
                return $order;
            }
        }

        $pendingStatus = (int) Configuration::get('NITRON_PAY_OS_PENDING');
        if ($pendingStatus <= 0) {
            $this->ensurePendingOrderState();
            $pendingStatus = (int) Configuration::get('NITRON_PAY_OS_PENDING');
        }
        if ($pendingStatus <= 0) {
            $pendingStatus = (int) Configuration::get('PS_OS_PREPARATION');
        }

        $ok = $this->validateOrder(
            (int) $cart->id,
            $pendingStatus,
            (float) $totalOrder,
            $this->displayName,
            'سفارش ثبت شد و در انتظار پرداخت آنلاین نیترون است.',
            [],
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        if (!$ok || (int) $this->currentOrder <= 0) {
            return null;
        }

        $order = new Order((int) $this->currentOrder);

        return Validate::isLoadedObject($order) ? $order : null;
    }

    /**
     * ساخت وضعیت سفارشی «در انتظار پرداخت نیترون»
     */
    public function ensurePendingOrderState()
    {
        $existing = (int) Configuration::get('NITRON_PAY_OS_PENDING');
        if ($existing > 0) {
            $state = new OrderState($existing);
            if (Validate::isLoadedObject($state)) {
                return $existing;
            }
        }

        $orderState = new OrderState();
        $orderState->invoice = false;
        $orderState->send_email = false;
        $orderState->module_name = $this->name;
        $orderState->color = '#3498DB';
        $orderState->unremovable = false;
        $orderState->hidden = false;
        $orderState->logable = true;
        $orderState->delivery = false;
        $orderState->shipped = false;
        $orderState->paid = false;
        $orderState->pdf_invoice = false;
        $orderState->pdf_delivery = false;
        $orderState->deleted = false;

        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $iso = isset($lang['iso_code']) ? Tools::strtolower($lang['iso_code']) : 'fa';
            $orderState->name[$lang['id_lang']] = ($iso === 'fa' || $iso === 'per')
                ? 'در انتظار پرداخت نیترون'
                : 'Awaiting Nitron payment';
        }

        if ($orderState->add()) {
            Configuration::updateValue('NITRON_PAY_OS_PENDING', (int) $orderState->id);
            $source = _PS_MODULE_DIR_ . $this->name . '/logo.png';
            $dest = _PS_ORDER_STATE_IMG_DIR_ . (int) $orderState->id . '.gif';
            if (file_exists($source) && is_writable(_PS_ORDER_STATE_IMG_DIR_)) {
                @copy($source, $dest);
            }

            return (int) $orderState->id;
        }

        return 0;
    }

    /**
     * ارتقا به نسخه ۱.۳.۰ برای فروشگاه‌های نصب‌شده
     *
     * @return bool
     */
    public function upgradeTo130()
    {
        $this->ensurePendingOrderState();
        $this->installTabs();
        $this->registerHook('displayOrderDetail');
        $this->registerHook('displayAdminOrder');

        $db = Db::getInstance();
        $table = _DB_PREFIX_ . 'nitron_pay_transaction';
        $indexes = [
            'idx_customer' => 'ALTER TABLE `' . $table . '` ADD INDEX `idx_customer` (`id_customer`)',
            'idx_date_add' => 'ALTER TABLE `' . $table . '` ADD INDEX `idx_date_add` (`date_add`)',
        ];
        foreach ($indexes as $name => $sql) {
            $exists = $db->executeS('SHOW INDEX FROM `' . $table . '` WHERE Key_name = \'' . pSQL($name) . '\'');
            if (!$exists) {
                $db->execute($sql);
            }
        }

        return true;
    }
}
