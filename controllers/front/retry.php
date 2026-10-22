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

class Nitron_PayRetryModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = false;

    public function postProcess()
    {
        /** @var Nitron_Pay $module */
        $module = $this->module;

        $idOrder = (int) Tools::getValue('id_order');
        $key = trim((string) Tools::getValue('key'));

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order) || $order->module !== $module->name) {
            $this->errors[] = $this->module->l('سفارش مورد نظر برای پرداخت یافت نشد.');
            $this->redirectWithNotifications('index.php?controller=history');
            return;
        }

        $customer = new Customer((int) $order->id_customer);
        if (!Validate::isLoadedObject($customer) || empty($key) || $key !== $customer->secure_key) {
            $this->errors[] = $this->module->l('لینک پرداخت نامعتبر است.');
            $this->redirectWithNotifications('index.php?controller=history');
            return;
        }

        if (!$module->isOrderPayable($order)) {
            $this->errors[] = $this->module->l('این سفارش قبلاً پرداخت شده یا دیگر قابل پرداخت نیست.');
            Tools::redirect($module->getOrderFollowUpUrl($order, $customer));
            return;
        }

        $currency = new Currency((int) $order->id_currency);
        $totalOrder = (float) $order->total_paid;
        $amountRial = $module->convertAmountToRial($totalOrder, $currency);

        if ($amountRial < 1000) {
            $this->errors[] = $this->module->l('مبلغ سفارش کمتر از حداقل مجاز برای پرداخت آنلاین است.');
            $this->redirectWithNotifications($module->getOrderFollowUpUrl($order, $customer));
            return;
        }

        $siteOrderId = 'NP' . (int) $order->id . '_' . time();
        $callbackUrl = $this->context->link->getModuleLink($module->name, 'validation', [], true);

        $extraParams = [
            'description' => 'پرداخت مجدد سفارش #' . (int) $order->id . ' در ' . Configuration::get('PS_SHOP_NAME'),
            'payer_email' => $customer->email,
        ];

        $address = new Address((int) $order->id_address_invoice ?: (int) $order->id_address_delivery);
        if (Validate::isLoadedObject($address)) {
            $mobile = !empty($address->phone_mobile) ? $address->phone_mobile : $address->phone;
            if (!empty($mobile)) {
                $extraParams['payer_mobile'] = $mobile;
            }
        }

        $transaction = new NitronPayTransaction();
        $transaction->id_shop = (int) $order->id_shop;
        $transaction->id_cart = (int) $order->id_cart;
        $transaction->id_order = (int) $order->id;
        $transaction->id_customer = (int) $order->id_customer;
        $transaction->id_currency = (int) $currency->id;
        $transaction->site_order_id = $siteOrderId;
        $transaction->amount_rial = (int) $amountRial;
        $transaction->amount_order = (float) $totalOrder;
        $transaction->status = 'initiated';
        $transaction->is_verified = 0;
        $transaction->add();

        NitronPayTransaction::markPreviousAttemptsSuperseded((int) $order->id, (int) $transaction->id);

        $api = new NitronPayApi();
        $response = $api->createPayment($amountRial, $siteOrderId, $callbackUrl, $extraParams);

        if ($response['success'] && !empty($response['data']['payment_url'])) {
            $data = $response['data'];
            $transaction->uuid = isset($data['uuid']) ? $data['uuid'] : null;
            $transaction->gateway_name = isset($data['gateway']) ? $data['gateway'] : null;
            $transaction->payment_url = $data['payment_url'];
            $transaction->status = isset($data['status']) ? $data['status'] : 'redirected_to_gateway';
            $transaction->raw_create_response = $response['raw'];
            $transaction->save();

            $pendingStatus = (int) Configuration::get('NITRON_PAY_OS_PENDING');
            if ($pendingStatus > 0) {
                $module->changeExistingOrderState((int) $order->id, $pendingStatus);
            }

            NitronPayLogger::log('retry_payment', 'پرداخت مجدد سفارش #' . (int) $order->id . ' آغاز شد.', $siteOrderId, $transaction->uuid);
            Tools::redirectLink($data['payment_url']);
            return;
        }

        $transaction->status = 'create_failed';
        $transaction->raw_create_response = $response['raw'];
        $transaction->save();

        $this->errors[] = !empty($response['message']) ? $response['message'] : $this->module->l('خطا در ایجاد پرداخت بانکی. لطفاً دوباره تلاش کنید.');
        $this->redirectWithNotifications($module->getOrderFollowUpUrl($order, $customer));
    }
}
