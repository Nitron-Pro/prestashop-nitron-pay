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
}
