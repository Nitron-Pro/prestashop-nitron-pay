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

class NitronPayTransaction extends ObjectModel
{
    /** @var int */
    public $id_nitron_pay_transaction;

    /** @var int */
    public $id_shop;

    /** @var int */
    public $id_cart;

    /** @var int */
    public $id_order;

    /** @var int */
    public $id_customer;

    /** @var string */
    public $uuid;

    /** @var string */
    public $site_order_id;

    /** @var string */
    public $gateway_name;

    /** @var int */
    public $amount_rial;

    /** @var float */
    public $amount_order;

    /** @var int */
    public $id_currency;

    /** @var string */
    public $status;

    /** @var string */
    public $ref_id;

    /** @var string */
    public $card_pan;

    /** @var string */
    public $payment_url;

    /** @var int */
    public $is_verified;

    /** @var string */
    public $raw_create_response;

    /** @var string */
    public $raw_verify_response;

    /** @var string */
    public $date_add;

    /** @var string */
    public $date_upd;

    public static $definition = [
        'table' => 'nitron_pay_transaction',
        'primary' => 'id_nitron_pay_transaction',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_cart' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'uuid' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'site_order_id' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 64],
            'gateway_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'amount_rial' => ['type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true],
            'amount_order' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true],
            'id_currency' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32],
            'ref_id' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'card_pan' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32],
            'payment_url' => ['type' => self::TYPE_STRING],
            'is_verified' => ['type' => self::TYPE_INT, 'validate' => 'isBool'],
            'raw_create_response' => ['type' => self::TYPE_STRING],
            'raw_verify_response' => ['type' => self::TYPE_STRING],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * @param string $siteOrderId
     * @return NitronPayTransaction|null
     */
    public static function getBySiteOrderId($siteOrderId)
    {
        $id = Db::getInstance()->getValue(
            'SELECT `id_nitron_pay_transaction` FROM `' . _DB_PREFIX_ . 'nitron_pay_transaction` ' .
            'WHERE `site_order_id` = \'' . pSQL($siteOrderId) . '\' ' .
            'ORDER BY `id_nitron_pay_transaction` DESC'
        );

        if ($id) {
            return new self((int) $id);
        }

        return null;
    }

    /**
     * @param string $uuid
     * @return NitronPayTransaction|null
     */
    public static function getByUuid($uuid)
    {
        $id = Db::getInstance()->getValue(
            'SELECT `id_nitron_pay_transaction` FROM `' . _DB_PREFIX_ . 'nitron_pay_transaction` ' .
            'WHERE `uuid` = \'' . pSQL($uuid) . '\' ' .
            'ORDER BY `id_nitron_pay_transaction` DESC'
        );

        if ($id) {
            return new self((int) $id);
        }

        return null;
    }

    /**
     * @param int $idOrder
     * @return NitronPayTransaction|null
     */
    public static function getLatestByOrderId($idOrder)
    {
        $id = Db::getInstance()->getValue(
            'SELECT `id_nitron_pay_transaction` FROM `' . _DB_PREFIX_ . 'nitron_pay_transaction` ' .
            'WHERE `id_order` = ' . (int) $idOrder . ' ' .
            'ORDER BY `id_nitron_pay_transaction` DESC'
        );

        if ($id) {
            return new self((int) $id);
        }

        return null;
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function isSuccessStatus($status)
    {
        return in_array($status, ['verified', 'success'], true);
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function isFailedStatus($status)
    {
        return in_array($status, ['failed', 'cancelled', 'canceled', 'fail', 'verify_failed', 'create_failed'], true);
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function isPendingStatus($status)
    {
        return !self::isSuccessStatus($status) && !self::isFailedStatus($status) && $status !== 'superseded';
    }

    /**
     * برچسب و کلاس نمایش وضعیت برای مدیر فروشگاه
     *
     * @param string $status
     * @return array
     */
    public static function getStatusMeta($status)
    {
        $map = [
            'verified' => ['label' => 'موفق / تایید شده', 'class' => 'success', 'group' => 'success'],
            'success' => ['label' => 'موفق / تایید شده', 'class' => 'success', 'group' => 'success'],
            'initiated' => ['label' => 'شروع شده', 'class' => 'default', 'group' => 'pending'],
            'redirected_to_gateway' => ['label' => 'ارسال به درگاه — هنوز پرداخت نشده', 'class' => 'info', 'group' => 'pending'],
            'returned_to_site' => ['label' => 'بازگشت از درگاه — در انتظار تایید', 'class' => 'warning', 'group' => 'pending'],
            'failed' => ['label' => 'ناموفق', 'class' => 'danger', 'group' => 'failed'],
            'fail' => ['label' => 'ناموفق', 'class' => 'danger', 'group' => 'failed'],
            'cancelled' => ['label' => 'لغو شده توسط مشتری', 'class' => 'danger', 'group' => 'failed'],
            'canceled' => ['label' => 'لغو شده توسط مشتری', 'class' => 'danger', 'group' => 'failed'],
            'verify_failed' => ['label' => 'خطا در تایید بانک', 'class' => 'danger', 'group' => 'failed'],
            'create_failed' => ['label' => 'خطا در ایجاد پرداخت', 'class' => 'danger', 'group' => 'failed'],
            'superseded' => ['label' => 'جایگزین شده با تلاش جدید', 'class' => 'default', 'group' => 'pending'],
        ];

        if (isset($map[$status])) {
            return $map[$status];
        }

        return [
            'label' => $status ? $status : 'نامشخص',
            'class' => 'warning',
            'group' => 'pending',
        ];
    }

    /**
     * تایم‌لاین محلی بر اساس وضعیت واقعی تراکنش (بدون جعل موفقیت)
     *
     * @param NitronPayTransaction $tx
     * @return array
     */
    public static function buildLocalStages($tx)
    {
        $status = (string) $tx->status;
        $ref = !empty($tx->ref_id) ? $tx->ref_id : '-';
        $stages = [];

        if ($status === 'create_failed') {
            $stages[] = [
                'name' => '۱. ثبت سفارش و ساخت پرداخت',
                'state' => 'failed',
                'message' => 'ایجاد تراکنش در هسته نیترون پی ناموفق بود. سفارش در فروشگاه ثبت شده و لینک پرداخت مجدد قابل ارسال است.',
            ];
            $stages[] = [
                'name' => '۲. ارسال به درگاه',
                'state' => 'skipped',
                'message' => 'به دلیل خطا در ساخت پرداخت، مشتری به درگاه نرفت.',
            ];
            $stages[] = [
                'name' => '۳. بازگشت و تایید نهایی',
                'state' => 'skipped',
                'message' => 'این مرحله انجام نشده است.',
            ];

            return $stages;
        }

        $stages[] = [
            'name' => '۱. ثبت سفارش و ساخت پرداخت',
            'state' => 'done',
            'message' => 'سفارش در فروشگاه ثبت و درخواست پرداخت به نیترون ارسال شد.',
        ];

        if ($status === 'initiated') {
            $stages[] = [
                'name' => '۲. ارسال به درگاه',
                'state' => 'current',
                'message' => 'تراکنش ساخته شده اما هدایت به درگاه کامل نشده است.',
            ];
            $stages[] = [
                'name' => '۳. بازگشت از درگاه',
                'state' => 'skipped',
                'message' => 'مشتری هنوز به درگاه نرفته است.',
            ];
            $stages[] = [
                'name' => '۴. تایید نهایی پرداخت',
                'state' => 'skipped',
                'message' => 'پرداختی تایید نشده است.',
            ];

            return $stages;
        }

        $stages[] = [
            'name' => '۲. ارسال به درگاه',
            'state' => $status === 'redirected_to_gateway' ? 'current' : 'done',
            'message' => 'مشتری به صفحه پرداخت هدایت شد. این مرحله به‌معنی پرداخت موفق نیست.',
        ];

        if ($status === 'redirected_to_gateway') {
            $stages[] = [
                'name' => '۳. بازگشت از درگاه',
                'state' => 'current',
                'message' => 'مشتری هنوز از درگاه برنگشته یا پرداخت را نیمه‌کاره رها کرده است. می‌توانید لینک پرداخت را دوباره برایش بفرستید.',
            ];
            $stages[] = [
                'name' => '۴. تایید نهایی پرداخت',
                'state' => 'skipped',
                'message' => 'تا بازگشت موفق از درگاه، تاییده‌ای وجود ندارد.',
            ];

            return $stages;
        }

        if (in_array($status, ['failed', 'cancelled', 'canceled', 'fail'], true)) {
            $stages[] = [
                'name' => '۳. بازگشت از درگاه',
                'state' => 'failed',
                'message' => 'پرداخت لغو یا ناموفق اعلام شد. سفارش در فروشگاه مانده و قابل پیگیری و پرداخت مجدد است.',
            ];
            $stages[] = [
                'name' => '۴. تایید نهایی پرداخت',
                'state' => 'skipped',
                'message' => 'چون پرداخت ناموفق بود، تایید نهایی انجام نشد.',
            ];

            return $stages;
        }

        if ($status === 'returned_to_site') {
            $stages[] = [
                'name' => '۳. بازگشت از درگاه',
                'state' => 'done',
                'message' => 'مشتری از درگاه بازگشت؛ تایید نهایی هنوز قطعی نشده است.',
            ];
            $stages[] = [
                'name' => '۴. تایید نهایی پرداخت',
                'state' => 'current',
                'message' => 'در انتظار تایید هسته / بانک.',
            ];

            return $stages;
        }

        if ($status === 'verify_failed') {
            $stages[] = [
                'name' => '۳. بازگشت از درگاه',
                'state' => 'done',
                'message' => 'بازگشت از درگاه انجام شد.',
            ];
            $stages[] = [
                'name' => '۴. تایید نهایی پرداخت',
                'state' => 'failed',
                'message' => 'تایید بانک/هسته ناموفق بود. سفارش را با لینک پرداخت مجدد پیگیری کنید.',
            ];

            return $stages;
        }

        if ($status === 'superseded') {
            $stages[] = [
                'name' => '۳. تلاش جدید جایگزین شد',
                'state' => 'skipped',
                'message' => 'برای همین سفارش لینک پرداخت تازه‌ای ساخته شده است.',
            ];

            return $stages;
        }

        if (self::isSuccessStatus($status)) {
            $stages[] = [
                'name' => '۳. بازگشت از درگاه',
                'state' => 'done',
                'message' => 'مشتری با نتیجه پرداخت به فروشگاه بازگشت.',
            ];
            $stages[] = [
                'name' => '۴. تایید نهایی پرداخت',
                'state' => 'done',
                'message' => 'پرداخت تایید شد. کد پیگیری: ' . $ref,
            ];

            return $stages;
        }

        $stages[] = [
            'name' => '۳. وضعیت جاری',
            'state' => 'current',
            'message' => 'وضعیت فعلی تراکنش: ' . $status,
        ];

        return $stages;
    }

    /**
     * تلاش‌های ناتمام قبلی همان سفارش را بایگانی می‌کند
     *
     * @param int $idOrder
     * @param int $exceptId
     */
    public static function markPreviousAttemptsSuperseded($idOrder, $exceptId = 0)
    {
        if ((int) $idOrder <= 0) {
            return;
        }

        $sql = 'UPDATE `' . _DB_PREFIX_ . 'nitron_pay_transaction` SET `status` = \'superseded\', `date_upd` = NOW() ' .
            'WHERE `id_order` = ' . (int) $idOrder .
            ' AND `is_verified` = 0' .
            ' AND `status` NOT IN (\'verified\', \'success\', \'superseded\')';

        if ((int) $exceptId > 0) {
            $sql .= ' AND `id_nitron_pay_transaction` != ' . (int) $exceptId;
        }

        Db::getInstance()->execute($sql);
    }
}
