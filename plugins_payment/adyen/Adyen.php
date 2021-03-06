<?php

/*
 * TODO
 *
 * - test IPN
 */

/**
 * Class for integrating Adyen payment gateway in XE Shop
 *
 * @author Alexandru Ganga (alexandru.ganga@arnia.ro)
 */

class Adyen extends PaymentMethodAbstract
{
    /**
     * User friendly name for this payment plugin
     *
     * @return string
     */
    public function getDisplayName()
    {
        return 'Adyen';
    }

    /**
     * Process the payment
     *
     * @param Cart $cart
     * @param      $error_message
     * @return bool|mixed
     */
    public function processPayment(Cart $cart, &$error_message)
    {
    }

    /**
     * Adyen form action
     *
     * @return string
     */
    public function getPaymentFormAction()
    {
        return $this->getGatewayType();
    }

    /**
     * Returns Adyen name
     *
     * @return string
     */
    public function getSelectPaymentHtml()
    {
        return $this->display_name;
    }

    /**
     * Returns the HTML to display on the checkout form
     * to gather info from users - like credit card number
     *
     * @return string
     */
    public function getPaymentFormHTML()
    {
        require_once(_KARYBU_PATH_ . 'modules/shop/shop.locales.php');
        $countries = require_once(_KARYBU_PATH_ . 'modules/shop/shop.countries.php');

        $cart = Context::get('cart');
        $billing_address = $cart->getBillingAddress();
        $logged_info = Context::get('logged_info');

        $countryCode = array_search($billing_address->country, $countries);

        $paymentRequest = new Adyen\PaymentRequest($this->secret_key);
        $paymentRequest->setMerchantAccount($this->merchant_account);
        $paymentRequest->setSkinCode($this->skin_code);
        $paymentRequest->setCurrencyCode($cart->getCurrency());
        $paymentRequest->setShopperLocale(getLocale($countryCode, Context::getLangType()));
        $paymentRequest->setCountryCode($countryCode);
        $paymentRequest->setShopperEmail($logged_info->email_address);
        $paymentRequest->setResultUrl($this->getOrderConfirmationPageUrl());
        $paymentRequest->setPaymentAmount($cart->getTotal() * 100); // in cents
        $paymentRequest->validate();

        $formGenerator = new Adyen\FormGenerator;
        $formGenerator->showSubmitButton(false);
        $html = $formGenerator->render($paymentRequest);

        return $html;
    }

    /**
     * @brief Get test/production URL
     */
    public function getGatewayType()
    {
        if ($this->properties->test == 'TEST') {
            return "https://test.adyen.com/hpp/select.shtml";
        } else {
            return "https://live.adyen.com/hpp/select.shtml";
        }
    }

    /**
     * Handles all IPN notifications from Adyen
     */
    public function notify()
    {
        $args = $_REQUEST;
        if (__DEBUG__) {
            ShopLogger::log("Received IPN Notification: " . http_build_query($args));
        }

        $notificationResponse = new Adyen\NotificationResponse($args);

        // handle payment confirmation
        $params = $notificationResponse->toArray();
        $paymentId = $notificationResponse->getParam('pspReference');
        $paymentAmount = $notificationResponse->getAmount();
        $paymentCurrency = $notificationResponse->getCurrency();

        if (__DEBUG__) {
            ShopLogger::log("Successfully validated IPN data: " . http_build_query($params));
        }

        if (!$order = $this->orderCreatedForThisTransaction($paymentId)) {
            $paymentCartId = $order->cart_srl;
            $paymentStatus = $notificationResponse->getParam('success');
            // check the payment_status is true
            if (!$paymentStatus)
            {
                ShopLogger::log("Payment is not completed. Payment status [" . $paymentStatus . "] received");
                $this->markTransactionAsFailedInUserCart(
                    $paymentCartId,
                    $paymentId,
                    "Your payment was not completed. Your order was not created."
                );
                return;
            }

            $cart = new Cart($paymentCartId);
            if (($paymentAmount != $cart->getTotal()) || ($paymentCurrency != $cart->getCurrency())) {
                ShopLogger::log("Invalid payment. " . PHP_EOL
                    . "Payment amount [" . $paymentAmount . "] instead of " . $cart->getTotal() . PHP_EOL
                    . "Payment currency [" . $paymentCurrency . "] instead of " . $cart->getCurrency()
                );
                $this->markTransactionAsFailedInUserCart(
                    $paymentCartId,
                    $paymentId,
                    "Your payment was invalid. Your order was not created."
                );
                return;
            }

            // 3. If the source of the POST is correct, we can now use the data to create an order
            // based on the message received
            $this->createNewOrderAndDeleteExistingCart($cart, $paymentId);

            // get created order
            $model = getModel('shop');
            $orderRepository = $model->getOrderRepository();
            $order_srl = Context::get('order_srl');
            $order = $orderRepository->getOrderBySrl($order_srl);
        }

        // generate invoice
        $args = new StdClass();
        $args->order_srl = $order->order_srl;
        $args->module_srl = $order->module_srl;
        $invoice = new Invoice($args);
        $invoice->save();
        if ($invoice->invoice_srl) {
            if (isset($order->shipment))
                $order->order_status = Order::ORDER_STATUS_COMPLETED;
            else
                $order->order_status = Order::ORDER_STATUS_PROCESSING;
            try {
                $order->save();
            }
            catch(Exception $e) {
                return new Object(-1, $e->getMessage());
            }
        } else {
            throw new ShopException('Something whent wrong when adding invoice');
        }
    }

