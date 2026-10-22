<?php
/**
 * 2026 Nitron
 *
 * @author    nitron
 * @copyright 2026 Nitron
 * @license   Commercial License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'nitron_pay/classes/NitronPayApi.php';
require_once _PS_MODULE_DIR_ . 'nitron_pay/classes/NitronPayLogger.php';

class AdminNitronPayConfigController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = 'پیکربندی و تنظیمات درگاه پرداخت نیترون (NitronPay)';
    }

    public function initContent()
    {
        parent::initContent();

        if (Tools::isSubmit('submitNitronPaySettings')) {
            $this->processSaveSettings();
        }

        if (Tools::isSubmit('btnTestConnection')) {
            $this->processTestConnection();
        }

        $this->renderCustomView();
    }

    private function processSaveSettings()
    {
        $apiKey = trim(Tools::getValue('NITRON_PAY_API_KEY'));
        $apiSecret = trim(Tools::getValue('NITRON_PAY_API_SECRET'));
        $baseUrl = trim(Tools::getValue('NITRON_PAY_BASE_URL'));
        $title = trim(Tools::getValue('NITRON_PAY_TITLE'));
        $currencyUnit = trim(Tools::getValue('NITRON_PAY_CURRENCY_UNIT'));
        $enableInquiry = (int) Tools::getValue('NITRON_PAY_ENABLE_INQUIRY', 0);
        $osSuccess = (int) Tools::getValue('NITRON_PAY_OS_SUCCESS');
        $osFailed = (int) Tools::getValue('NITRON_PAY_OS_FAILED');
        $osPending = (int) Tools::getValue('NITRON_PAY_OS_PENDING');

        if (empty($baseUrl)) {
            $baseUrl = 'https://app.nitron.pro/pay';
        }

        Configuration::updateValue('NITRON_PAY_API_KEY', $apiKey);
        Configuration::updateValue('NITRON_PAY_API_SECRET', $apiSecret);
        Configuration::updateValue('NITRON_PAY_BASE_URL', $baseUrl);
        Configuration::updateValue('NITRON_PAY_TITLE', $title);
        Configuration::updateValue('NITRON_PAY_CURRENCY_UNIT', $currencyUnit);
        Configuration::updateValue('NITRON_PAY_ENABLE_INQUIRY', $enableInquiry);
        Configuration::updateValue('NITRON_PAY_OS_SUCCESS', $osSuccess);
        Configuration::updateValue('NITRON_PAY_OS_FAILED', $osFailed);
        if ($osPending > 0) {
            Configuration::updateValue('NITRON_PAY_OS_PENDING', $osPending);
        }

        $this->confirmations[] = 'تنظیمات درگاه نیترون با موفقیت ذخیره شد.';
    }

    private function processTestConnection()
    {
        $api = new NitronPayApi();
        $result = $api->getGateways();

        if ($result['success']) {
            $this->confirmations[] = 'ارتباط با سرور درگاه نیترون برقرار است. (کد وضعیت: ' . $result['status_code'] . ')';
        } else {
            $this->errors[] = 'خطا در ارتباط با وب‌سرویس نیترون: ' . $result['message'] . ' (کد: ' . $result['status_code'] . ')';
        }
    }

    private function renderCustomView()
    {
        $orderStates = OrderState::getOrderStates($this->context->language->id);

        $currencies = Currency::getCurrencies(true, false);

        $this->context->smarty->assign([
            'current_url' => $this->context->link->getAdminLink('AdminNitronPayConfig'),
            'logs_url' => $this->context->link->getAdminLink('AdminNitronPayLogs') . '&np_tab=transactions',
            'reports_url' => $this->context->link->getAdminLink('AdminNitronPayReports'),
            'api_key' => Configuration::get('NITRON_PAY_API_KEY'),
            'api_secret' => Configuration::get('NITRON_PAY_API_SECRET'),
            'base_url' => Configuration::get('NITRON_PAY_BASE_URL') ?: 'https://app.nitron.pro/pay',
            'gateway_title' => Configuration::get('NITRON_PAY_TITLE') ?: 'پرداخت آنلاین نیترون (کارت‌های عضو شتاب)',
            'currency_unit' => Configuration::get('NITRON_PAY_CURRENCY_UNIT') ?: 'auto',
            'enable_inquiry' => Configuration::get('NITRON_PAY_ENABLE_INQUIRY') !== false ? (int) Configuration::get('NITRON_PAY_ENABLE_INQUIRY') : 1,
            'os_success' => (int) Configuration::get('NITRON_PAY_OS_SUCCESS') ?: (int) Configuration::get('PS_OS_PAYMENT'),
            'os_failed' => (int) Configuration::get('NITRON_PAY_OS_FAILED') ?: (int) Configuration::get('PS_OS_ERROR'),
            'os_pending' => (int) Configuration::get('NITRON_PAY_OS_PENDING'),
            'order_states' => $orderStates,
            'currencies' => $currencies,
        ]);

        $tplOutput = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'nitron_pay/views/templates/admin/configure.tpl');
        $this->content .= $tplOutput;
        $this->context->smarty->assign(['content' => $this->content]);
    }
}
