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
require_once _PS_MODULE_DIR_ . 'nitron_pay/classes/NitronPayTransaction.php';

class AdminNitronPayLogsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = 'لاگ‌ها و تراکنش‌های درگاه پرداخت نیترون (NitronPay)';
    }

    public function initContent()
    {
        parent::initContent();

        if (Tools::isSubmit('actionClearLogs')) {
            $this->processClearLogs();
        }

        if (Tools::isSubmit('actionInquiry')) {
            $this->processManualInquiry();
        }

        if (Tools::getValue('view_transaction_uuid')) {
            $this->renderTransactionDetails(trim(Tools::getValue('view_transaction_uuid')));
            return;
        }

        $this->renderCustomView();
    }

    private function renderTransactionDetails($uuid)
    {
        $api = new NitronPayApi();
        $res = $api->getTransactionDetails($uuid);

        $details = null;
        if ($res['success']) {
            $details = $res['data'];
        } else {
            // در صورت عدم دسترسی به رپورت، سعی می‌کنیم از روی دیتابیس لوکال و استعلام اطلاعات پایه را بسازیم
            $tx = NitronPayTransaction::getByUuid($uuid);
            if ($tx) {
                $details = [
                    'uuid' => $tx->uuid,
                    'site_order_id' => $tx->site_order_id,
                    'amount' => $tx->amount_rial,
                    'status' => $tx->status,
                    'ref_id' => $tx->ref_id,
                    'gateway' => $tx->gateway_name,
                    'created_at' => $tx->date_add,
                ];
            }
        }

        // بررسی لاگ‌های مربوط به این تراکنش در دیتابیس لوکال
        $txLogs = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'nitron_pay_log` WHERE `uuid` = \'' . pSQL($uuid) . '\' OR `site_order_id` = (SELECT `site_order_id` FROM `' . _DB_PREFIX_ . 'nitron_pay_transaction` WHERE `uuid` = \'' . pSQL($uuid) . '\') ORDER BY `id_nitron_pay_log` ASC'
        );

        $this->context->smarty->assign([
            'back_url' => $this->context->link->getAdminLink('AdminNitronPayLogs'),
            'current_url' => $this->context->link->getAdminLink('AdminNitronPayLogs'),
            'uuid' => $uuid,
            'details' => $details,
            'tx_logs' => $txLogs ?: [],
            'error_msg' => !$res['success'] ? $res['message'] : null,
        ]);

        $tplOutput = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'nitron_pay/views/templates/admin/transaction_view.tpl');
        $this->content .= $tplOutput;
        $this->context->smarty->assign(['content' => $this->content]);
    }

    private function processClearLogs()
    {
        $keepLast = (int) Tools::getValue('keep_last', 0);

        if ($keepLast > 0) {
            // حذف لاگ‌های قدیمی‌تر و نگه‌داشتن N لاگ اخیر
            $subQuery = 'SELECT `id_nitron_pay_log` FROM `' . _DB_PREFIX_ . 'nitron_pay_log` ORDER BY `id_nitron_pay_log` DESC LIMIT ' . (int) $keepLast;
            $keepIds = Db::getInstance()->executeS($subQuery);
            if ($keepIds && count($keepIds) > 0) {
                $ids = array_map(function ($item) {
                    return (int) $item['id_nitron_pay_log'];
                }, $keepIds);
                $minId = min($ids);
                Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'nitron_pay_log` WHERE `id_nitron_pay_log` < ' . (int) $minId);
                $this->confirmations[] = "لاگ‌های قدیمی‌تر با موفقیت پاکسازی شدند و {$keepLast} لاگ اخیر حفظ گردید.";
            } else {
                $this->confirmations[] = 'تعداد لاگ‌ها کمتر از سقف نگهداری است.';
            }
        } else {
            Db::getInstance()->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'nitron_pay_log`');
            $this->confirmations[] = 'کلیه لاگ‌های درگاه نیترون با موفقیت پاکسازی شدند.';
        }
    }

    private function processManualInquiry()
    {
        $siteOrderId = trim(Tools::getValue('site_order_id'));
        $uuid = trim(Tools::getValue('uuid'));

        if (empty($siteOrderId)) {
            $this->errors[] = 'شناسه سفارش مشخص نیست.';
            return;
        }

        $api = new NitronPayApi();
        $res = $api->inquiryPayment($siteOrderId, $uuid);

        if ($res['success']) {
            $data = $res['data'];
            $status = isset($data['status']) ? $data['status'] : 'نامشخص';
            $refId = !empty($data['ref_id']) ? $data['ref_id'] : (!empty($data['inquiry']['response']['refNumber']) ? $data['inquiry']['response']['refNumber'] : '-');
            $this->confirmations[] = "استعلام با موفقیت انجام شد: وضعیت = {$status} | کد پیگیری (ref_id) = {$refId}";

            // بروزرسانی تراکنش در صورت وجود
            $tx = NitronPayTransaction::getBySiteOrderId($siteOrderId);
            if ($tx && Validate::isLoadedObject($tx)) {
                $tx->status = $status;
                if (!empty($refId) && $refId !== '-') {
                    $tx->ref_id = $refId;
                }
                $tx->save();
            }
        } else {
            $this->errors[] = 'خطا در استعلام وضعیت تراکنش: ' . $res['message'];
        }
    }

    private function renderCustomView()
    {
        // فیلترها
        $filterAction = trim(Tools::getValue('filter_action', ''));
        $filterOrderId = trim(Tools::getValue('filter_site_order_id', ''));
        $filterDateFrom = trim(Tools::getValue('filter_date_from', ''));
        $filterDateTo = trim(Tools::getValue('filter_date_to', ''));
        $filterStatus = trim(Tools::getValue('filter_status', ''));

        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $where = ' WHERE 1=1 ';
        if (!empty($filterAction)) {
            $where .= ' AND l.`action` LIKE \'%' . pSQL($filterAction) . '%\'';
        }
        if (!empty($filterOrderId)) {
            $where .= ' AND (l.`site_order_id` LIKE \'%' . pSQL($filterOrderId) . '%\' OR l.`uuid` LIKE \'%' . pSQL($filterOrderId) . '%\')';
        }
        if (!empty($filterDateFrom) && Validate::isDate($filterDateFrom)) {
            $where .= ' AND l.`date_add` >= \'' . pSQL($filterDateFrom) . ' 00:00:00\'';
        }
        if (!empty($filterDateTo) && Validate::isDate($filterDateTo)) {
            $where .= ' AND l.`date_add` <= \'' . pSQL($filterDateTo) . ' 23:59:59\'';
        }
        if ($filterStatus !== '') {
            if ($filterStatus === 'success') {
                $where .= ' AND (l.`status_code` >= 200 AND l.`status_code` < 300)';
            } elseif ($filterStatus === 'error') {
                $where .= ' AND (l.`status_code` < 200 OR l.`status_code` >= 300 OR l.`status_code` IS NULL)';
            }
        }

        // تعداد کل کل لاگ‌ها در دیتابیس (بدون فیلتر)
        $totalAllLogs = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'nitron_pay_log`');

        // تعداد لاگ‌های مطابق فیلتر
        $totalFilteredLogs = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'nitron_pay_log` l ' . $where);
        $totalPages = max(1, (int) ceil($totalFilteredLogs / $limit));

        $logsSql = 'SELECT l.* FROM `' . _DB_PREFIX_ . 'nitron_pay_log` l ' . $where . ' ORDER BY l.`id_nitron_pay_log` DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        $logs = Db::getInstance()->executeS($logsSql);

        // آخرین تراکنش‌های ماژول
        $transSql = 'SELECT t.*, o.`reference` as order_reference FROM `' . _DB_PREFIX_ . 'nitron_pay_transaction` t ' .
            'LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.`id_order` = t.`id_order`) ' .
            'ORDER BY t.`id_nitron_pay_transaction` DESC LIMIT 50';
        $transactions = Db::getInstance()->executeS($transSql);

        $this->context->smarty->assign([
            'current_url' => $this->context->link->getAdminLink('AdminNitronPayLogs'),
            'config_url' => $this->context->link->getAdminLink('AdminNitronPayConfig'),
            'logs' => $logs ?: [],
            'transactions' => $transactions ?: [],
            'total_all_logs' => $totalAllLogs,
            'total_filtered_logs' => $totalFilteredLogs,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'page_limit' => $limit,
            'filters' => [
                'action' => $filterAction,
                'site_order_id' => $filterOrderId,
                'date_from' => $filterDateFrom,
                'date_to' => $filterDateTo,
                'status' => $filterStatus,
            ],
        ]);

        $tplOutput = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'nitron_pay/views/templates/admin/logs.tpl');
        $this->content .= $tplOutput;
        $this->context->smarty->assign(['content' => $this->content]);
    }
}