    /**
     * Page where user is redirected back to after
     * he completed the payment on the Adyen website
     *
     * If an order has not been created, we create it now
     * If payment is complete, we update order status to Processing
     *
     * If an error occurred, we show it to the user
     *
     * @param $cart
     * @param $module_srl
     * @throws NetworkErrorException
     * @return void
     */
    public function onOrderConfirmationPageLoad($cart, $module_srl)
    {
        $args = $_REQUEST;
        $paymentResponse = new Adyen\PaymentResponse($args);
        if (!$paymentResponse->isValid($this->secret_key))
            return;

        $result = Context::get('authResult');
        $paymentId = Context::get('pspReference');
        if (($result == 'AUTHORISED') || ($result == 'PENDING')) {
            if (!$paymentId) {
                return;
            }

            if (!$order = $this->orderCreatedForThisTransaction($paymentId)) {
                if ($this->thisTransactionWasAlreadyProcessedAndWasInvalid($cart, $paymentId)) {
                    $this->redirectUserToOrderUnsuccessfulPageAndShowHimTheErrorMessage($cart->getTransactionErrorMessage());
                    return;
                } else {
                    $this->createNewOrderAndDeleteExistingCart($cart, $paymentId);
                }
            } else {
                // Order already exists for this transaction, so we'll just display it
                // skipping any requests to Adyen
                Context::set('order_srl', $order->order_srl);
                return;
            }
        }
    }

    /**
     * Given a transaction id, checks if an order was created or not for it
     * (from an IPN call, for instance)
     *
     * @param $transaction_id
     * @return boolean
     */
    private function orderCreatedForThisTransaction($transaction_id)
    {
        $orderRepository = new OrderRepository();
        $order = $orderRepository->getOrderByTransactionId($transaction_id);
        return $order;
    }

    /**
     * Checks if a transaction was already processed but was invalid
     * causing the order not to be created;
     * Thus, even though there is no order created, we should not parse this again
     */
    private function thisTransactionWasAlreadyProcessedAndWasInvalid(Cart $cart, $transaction_id)
    {
        return $cart->getTransactionId()
        && $cart->getTransactionId() == $transaction_id;
    }

    /**
     * Redirects the user to an error page, informing him why the payment failed
     *
     * @param $error_message
     */
    private function redirectUserToOrderUnsuccessfulPageAndShowHimTheErrorMessage($error_message)
    {
        $shopController = getController('shop');
        $shopController->setMessage($error_message, "error");
        $this->redirect($this->getPlaceOrderPageUrl());
    }

    /**
     * Make sure all mandatory fields are set
     */
    public function isConfigured(&$error_message = 'msg_invalid_request')
    {
        if (!isset($this->merchant_account) || !isset($this->secret_key) || !isset($this->skin_code)
                 || !isset($this->test)) {
            $error_message = 'msg_authorize_missing_fields';
            return FALSE;
        }
        return TRUE;
    }
}

?>