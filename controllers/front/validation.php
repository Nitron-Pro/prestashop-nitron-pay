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

class Nitron_PayValidationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        $status = Tools::getValue('status');
        $siteOrderId = Tools::getValue('orderid');
        if (empty($siteOrderId)) {
            $siteOrderId = Tools::getValue('site_order_id');
        }
        $signature = Tools::getValue('signature');
        $refIdFromGet = Tools::getValue('ref_id');

        NitronPayLogger::log('callback_received', 'کال‌بک بازگشت از درگاه با متد GET دریافت شد.', $siteOrderId, null, $_GET);

        if (empty($siteOrderId)) {
            $this->errors[] = $this->module->l('شناسه سفارش ارسالی از بانک نامعتبر است.');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
            return;
        }

        $transaction = NitronPayTransaction::getBySiteOrderId($siteOrderId);
        if (!$transaction || !Validate::isLoadedObject($transaction)) {
            $this->errors[] = $this->module->l('اطلاعات تراکنش متناظر با این پرداخت یافت نشد.');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
            return;
        }

        $cart = new Cart((int) $transaction->id_cart);
        $customer = new Customer((int) $transaction->id_customer);
        if (!Validate::isLoadedObject($customer) && Validate::isLoadedObject($cart)) {
            $customer = new Customer((int) $cart->id_customer);
        }
        if (!Validate::isLoadedObject($customer)) {
            $this->errors[] = $this->module->l('مشتری مربوط به سفارش یافت نشد.');
            $this->redirectWithNotifications('index.php?controller=order&step=1');
            return;
        }

        /** @var Nitron_Pay $module */
        $module = $this->module;
        $idOrder = (int) $transaction->id_order;
        if ($idOrder <= 0 && Validate::isLoadedObject($cart)) {
            $idOrder = (int) Order::getIdByCartId((int) $cart->id);
        }
        $order = $idOrder > 0 ? new Order($idOrder) : null;
        $followUpUrl = ($order && Validate::isLoadedObject($order))
            ? $module->getOrderFollowUpUrl($order, $customer)
            : 'index.php?controller=order&step=1';

        $api = new NitronPayApi();

        // 1. بررسی امضای امنیتی
        $callbackParams = $_GET;
        // حذف پارامترهای مسیریابی خود پرستاشاپ در صورت وجود
        unset($callbackParams['controller'], $callbackParams['module'], $callbackParams['fc']);

        if (!empty($signature) && !$api->verifySignature($callbackParams, $signature)) {
            NitronPayLogger::log('verify_signature_failed', 'امضای بازگشتی با کلید امنیتی سرور همخوانی ندارد.', $siteOrderId, $transaction->uuid, $callbackParams);
            $this->errors[] = $this->module->l('اعتبارسنجی امضای امنیتی تراکنش ناموفق بود.');
            $this->redirectWithNotifications($followUpUrl);
            return;
        }

        // 2. اگر تراکنش لغو شده یا ناموفق است
        if ($status === 'failed' || $status === 'cancelled') {
            $transaction->status = $status;
            $transaction->save();

            NitronPayLogger::log('payment_cancelled', 'پرداخت توسط کاربر لغو یا ناموفق اعلام شد: ' . $status, $siteOrderId, $transaction->uuid);
            $failedStatus = (int) Configuration::get('NITRON_PAY_OS_FAILED');
            if ($idOrder > 0 && $failedStatus > 0) {
                $module->changeExistingOrderState($idOrder, $failedStatus);
            }
            $this->errors[] = $this->module->l('پرداخت شما در درگاه بانکی لغو یا ناموفق بود. می‌توانید دوباره از همین سفارش پرداخت کنید.');
            $this->redirectWithNotifications($followUpUrl);
            return;
        }

        $isVerifiedSuccess = false;
        $finalRefId = '';
        $cardPan = '';

        // 3. اگر حالت تایید توسط پنل بوده و وضعیت verified به همراه ref_id ارسال شده باشد
        if ($status === 'verified' && !empty($refIdFromGet)) {
            $isVerifiedSuccess = true;
            $finalRefId = $refIdFromGet;
            $transaction->ref_id = $finalRefId;
            $transaction->status = 'verified';
            $transaction->is_verified = 1;
            $transaction->save();
        } elseif ($status === 'awaiting_verify' || empty($status)) {
            // 4. حالت تایید توسط سایت: فراخوانی اندپوینت verify
            $verifyRes = $api->verifyPayment($siteOrderId, $transaction->uuid);
            $transaction->raw_verify_response = $verifyRes['raw'];

            if ($verifyRes['success']) {
                $vData = $verifyRes['data'];
                $finalRefId = isset($vData['ref_id']) ? $vData['ref_id'] : '';
                $isVerifiedSuccess = true;

                // استخراج جزئیات کارت در صورت وجود
                if (isset($vData['gateway_payload']['verify_response']['card_pan'])) {
                    $cardPan = $vData['gateway_payload']['verify_response']['card_pan'];
                }

                $transaction->ref_id = $finalRefId;
                $transaction->card_pan = $cardPan;
                $transaction->status = 'verified';
                $transaction->is_verified = 1;
                $transaction->save();
            } else {
                $transaction->status = 'verify_failed';
                $transaction->save();

                $errMsg = !empty($verifyRes['message']) ? $verifyRes['message'] : 'تایید نهایی تراکنش توسط بانک انجام نشد.';
                $failedStatus = (int) Configuration::get('NITRON_PAY_OS_FAILED');
                if ($idOrder > 0 && $failedStatus > 0) {
                    $module->changeExistingOrderState($idOrder, $failedStatus);
                }
                $this->errors[] = $errMsg;
                $this->redirectWithNotifications($followUpUrl);
                return;
            }
        }

        // 5. استعلام ضد هایجک و تایید یک‌بارمصرف از هسته نیترون‌پی (Anti-Hijack Lookup)
        if ($isVerifiedSuccess) {
            $lookupRes = $api->lookupPayment($siteOrderId, $transaction->uuid);
            if ($lookupRes['success'] && isset($lookupRes['data'])) {
                $lData = $lookupRes['data'];
                // بررسی شرط can_settle برای اطمینان از مجاز بودن تسویه و ثبت سفارش
                if (isset($lData['can_settle']) && !$lData['can_settle']) {
                    NitronPayLogger::log('lookup_settle_forbidden', 'تاییدیه تسویه تراکنش قبلاً صادر شده یا معتبر نیست (can_settle=false)', $siteOrderId, $transaction->uuid, null, $lData);
                    $this->errors[] = $this->module->l('این تراکنش قبلاً تایید و تسویه شده است و امکان استفاده مجدد ندارد.');
                    $this->redirectWithNotifications($followUpUrl);
                    return;
                }
                if (!empty($lData['ref_id'])) {
                    $finalRefId = $lData['ref_id'];
                    $transaction->ref_id = $finalRefId;
                }
                NitronPayLogger::log('lookup_success', 'تاییدیه یک‌بارمصرف هسته دریافت شد (can_settle=true)', $siteOrderId, $transaction->uuid, null, $lData);
            }
        }

        // 6. استعلام اختیاری مضاعف امنیتی از بانک (در صورت فعال بودن در تنظیمات ماژول)
        if ($isVerifiedSuccess && (int) Configuration::get('NITRON_PAY_ENABLE_INQUIRY', 1)) {
            $inquiryRes = $api->inquiryPayment($siteOrderId, $transaction->uuid);
            if ($inquiryRes['success']) {
                $iData = $inquiryRes['data'];
                if (isset($iData['ref_id']) && !empty($iData['ref_id'])) {
                    $finalRefId = $iData['ref_id'];
                    $transaction->ref_id = $finalRefId;
                }
                if (isset($iData['status']) && !empty($iData['status'])) {
                    $transaction->status = $iData['status'];
                }
                $transaction->save();
                NitronPayLogger::log('security_inquiry_success', 'استعلام امنیتی تراکنش از بانک با موفقیت انجام و تایید شد.', $siteOrderId, $transaction->uuid, null, $inquiryRes['data']);
            } else {
                NitronPayLogger::log('security_inquiry_warning', 'پاسخ استعلام بانک: ' . $inquiryRes['message'], $siteOrderId, $transaction->uuid, null, $inquiryRes['data']);
            }
        }

        // 7. در صورت تایید موفق، نهایی کردن سفارش از قبل ثبت‌شده
        if ($isVerifiedSuccess) {
            $totalOrder = (float) $transaction->amount_order;
            $currencyId = (int) $transaction->id_currency;
            $orderStatusId = (int) Configuration::get('NITRON_PAY_OS_SUCCESS');
            if ($orderStatusId <= 0) {
                $orderStatusId = (int) Configuration::get('PS_OS_PAYMENT');
            }

            if ($idOrder <= 0 && Validate::isLoadedObject($cart) && (int) $cart->orderExists() > 0) {
                $idOrder = (int) Order::getIdByCartId((int) $cart->id);
            }

            if ($idOrder <= 0 && Validate::isLoadedObject($cart)) {
                $extraVars = [
                    'transaction_id' => $finalRefId,
                ];

                $this->module->validateOrder(
                    (int) $cart->id,
                    $orderStatusId,
                    $totalOrder,
                    $this->module->displayName,
                    'کد پیگیری پرداخت نیترون: ' . $finalRefId . ($cardPan ? ' | کارت: ' . $cardPan : ''),
                    $extraVars,
                    $currencyId,
                    false,
                    $customer->secure_key
                );

                $idOrder = (int) $this->module->currentOrder;
            } elseif ($idOrder > 0) {
                $module->changeExistingOrderState($idOrder, $orderStatusId);
            }

            $transaction->id_order = $idOrder;
            $transaction->save();

            // ذخیره تراکنش در جدول پرداخت‌های سفارش پرستاشاپ (OrderPayment)
            if ($idOrder > 0) {
                $order = new Order($idOrder);
                if (Validate::isLoadedObject($order)) {
                    $payments = $order->getOrderPaymentCollection();
                    if ($payments && count($payments)) {
                        $orderPayment = $payments[0];
                        $orderPayment->transaction_id = $finalRefId;
                        if (!empty($cardPan)) {
                            $orderPayment->card_number = $cardPan;
                        }
                        $orderPayment->save();
                    }
                }
            }

            NitronPayLogger::log('order_validated_success', "سفارش #{$idOrder} با کد پیگیری {$finalRefId} با موفقیت ثبت شد.", $siteOrderId, $transaction->uuid);

            Tools::redirect(
                'index.php?controller=order-confirmation&id_cart=' . (int) $cart->id .
                '&id_module=' . (int) $this->module->id .
                '&id_order=' . (int) $idOrder .
                '&key=' . $customer->secure_key
            );
            return;
        }

        $this->errors[] = $this->module->l('پرداخت تایید نشد. می‌توانید از صفحه همین سفارش دوباره تلاش کنید.');
        $this->redirectWithNotifications($followUpUrl);
    }
}
