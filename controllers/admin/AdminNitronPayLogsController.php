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
        $this->meta_title = 'تراکنش‌ها و لاگ‌های درگاه پرداخت نیترون (NitronPay)';
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

        $tab = Tools::getValue('np_tab', 'transactions');
        if ($tab === 'logs') {
            $this->renderLogsTab();
            return;
        }

        $this->renderTransactionsTab();
    }

    private function assignNav($activeTab)
    {
        $this->context->smarty->assign([
            'current_url' => $this->context->link->getAdminLink('AdminNitronPayLogs'),
            'config_url' => $this->context->link->getAdminLink('AdminNitronPayConfig'),
            'reports_url' => $this->context->link->getAdminLink('AdminNitronPayReports'),
            'transactions_url' => $this->context->link->getAdminLink('AdminNitronPayLogs') . '&np_tab=transactions',
            'logs_tab_url' => $this->context->link->getAdminLink('AdminNitronPayLogs') . '&np_tab=logs',
            'active_np_tab' => $activeTab,
            'admin_token' => $this->token,
        ]);
    }

    private function renderTransactionDetails($uuid)
    {
        $api = new NitronPayApi();
        $res = $api->getTransactionDetails($uuid);

        $tx = NitronPayTransaction::getByUuid($uuid);
        $details = null;
        if ($res['success']) {
            $details = $res['data'];
        }

        if (!$details && $tx) {
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

        if ($tx && empty($details['status'])) {
            $details['status'] = $tx->status;
        }
        if ($tx && empty($details['stages'])) {
            $details['stages'] = NitronPayTransaction::buildLocalStages($tx);
        }

        $shopContext = $this->buildShopContext($tx);

        $txLogs = [];
        if ($uuid !== '') {
            $txLogs = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . 'nitron_pay_log` WHERE `uuid` = \'' . pSQL($uuid) . '\' OR `site_order_id` = (SELECT `site_order_id` FROM `' . _DB_PREFIX_ . 'nitron_pay_transaction` WHERE `uuid` = \'' . pSQL($uuid) . '\' LIMIT 1) ORDER BY `id_nitron_pay_log` ASC'
            );
        }

        $statusMeta = NitronPayTransaction::getStatusMeta(isset($details['status']) ? $details['status'] : ($tx ? $tx->status : ''));

        $this->assignNav('transactions');
        $this->context->smarty->assign([
            'back_url' => $this->context->link->getAdminLink('AdminNitronPayLogs') . '&np_tab=transactions',
            'uuid' => $uuid,
            'details' => $details,
            'tx_logs' => $txLogs ?: [],
            'error_msg' => !$res['success'] ? $res['message'] : null,
            'shop_context' => $shopContext,
            'status_meta' => $statusMeta,
            'can_inquiry' => $tx && NitronPayTransaction::isPendingStatus($tx->status),
        ]);

        $tplOutput = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'nitron_pay/views/templates/admin/transaction_view.tpl');
        $this->content .= $tplOutput;
        $this->context->smarty->assign(['content' => $this->content]);
    }

    /**
     * @param NitronPayTransaction|null $tx
     * @return array
     */
    private function buildShopContext($tx)
    {
        $ctx = [
            'id_customer' => 0,
            'id_cart' => 0,
            'id_order' => 0,
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => '',
            'order_reference' => '',
            'retry_url' => '',
            'customer_admin_url' => '',
            'order_admin_url' => '',
            'cart_admin_url' => '',
        ];

        if (!$tx) {
            return $ctx;
        }

        $ctx['id_customer'] = (int) $tx->id_customer;
        $ctx['id_cart'] = (int) $tx->id_cart;
        $ctx['id_order'] = (int) $tx->id_order;

        if ($tx->id_customer) {
            $customer = new Customer((int) $tx->id_customer);
            if (Validate::isLoadedObject($customer)) {
                $ctx['customer_name'] = trim($customer->firstname . ' ' . $customer->lastname);
                $ctx['customer_email'] = $customer->email;
                $ctx['customer_admin_url'] = $this->context->link->getAdminLink('AdminCustomers', true, [], [
                    'id_customer' => (int) $customer->id,
                    'viewcustomer' => 1,
                ]);
            }
        }

        if ($tx->id_order) {
            $order = new Order((int) $tx->id_order);
            if (Validate::isLoadedObject($order)) {
                $ctx['order_reference'] = $order->reference;
                $ctx['order_admin_url'] = $this->context->link->getAdminLink('AdminOrders', true, [], [
                    'id_order' => (int) $order->id,
                    'vieworder' => 1,
                ]);
                $address = new Address((int) $order->id_address_invoice);
                if (Validate::isLoadedObject($address)) {
                    $ctx['customer_phone'] = !empty($address->phone_mobile) ? $address->phone_mobile : $address->phone;
                }
                if ($this->module instanceof Nitron_Pay && $this->module->isOrderPayable($order)) {
                    $cust = new Customer((int) $order->id_customer);
                    $ctx['retry_url'] = $this->module->getRetryPaymentUrl($order, $cust);
                }
            }
        }

        if ($tx->id_cart) {
            $ctx['cart_admin_url'] = $this->context->link->getAdminLink('AdminCarts', true, [], [
                'id_cart' => (int) $tx->id_cart,
                'viewcart' => 1,
            ]);
            if (empty($ctx['customer_phone'])) {
                $cart = new Cart((int) $tx->id_cart);
                if (Validate::isLoadedObject($cart)) {
                    $address = new Address((int) ($cart->id_address_invoice ?: $cart->id_address_delivery));
                    if (Validate::isLoadedObject($address)) {
                        $ctx['customer_phone'] = !empty($address->phone_mobile) ? $address->phone_mobile : $address->phone;
                    }
                }
            }
        }

        return $ctx;
    }

    private function processClearLogs()
    {
        $keepLast = (int) Tools::getValue('keep_last', 0);

        if ($keepLast > 0) {
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

    private function renderTransactionsTab()
    {
        $filterQ = trim(Tools::getValue('filter_q', ''));
        $filterGroup = trim(Tools::getValue('filter_tx_group', ''));
        $filterDateFrom = trim(Tools::getValue('filter_date_from', ''));
        $filterDateTo = trim(Tools::getValue('filter_date_to', ''));
        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = 40;
        $offset = ($page - 1) * $limit;

        $where = ' WHERE 1=1 ';
        if ($filterQ !== '') {
            $q = pSQL($filterQ);
            $where .= ' AND (' .
                't.`site_order_id` LIKE \'%' . $q . '%\' OR t.`uuid` LIKE \'%' . $q . '%\' OR t.`ref_id` LIKE \'%' . $q . '%\' ' .
                'OR t.`id_cart` LIKE \'%' . $q . '%\' OR t.`id_order` LIKE \'%' . $q . '%\' ' .
                'OR c.`email` LIKE \'%' . $q . '%\' OR c.`firstname` LIKE \'%' . $q . '%\' OR c.`lastname` LIKE \'%' . $q . '%\' ' .
                'OR a.`phone` LIKE \'%' . $q . '%\' OR a.`phone_mobile` LIKE \'%' . $q . '%\' OR o.`reference` LIKE \'%' . $q . '%\'' .
                ')';
        }
        if ($filterGroup === 'success') {
            $where .= ' AND t.`status` IN (\'verified\', \'success\')';
        } elseif ($filterGroup === 'failed') {
            $where .= ' AND t.`status` IN (\'failed\', \'cancelled\', \'canceled\', \'fail\', \'verify_failed\', \'create_failed\')';
        } elseif ($filterGroup === 'pending') {
            $where .= ' AND t.`status` NOT IN (\'verified\', \'success\', \'failed\', \'cancelled\', \'canceled\', \'fail\', \'verify_failed\', \'create_failed\')';
        }
        if (!empty($filterDateFrom) && Validate::isDate($filterDateFrom)) {
            $where .= ' AND t.`date_add` >= \'' . pSQL($filterDateFrom) . ' 00:00:00\'';
        }
        if (!empty($filterDateTo) && Validate::isDate($filterDateTo)) {
            $where .= ' AND t.`date_add` <= \'' . pSQL($filterDateTo) . ' 23:59:59\'';
        }

        $join = ' FROM `' . _DB_PREFIX_ . 'nitron_pay_transaction` t ' .
            'LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.`id_order` = t.`id_order`) ' .
            'LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.`id_customer` = t.`id_customer`) ' .
            'LEFT JOIN `' . _DB_PREFIX_ . 'cart` ca ON (ca.`id_cart` = t.`id_cart`) ' .
            'LEFT JOIN `' . _DB_PREFIX_ . 'address` a ON (a.`id_address` = COALESCE(NULLIF(o.`id_address_invoice`, 0), ca.`id_address_invoice`)) ';

        $total = (int) Db::getInstance()->getValue('SELECT COUNT(DISTINCT t.`id_nitron_pay_transaction`) ' . $join . $where);
        $totalPages = max(1, (int) ceil($total / $limit));

        $sql = 'SELECT t.*, o.`reference` AS order_reference, ' .
            'c.`firstname` AS customer_firstname, c.`lastname` AS customer_lastname, c.`email` AS customer_email, ' .
            'COALESCE(NULLIF(a.`phone_mobile`, \'\'), a.`phone`) AS customer_phone ' .
            $join . $where .
            ' GROUP BY t.`id_nitron_pay_transaction` ' .
            'ORDER BY t.`id_nitron_pay_transaction` DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        $rows = Db::getInstance()->executeS($sql) ?: [];

        foreach ($rows as &$row) {
            $row['status_meta'] = NitronPayTransaction::getStatusMeta($row['status']);
            $row['customer_name'] = trim(($row['customer_firstname'] ?? '') . ' ' . ($row['customer_lastname'] ?? ''));
            $row['can_inquiry'] = NitronPayTransaction::isPendingStatus($row['status']);
            $row['retry_url'] = '';
            if (!empty($row['id_order']) && $this->module instanceof Nitron_Pay) {
                $order = new Order((int) $row['id_order']);
                if (Validate::isLoadedObject($order) && $this->module->isOrderPayable($order)) {
                    $cust = new Customer((int) $order->id_customer);
                    $row['retry_url'] = $this->module->getRetryPaymentUrl($order, $cust);
                }
            }
        }
        unset($row);

        $todayKpi = Db::getInstance()->getRow(
            'SELECT ' .
            'SUM(CASE WHEN `status` IN (\'verified\', \'success\') THEN 1 ELSE 0 END) AS success_count, ' .
            'SUM(CASE WHEN `status` IN (\'failed\', \'cancelled\', \'canceled\', \'fail\', \'verify_failed\', \'create_failed\') THEN 1 ELSE 0 END) AS failed_count, ' .
            'SUM(CASE WHEN `status` NOT IN (\'verified\', \'success\', \'failed\', \'cancelled\', \'canceled\', \'fail\', \'verify_failed\', \'create_failed\') THEN 1 ELSE 0 END) AS pending_count, ' .
            'SUM(CASE WHEN `status` IN (\'verified\', \'success\') THEN `amount_rial` ELSE 0 END) AS success_sum ' .
            'FROM `' . _DB_PREFIX_ . 'nitron_pay_transaction` WHERE `date_add` >= CURDATE()'
        );

        $this->assignNav('transactions');
        $this->context->smarty->assign([
            'transactions' => $rows,
            'tx_total' => $total,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'page_limit' => $limit,
            'today_kpi' => $todayKpi ?: ['success_count' => 0, 'failed_count' => 0, 'pending_count' => 0, 'success_sum' => 0],
            'filters' => [
                'q' => $filterQ,
                'tx_group' => $filterGroup,
                'date_from' => $filterDateFrom,
                'date_to' => $filterDateTo,
            ],
        ]);

        $tplOutput = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'nitron_pay/views/templates/admin/transactions.tpl');
        $this->content .= $tplOutput;
        $this->context->smarty->assign(['content' => $this->content]);
    }

    private function renderLogsTab()
    {
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

        $totalAllLogs = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'nitron_pay_log`');
        $totalFilteredLogs = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'nitron_pay_log` l ' . $where);
        $totalPages = max(1, (int) ceil($totalFilteredLogs / $limit));

        $logsSql = 'SELECT l.* FROM `' . _DB_PREFIX_ . 'nitron_pay_log` l ' . $where . ' ORDER BY l.`id_nitron_pay_log` DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        $logs = Db::getInstance()->executeS($logsSql);

        $this->assignNav('logs');
        $this->context->smarty->assign([
            'logs' => $logs ?: [],
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
