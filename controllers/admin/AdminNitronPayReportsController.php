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

require_once _PS_MODULE_DIR_ . 'nitron_pay/classes/NitronPayTransaction.php';

class AdminNitronPayReportsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = 'گزارش پرداخت‌های موفق نیترون پی';
    }

    public function initContent()
    {
        parent::initContent();

        $period = Tools::getValue('report_period', 'daily');
        $customFrom = trim(Tools::getValue('filter_date_from', ''));
        $customTo = trim(Tools::getValue('filter_date_to', ''));
        $range = $this->resolveDateRange($period, $customFrom, $customTo);

        if (Tools::isSubmit('exportExcel')) {
            $this->exportExcel($range, $period);
            return;
        }

        $rows = $this->getSuccessRows($range);
        $summary = [
            'count' => count($rows),
            'sum_rial' => 0,
        ];
        foreach ($rows as $row) {
            $summary['sum_rial'] += (int) $row['amount_rial'];
        }

        $this->context->smarty->assign([
            'current_url' => $this->context->link->getAdminLink('AdminNitronPayReports'),
            'config_url' => $this->context->link->getAdminLink('AdminNitronPayConfig'),
            'transactions_url' => $this->context->link->getAdminLink('AdminNitronPayLogs') . '&np_tab=transactions',
            'logs_tab_url' => $this->context->link->getAdminLink('AdminNitronPayLogs') . '&np_tab=logs',
            'reports_url' => $this->context->link->getAdminLink('AdminNitronPayReports'),
            'active_np_tab' => 'reports',
            'admin_token' => $this->token,
            'report_period' => $period,
            'range' => $range,
            'rows' => $rows,
            'summary' => $summary,
            'filters' => [
                'date_from' => $range['from'],
                'date_to' => $range['to'],
            ],
        ]);

        $tplOutput = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'nitron_pay/views/templates/admin/reports.tpl');
        $this->content .= $tplOutput;
        $this->context->smarty->assign(['content' => $this->content]);
    }

    /**
     * @param string $period
     * @param string $customFrom
     * @param string $customTo
     * @return array
     */
    private function resolveDateRange($period, $customFrom, $customTo)
    {
        $today = date('Y-m-d');
        if ($period === 'weekly') {
            return [
                'from' => date('Y-m-d', strtotime('-6 days')),
                'to' => $today,
                'label' => 'هفت روز اخیر',
                'slug' => 'weekly',
            ];
        }
        if ($period === 'monthly') {
            return [
                'from' => date('Y-m-01'),
                'to' => $today,
                'label' => 'ماه جاری',
                'slug' => 'monthly',
            ];
        }
        if ($period === 'custom' && Validate::isDate($customFrom) && Validate::isDate($customTo)) {
            return [
                'from' => $customFrom,
                'to' => $customTo,
                'label' => 'بازه سفارشی',
                'slug' => 'custom-' . $customFrom . '-to-' . $customTo,
            ];
        }

        return [
            'from' => $today,
            'to' => $today,
            'label' => 'امروز',
            'slug' => 'daily',
        ];
    }

    /**
     * @param array $range
     * @return array
     */
    private function getSuccessRows(array $range)
    {
        $sql = 'SELECT t.*, o.`reference` AS order_reference, ' .
            'c.`firstname` AS customer_firstname, c.`lastname` AS customer_lastname, c.`email` AS customer_email, ' .
            'COALESCE(NULLIF(a.`phone_mobile`, \'\'), a.`phone`) AS customer_phone ' .
            'FROM `' . _DB_PREFIX_ . 'nitron_pay_transaction` t ' .
            'LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.`id_order` = t.`id_order`) ' .
            'LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.`id_customer` = t.`id_customer`) ' .
            'LEFT JOIN `' . _DB_PREFIX_ . 'address` a ON (a.`id_address` = o.`id_address_invoice`) ' .
            'WHERE t.`status` IN (\'verified\', \'success\') ' .
            'AND t.`date_add` >= \'' . pSQL($range['from']) . ' 00:00:00\' ' .
            'AND t.`date_add` <= \'' . pSQL($range['to']) . ' 23:59:59\' ' .
            'ORDER BY t.`date_add` DESC';

        $rows = Db::getInstance()->executeS($sql) ?: [];
        foreach ($rows as &$row) {
            $row['customer_name'] = trim(($row['customer_firstname'] ?? '') . ' ' . ($row['customer_lastname'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    /**
     * خروجی اکسل سبک بدون پکیج جانبی
     *
     * @param array $range
     * @param string $period
     */
    private function exportExcel(array $range, $period)
    {
        $rows = $this->getSuccessRows($range);
        $fileName = 'nitron-pay-success-' . $range['slug'] . '-' . date('Ymd-His') . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        echo "\xEF\xBB\xBF";
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>';
        echo '<table border="1">';
        echo '<tr>';
        $headers = [
            'شناسه تراکنش',
            'تاریخ',
            'شماره سفارش',
            'مرجع سفارش',
            'سبد خرید',
            'مشتری',
            'ایمیل',
            'موبایل',
            'مبلغ ریال',
            'کد پیگیری',
            'کارت',
            'درگاه',
            'وضعیت',
        ];
        foreach ($headers as $h) {
            echo '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr>';

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . (int) $row['id_nitron_pay_transaction'] . '</td>';
            echo '<td>' . htmlspecialchars($row['date_add'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . ((int) $row['id_order'] ?: '-') . '</td>';
            echo '<td>' . htmlspecialchars($row['order_reference'] ?: '-', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . ((int) $row['id_cart'] ?: '-') . '</td>';
            echo '<td>' . htmlspecialchars($row['customer_name'] ?: '-', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($row['customer_email'] ?: '-', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($row['customer_phone'] ?: '-', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . (int) $row['amount_rial'] . '</td>';
            echo '<td>' . htmlspecialchars($row['ref_id'] ?: '-', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($row['card_pan'] ?: '-', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($row['gateway_name'] ?: '-', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>موفق</td>';
            echo '</tr>';
        }

        echo '</table></body></html>';
        exit;
    }
}
