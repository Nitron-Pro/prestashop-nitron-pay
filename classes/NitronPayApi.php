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

class NitronPayApi
{
    private $apiKey;
    private $apiSecret;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = trim(Configuration::get('NITRON_PAY_API_KEY'));
        $this->apiSecret = trim(Configuration::get('NITRON_PAY_API_SECRET'));
        $url = trim(Configuration::get('NITRON_PAY_BASE_URL'));
        if (empty($url)) {
            $url = 'https://app.nitron.pro/pay';
        }
        $this->baseUrl = rtrim($url, '/');
    }

    /**
     * ایجاد تراکنش پرداخت
     *
     * @param int $amountRial مبلغ به ریال
     * @param string $siteOrderId شناسه سفارش یکتا
     * @param string $callbackUrl آدرس بازگشت
     * @param array $extraParams
     * @return array
     */
    public function createPayment($amountRial, $siteOrderId, $callbackUrl, array $extraParams = [])
    {
        $payload = array_merge([
            'amount' => (int) $amountRial,
            'site_order_id' => (string) $siteOrderId,
            'callback_url' => (string) $callbackUrl,
        ], $extraParams);

        return $this->request('POST', '/api/v1/payments', $payload, $siteOrderId);
    }

    /**
     * وریفای تراکنش پرداخت
     *
     * @param string $siteOrderId
     * @param string|null $uuid
     * @return array
     */
    public function verifyPayment($siteOrderId, $uuid = null)
    {
        $payload = [
            'site_order_id' => (string) $siteOrderId,
        ];
        if (!empty($uuid)) {
            $payload['uuid'] = (string) $uuid;
        }

        return $this->request('POST', '/api/v1/payments/verify', $payload, $siteOrderId, $uuid);
    }

    /**
     * استعلام وضعیت تراکنش
     *
     * @param string $siteOrderId
     * @param string|null $uuid
     * @return array
     */
    public function inquiryPayment($siteOrderId, $uuid = null)
    {
        $payload = [
            'site_order_id' => (string) $siteOrderId,
        ];
        if (!empty($uuid)) {
            $payload['uuid'] = (string) $uuid;
        }

        return $this->request('POST', '/api/v1/payments/inquiry', $payload, $siteOrderId, $uuid);
    }

    /**
     * دریافت جزئیات کامل یک تراکنش با مراحل و تایم‌لاین
     *
     * @param string $uuid
     * @return array
     */
    public function getTransactionDetails($uuid)
    {
        return $this->request('GET', '/api/v1/reports/transactions/' . urlencode($uuid), null, null, $uuid);
    }

    /**
     * دریافت لیست درگاه‌ها
     *
     * @return array
     */
    public function getGateways()
    {
        return $this->request('GET', '/api/v1/gateways');
    }

    /**
     * اعتبارسنجی امضای بازگشتی از کال‌بک
     *
     * @param array $params پارامترهای GET دریافتی
     * @param string $signature امضای ارسالی در درخواست
     * @return bool
     */
    public function verifySignature(array $params, $signature)
    {
        if (empty($this->apiSecret)) {
            // اگر سکرت ست نشده باشد، امکان بررسی امضا نیست
            return true;
        }

        if (empty($signature)) {
            return false;
        }

        // حذف امضا و پارامترهای سیستم از آرایه
        unset($params['signature']);

        // فقط پارامترهای مجاز محاسبه امضا را مرتب و بیلد می‌کنیم
        ksort($params);
        $queryString = http_build_query($params);
        $expectedSignature = hash_hmac('sha256', $queryString, $this->apiSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * ارسال درخواست HTTP به وب‌سرویس
     */
    private function request($method, $endpoint, $payload = null, $siteOrderId = null, $uuid = null)
    {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($payload)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            NitronPayLogger::log(
                $method . ' ' . $endpoint,
                'خطای ارتباط cURL: ' . $curlError,
                $siteOrderId,
                $uuid,
                $payload,
                null,
                0
            );

            return [
                'success' => false,
                'message' => 'خطا در برقراری ارتباط با سرور درگاه پرداخت: ' . $curlError,
                'status_code' => 0,
                'data' => null,
            ];
        }

        $decoded = json_decode($rawResponse, true);

        NitronPayLogger::log(
            $method . ' ' . $endpoint,
            'پاسخ وب‌سرویس با کد ' . $httpCode,
            $siteOrderId,
            $uuid,
            $payload,
            $decoded ?: $rawResponse,
            $httpCode
        );

        $isSuccess = ($httpCode >= 200 && $httpCode < 300);
        if (is_array($decoded) && isset($decoded['success'])) {
            $isSuccess = (bool) $decoded['success'];
        }

        return [
            'success' => $isSuccess,
            'status_code' => $httpCode,
            'message' => isset($decoded['message']) ? $decoded['message'] : ($isSuccess ? 'عملیات با موفقیت انجام شد' : 'خطا در پردازش درخواست'),
            'data' => isset($decoded['data']) ? $decoded['data'] : $decoded,
            'raw' => $rawResponse,
        ];
    }
}
