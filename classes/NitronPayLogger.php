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

class NitronPayLogger
{
    /**
     * @param string $action
     * @param string $message
     * @param string|null $siteOrderId
     * @param string|null $uuid
     * @param mixed $requestPayload
     * @param mixed $responsePayload
     * @param int|null $statusCode
     * @return bool
     */
    public static function log($action, $message, $siteOrderId = null, $uuid = null, $requestPayload = null, $responsePayload = null, $statusCode = null)
    {
        $context = Context::getContext();
        $idShop = (int) ($context->shop ? $context->shop->id : Configuration::get('PS_SHOP_DEFAULT'));

        $reqStr = is_string($requestPayload) ? $requestPayload : ($requestPayload ? json_encode($requestPayload, JSON_UNESCAPED_UNICODE) : null);
        $resStr = is_string($responsePayload) ? $responsePayload : ($responsePayload ? json_encode($responsePayload, JSON_UNESCAPED_UNICODE) : null);

        try {
            return Db::getInstance()->insert('nitron_pay_log', [
                'id_shop' => (int) $idShop,
                'site_order_id' => $siteOrderId ? pSQL($siteOrderId) : null,
                'uuid' => $uuid ? pSQL($uuid) : null,
                'action' => pSQL($action),
                'request_payload' => $reqStr ? pSQL($reqStr, true) : null,
                'response_payload' => $resStr ? pSQL($resStr, true) : null,
                'status_code' => $statusCode !== null ? (int) $statusCode : null,
                'message' => pSQL($message),
                'date_add' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            return false;
        }
    }
}
