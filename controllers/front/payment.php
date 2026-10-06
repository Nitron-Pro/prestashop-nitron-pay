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

        // تولید شناسه سفارش منحصر به‌فرد برای سایت
        $siteOrderId = $cart->id . '_' . time();

        $callbackUrl = $this->context->link->getModuleLink(
            $this->module->name,
            'validation',
            [],
            true
        );

        $extraParams = [
            'description' => 'پرداخت سفارش سبد خرید #' . $cart->id . ' در ' . Configuration::get('PS_SHOP_NAME'),
            'payer_email' => $customer->email,
        ];

        // شماره موبایل در صورت وجود
        $address = new Address($cart->id_address_invoice ?: $cart->id_address_delivery);
        if (Validate::isLoadedObject($address)) {
            $mobile = !empty($address->phone_mobile) ? $address->phone_mobile : $address->phone;
            if (!empty($mobile)) {
                $extraParams['payer_mobile'] = $mobile;
            }
        }

        // ثبت اولیه تراکنش در دیتابیس
        $transaction = new NitronPayTransaction();
        $transaction->id_shop = (int) $this->context->shop->id;
        $transaction->id_cart = (int) $cart->id;
        $transaction->id_customer = (int) $cart->id_customer;
        $transaction->id_currency = (int) $currency->id;
        $transaction->site_order_id = $siteOrderId;
        $transaction->amount_rial = (int) $amountRial;
        $transaction->amount_order = (float) $totalOrder;
        $transaction->status = 'initiated';
        $transaction->is_verified = 0;
        $transaction->add();

        // فراخوانی API نیترون برای ایجاد تراکنش
        $api = new NitronPayApi();
        $response = $api->createPayment($amountRial, $siteOrderId, $callbackUrl, $extraParams);

        if ($response['success'] && !empty($response['data']['payment_url'])) {
            $data = $response['data'];
            $paymentUrl = $data['payment_url'];
            $uuid = isset($data['uuid']) ? $data['uuid'] : null;
            $gateway = isset($data['gateway']) ? $data['gateway'] : null;

            $transaction->uuid = $uuid;
            $transaction->gateway_name = $gateway;
            $transaction->payment_url = $paymentUrl;
            $transaction->status = isset($data['status']) ? $data['status'] : 'redirected_to_gateway';
            $transaction->raw_create_response = $response['raw'];
            $transaction->save();

            // انتقال کاربر به آدرس درگاه بانک با GET
            Tools::redirectLink($paymentUrl);
            return;
        }

        // در صورت بروز خطا در ایجاد تراکنش
        $transaction->status = 'create_failed';
        $transaction->raw_create_response = $response['raw'];
        $transaction->save();

        $errorMessage = !empty($response['message']) ? $response['message'] : 'خطا در ایجاد پرداخت بانکی. لطفاً مجدداً تلاش کنید.';
        $this->errors[] = $errorMessage;
        $this->redirectWithNotifications('index.php?controller=order&step=1');
    }
}
