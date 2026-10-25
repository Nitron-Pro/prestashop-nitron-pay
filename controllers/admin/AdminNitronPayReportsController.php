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

    public function postProcess()
    {
        if (Tools::isSubmit('exportExcel')) {
            $period = Tools::getValue('report_period', 'daily');
            $customFrom = trim(Tools::getValue('filter_date_from', ''));
            $customTo = trim(Tools::getValue('filter_date_to', ''));
            $this->exportExcel($this->resolveDateRange($period, $customFrom, $customTo));
        }

        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();

        $period = Tools::getValue('report_period', 'daily');
        $customFrom = trim(Tools::getValue('filter_date_from', ''));
        $customTo = trim(Tools::getValue('filter_date_to', ''));
        $range = $this->resolveDateRange($period, $customFrom, $customTo);

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
     * فایل xlsx واقعی با ZipArchive، بدون پکیج اکسل
     *
     * @param array $range
     */
    private function exportExcel(array $range)
    {
        if (!class_exists('ZipArchive')) {
            $this->errors[] = 'افزونه ZipArchive روی PHP فعال نیست و ساخت xlsx ممکن نشد.';
            return;
        }

        $rows = $this->getSuccessRows($range);
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

        $sheetRows = [];
        $sheetRows[] = $this->xlsxRow(1, $headers, []);
        $line = 2;
        foreach ($rows as $row) {
            $sheetRows[] = $this->xlsxRow($line, [
                (int) $row['id_nitron_pay_transaction'],
                $row['date_add'],
                (int) $row['id_order'] ?: '-',
                $row['order_reference'] ?: '-',
                (int) $row['id_cart'] ?: '-',
                $row['customer_name'] ?: '-',
                $row['customer_email'] ?: '-',
                $row['customer_phone'] ?: '-',
                (int) $row['amount_rial'],
                $row['ref_id'] ?: '-',
                $row['card_pan'] ?: '-',
                $row['gateway_name'] ?: '-',
                'موفق',
            ], [0 => true, 8 => true]);
            $line++;
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews>'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . '</worksheet>';

        $fileName = 'nitron-pay-success-' . $range['slug'] . '-' . date('Ymd-His') . '.xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'npx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            $this->errors[] = 'ساخت فایل اکسل ممکن نشد.';
            return;
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="پرداخت‌های موفق" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="1"><xf/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: max-age=0');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /**
     * @param int $rowNumber
     * @param array $values
     * @param array $numericColumns
     * @return string
     */
    private function xlsxRow($rowNumber, array $values, array $numericColumns)
    {
        $cells = '';
        $col = 0;
        foreach ($values as $value) {
            $ref = $this->xlsxColumn($col) . $rowNumber;
            if (!empty($numericColumns[$col]) && is_numeric($value)) {
                $cells .= '<c r="' . $ref . '"><v>' . (int) $value . '</v></c>';
            } else {
                $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
            }
            $col++;
        }

        return '<row r="' . (int) $rowNumber . '">' . $cells . '</row>';
    }

    /**
     * @param int $index
     * @return string
     */
    private function xlsxColumn($index)
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = (int) (($index - $mod) / 26);
        }

        return $name;
    }
}
