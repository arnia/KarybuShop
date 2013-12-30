<?php


/**
 * Class for integrating Stripe payment gateway in XE Shop
 *
 * @author Alexandru Ganga (alexandru.ganga@arnia.ro)
 */
class Stripe extends PaymentMethodAbstract
{
    /**
     * User friendly name for this payment plugin
     *
     * @return string
     */
    public function getDisplayName()
    {
        return 'Stripe';
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
     * Returns Stripe name
     *
     * @return string
     */
    public function getSelectPaymentHtml()
    {
        return $this->display_name;
    }

    /**
     * Handles all IPN notifications from Stripe
     */
    public function notify($cart)
    {
        require_once('./lib/Stripe.php');

        $secret_key = ($this->env_type == 'TESTING')?$this->test_secret_key:$this->live_secret_key;
        Stripe::setApiKey($secret_key);

        // Retrieve the request's body and parse it as JSON
        $body = @file_get_contents('php://input');
        $event_json = json_decode($body);

        $id = $event_json->id;
        $event = Stripe_Event::retrieve($id);
        if (__DEBUG__) {
            ShopLogger::log("Received IPN Notification: " . http_build_query($event));
        }

        if ($event['type'] == 'charge.succeeded') {
            $charge = $event['data']['object'];
            $paymentId = $charge['id'];
            $paymentAmount = $charge['amount'];
            $paymentCurrency = $charge['currency'];

            if (!$order = $this->orderCreatedForThisTransaction($paymentId)) {
                // check the payment
                if ($charge['paid'] != true)
                {
                    ShopLogger::log("Payment is not completed.");
                    $this->markTransactionAsFailedInUserCart(
                        $paymentId,
                        $paymentId,
                        "Your payment was not completed. Your order was not created."
                    );
                    return;
                }

                $cart = new Cart($paymentId);
                if (($paymentAmount != $cart->getTotal()) || ($paymentCurrency != $cart->getCurrency())) {
                    ShopLogger::log("Invalid payment. " . PHP_EOL
                        . "Payment amount [" . $paymentAmount . "] instead of " . $cart->getTotal() . PHP_EOL
                        . "Payment currency [" . $paymentCurrency . "] instead of " . $cart->getCurrency()
                    );
                    $this->markTransactionAsFailedInUserCart(
                        $paymentId,
                        $paymentId,
                        "Your payment was invalid. Your order was not created."
                    );
                    return;
                }

                // create order
                $this->createNewOrderAndDeleteExistingCart($cart, $paymentId);
            } else {
                // perform logic when the validation fails
                if (__DEBUG__) {
                    ShopLogger::log("Validation for IPN Notification failed.");
                }

            }
        }
    }

    /**
     * Page where user is redirected back to after
     * he completed the payment on the stripe website
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
        require_once('./lib/Stripe.php');

        $secret_key = ($this->env_type == 'TESTING')?$this->test_secret_key:$this->live_secret_key;
        Stripe::setApiKey($secret_key);
        $token = $_POST['stripeToken'];
        $amount =  $_POST['amount'];
        $currency = $_POST['currency'];
        $description = $_POST['description'];
        $pay_email = $_POST['pay_email'];

        try {
            $charge = Stripe_Charge::create(array(
                    "amount" => $amount,
                    "currency" => $currency,
                    "card" => $token,
                    "description" => $description)
            );
        } catch(Stripe_CardError $e) {
            // The card has been declined
            return;
        }

        $paymentId = $charge['id'];
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
            // skipping any requests to stripe
            Context::set('order_srl', $order->order_srl);
            return;
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
        if (!isset($this->test_secret_key) || !isset($this->test_public_key)
                || !isset($this->live_secret_key) || !isset($this->live_public_key)
                || !isset($this->env_type)) {
            $error_message = 'msg_authorize_missing_fields';
            return FALSE;
        }
        return TRUE;
    }
}

?>