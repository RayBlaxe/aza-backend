<?php

namespace App\Services;

use Exception;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createPaymentToken($order)
    {
        $params = [
            'transaction_details' => [
                'order_id' => $order->order_number,
                'gross_amount' => (int) $order->total_amount,
            ],
            'customer_details' => [
                'first_name' => $order->user->name,
                'email' => $order->user->email,
                'phone' => $order->user->phone ?? '',
            ],
            'item_details' => $this->buildItemDetails($order),
            'callbacks' => [
                'finish' => config('app.frontend_url').'/payment/success',
                'unfinish' => config('app.frontend_url').'/payment/pending',
                'error' => config('app.frontend_url').'/payment/failed',
            ],
        ];

        if ($order->shipping_address) {
            $params['customer_details']['shipping_address'] = [
                'first_name' => $order->user->name,
                'phone' => $order->user->phone ?? '',
                'address' => $order->shipping_address['address'] ?? '',
                'city' => $order->shipping_address['city'] ?? '',
                'postal_code' => $order->shipping_address['postal_code'] ?? '',
                'country_code' => 'IDN',
            ];
        }

        try {
            $snapToken = Snap::getSnapToken($params);

            return [
                'success' => true,
                'snap_token' => $snapToken,
                'redirect_url' => "https://app.sandbox.midtrans.com/snap/v2/vtweb/{$snapToken}",
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function handleNotification()
    {
        try {
            $notification = new Notification;

            $transactionStatus = $notification->transaction_status;
            $type = $notification->payment_type;
            $orderId = $notification->order_id;
            $fraud = $notification->fraud_status;

            $status = null;

            if ($transactionStatus == 'capture') {
                if ($fraud == 'challenge') {
                    $status = 'challenge';
                } elseif ($fraud == 'accept') {
                    $status = 'paid';
                }
            } elseif ($transactionStatus == 'settlement') {
                $status = 'paid';
            } elseif ($transactionStatus == 'cancel' ||
                     $transactionStatus == 'deny' ||
                     $transactionStatus == 'expire') {
                $status = 'failed';
            } elseif ($transactionStatus == 'pending') {
                $status = 'pending';
            }

            return [
                'order_id' => $orderId,
                'status' => $status,
                'transaction_status' => $transactionStatus,
                'payment_type' => $type,
                'fraud_status' => $fraud,
                'raw_notification' => $notification->getResponse(),
            ];
        } catch (Exception $e) {
            throw new Exception('Invalid notification: '.$e->getMessage());
        }
    }

    public function verifySignature($orderId, $statusCode, $grossAmount, $serverKey)
    {
        $hash = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return $hash;
    }

    private function buildItemDetails($order)
    {
        $items = [];

        foreach ($order->orderItems as $item) {
            $items[] = [
                'id' => $item->product_id,
                'price' => (int) $item->price,
                'quantity' => $item->quantity,
                'name' => $item->product_name,
            ];
        }

        if ($order->shipping_cost > 0) {
            $items[] = [
                'id' => 'SHIPPING',
                'price' => (int) $order->shipping_cost,
                'quantity' => 1,
                'name' => 'Shipping Cost',
            ];
        }

        return $items;
    }
}
