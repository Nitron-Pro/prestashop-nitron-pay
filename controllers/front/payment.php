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

class Nitron_PayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        $cart = $this->context->cart;

        if (!$this->module->active || !Validate::isLoadedObject($cart) || $cart->nbProducts() <= 0) {
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0) {
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        $currency = $this->context->currency;
        $totalOrder = (float) $cart->getOrderTotal(true, Cart::BOTH);

        /** @var Nitron_Pay $module */
        $module = $this->module;
        $amountRial = $module->convertAmountToRial($totalOrder, $currency);

        if ($amountRial < 1000) {
            NitronPayLogger::log('create_payment', 'مبلغ کمتر از حداقل مجاز درگاه (۱۰۰۰ ریال) است: ' . $amountRial);
            $this->errors[] = $this->module->l('مبلغ سفارش کمتر از حداقل مجاز برای پرداخت آنلاین (۱۰۰۰ ریال) است.');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
            return;
        }

        $existingOrderId = (int) Order::getIdByCartId((int) $cart->id);
        if ($existingOrderId > 0) {
            $existingOrder = new Order($existingOrderId);
            if (Validate::isLoadedObject($existingOrder) && !$module->isOrderPayable($existingOrder)) {
                Tools::redirect(
                    'index.php?controller=order-confirmation&id_cart=' . (int) $cart->id .
                    '&id_module=' . (int) $module->id .
                    '&id_order=' . $existingOrderId .
                    '&key=' . $customer->secure_key
                );
                return;
            }
        }

        $order = $module->createPendingOrder($cart, $customer, $currency, $totalOrder);
        if (!$order || !Validate::isLoadedObject($order)) {
            $this->errors[] = $this->module->l('ثبت سفارش پیش از پرداخت با خطا مواجه شد. لطفاً دوباره تلاش کنید.');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
            return;
        }

        $this->startGatewayPayment($order, $customer, $currency, $totalOrder, $amountRial);
    }

    /**
     * ساخت تراکنش و هدایت به درگاه برای سفارش از قبل ثبت‌شده
     *
     * @param Order $order
     * @param Customer $customer
     * @param Currency $currency
     * @param float $totalOrder
     * @param int $amountRial
     */
    private function startGatewayPayment(Order $order, Customer $customer, Currency $currency, $totalOrder, $amountRial)
    {
        /** @var Nitron_Pay $module */
        $module = $this->module;

        $siteOrderId = 'NP' . (int) $order->id . '_' . time();
        $callbackUrl = $this->context->link->getModuleLink($module->name, 'validation', [], true);

        $extraParams = [
            'description' => 'پرداخت سفارش #' . (int) $order->id . ' در ' . Configuration::get('PS_SHOP_NAME'),
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
        $transaction->id_shop = (int) $this->context->shop->id;
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

            Tools::redirectLink($data['payment_url']);
            return;
        }

        $transaction->status = 'create_failed';
        $transaction->raw_create_response = $response['raw'];
        $transaction->save();

        $errorMessage = !empty($response['message']) ? $response['message'] : 'خطا در ایجاد پرداخت بانکی. لطفاً مجدداً تلاش کنید.';
        $this->errors[] = $errorMessage;
        $this->redirectWithNotifications($module->getOrderFollowUpUrl($order, $customer));
    }
}
