<?php

/*
* TODO
*
* - notify() - IPN  - http://www.alex.com/karybu/shop/?act=procShopPaymentNotify&payment_method_name=ogone
* - onOrderConfirmationPageLoad() - create order - http://www.alex.com/karybu/shop/?act=dispShopOrderConfirmation&payment_method_name=ogone
*
* - test IPN
* - DirectLink
* - 3DSecure
*/

//use Symfony\Component\ClassLoader\MapClassLoader;
use Ogone\Passphrase;
use Ogone\PaymentRequest;
use Ogone\PaymentResponse;
use Ogone\ShaComposer\ShaComposer;
use Ogone\ShaComposer\AllParametersShaComposer;
use Ogone\ParameterFilter\ShaInParameterFilter;
use Ogone\FormGenerator\FormGenerator;
use Ogone\FormGenerator\SimpleFormGenerator;
use InvalidArgumentException;

/**
 * Class for integrating Ogone payment gateway in XE Shop
 *
 * @author Alexandru Ganga (alexandru.ganga@arnia.ro)
 */
class Ogone extends PaymentMethodAbstract
{

    /*public function __construct() {
        $mapping = array(
            'FormGenerator' => __DIR__ . '/../../libs/classes/Ogone/FormGenerator/FormGenerator',
            'SimpleFormGenerator' => __DIR__ . '/../../libs/classes/Ogone/FormGenerator/SimpleFormGenerator',
        );

        $loader = new MapClassLoader($mapping);
        $loader->register();
    }*/

    /**
     * User friendly name for this payment plugin
     *
     * @return string
     */
    public function getDisplayName()
    {
        return 'Ogone';
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
     * Ogone form action
     *
     * @return string
     */
    public function getPaymentFormAction()
    {
        return $this->getGatewayType();
    }

    /**
     * Returns Ogone name
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

        $passphrase = new Passphrase($this->sha_in_passphrase);
        $shaComposer = new AllParametersShaComposer($passphrase);
        $shaComposer->addParameterFilter(new ShaInParameterFilter);

        $paymentRequest = new PaymentRequest($shaComposer);
        $paymentRequest->setOgoneUri($this->getGatewayType());
        $paymentRequest->setPspid($this->pspid);
        $paymentRequest->setOrderid($cart->cart_srl);

        $paymentRequest->setCurrency($cart->getCurrency());
        $paymentRequest->setLanguage(getLocale($countryCode, Context::getLangType()));

        $paymentRequest->setOwnerAddress($billing_address->address);
        $paymentRequest->setOwnerZip($billing_address->postal_code);
        $paymentRequest->setOwnerTown($billing_address->city);

        $paymentRequest->setOwnerCountry($countryCode);

        //$paymentRequest->setPaymentMethod('CreditCard');
        //$paymentRequest->setBrand('VISA');

        $paymentRequest->setCn($cart->getCustomerFirstname() . " " . $cart->getCustomerLastname());
        //$paymentRequest->setEmail($cart->getExtra('email'));
        $paymentRequest->setEmail($logged_info->email_address);

        // $this->getNotifyUrl()
        // $this->getOrderConfirmationPageUrl()
        $paymentRequest->setAccepturl($this->getOrderConfirmationPageUrl());

        $vid = Context::get('vid');
        $shopUrl = getNotEncodedFullUrl('', 'vid', $vid);
        $checkoutUrl = getNotEncodedFullUrl('', 'vid', $vid, 'act', 'dispShopCheckout');
        $placeOrderUrl = getNotEncodedFullUrl('', 'vid', $vid, 'act', 'dispShopPlaceOrder');

        $paymentRequest->setDeclineurl($checkoutUrl);
        $paymentRequest->setExceptionurl($checkoutUrl);
        $paymentRequest->setCancelurl($checkoutUrl);

        //$paymentRequest->setBackurl($placeOrderUrl);
        //$paymentRequest->setHomeurl($shopUrl);
        //$paymentRequest->setCatalogurl($shopUrl);

        //$paymentRequest->setDynamicTemplateUri($shopUrl);

        //$paymentRequest->setOrderDescription("Your order");

        $paymentRequest->setAmount($cart->getTotal() * 100); // in cents

        $paymentRequest->validate();

        $formGenerator = new SimpleFormGenerator;
        $formGenerator->showSubmitButton(false);
        $html = $formGenerator->render($paymentRequest);

        return $html;
    }

    /**
     * @brief Get test/production URL
     */
    public function getGatewayType()
    {
        if ($this->properties->gateway_type == 'TEST') {
            return "https://secure.ogone.com/ncol/test/orderstandard_utf8.asp";
        } else {
            return "https://secure.ogone.com/ncol/prod/orderstandard_utf8.asp";
        }
    }

    /**
     * Handles all IPN notifications from Ogone
     */
    public function notify($cart)
    {
        $args = $_REQUEST;

        if (__DEBUG__) {
            ShopLogger::log("Received IPN Notification: " . http_build_query($args));
        }

        $paymentResponse = new PaymentResponse($args);
        $passphrase = new Passphrase($this->sha_out_passphrase);
        $shaComposer = new AllParametersShaComposer($passphrase);
        $shaComposer->addParameterFilter(new ShaOutParameterFilter);

        if ($paymentResponse->isValid($shaComposer) && $paymentResponse->isSuccessful()) {
            // handle payment confirmation
            // params: ACCEPTANCE, STATUS, PAYID, ORDERID
            $params = $paymentResponse->toArray();
            $paymentOrderId = $paymentResponse->getParam('ORDERID');
            $paymentId = $paymentResponse->getParam('PAYID');
            $paymentAmount = $paymentResponse->getParam('AMOUNT');
            $paymentCurrency = $paymentResponse->getParam('CURRENCY');

            if (__DEBUG__) {
                ShopLogger::log("Successfully validated IPN data: " . http_build_query($params));

            }

            if (!$order = $this->orderCreatedForThisTransaction($paymentOrderId)) {
                $paymentStatus = $paymentResponse->getParam('STATUS');
                $paymentAcceptance = $paymentResponse->getParam('ACCEPTANCE');
                // check the payment_status is Completed
                if ($paymentStatus != 5) // status != Authorized
                {
                    ShopLogger::log("Payment is not completed. Payment status [" . $paymentStatus . "] received");
                    $this->markTransactionAsFailedInUserCart(
                        $paymentOrderId,
                        $paymentId,
                        "Your payment was not completed. Your order was not created."
                    );
                    return;
                }

                $cart = new Cart($paymentOrderId);
                if (($paymentAmount != $cart->getTotal()) || ($paymentCurrency != $cart->getCurrency())) {
                    ShopLogger::log("Invalid payment. " . PHP_EOL
                        . "Payment amount [" . $paymentAmount . "] instead of " . $cart->getTotal() . PHP_EOL
                        . "Payment currency [" . $paymentCurrency . "] instead of " . $cart->getCurrency()
                    );
                    $this->markTransactionAsFailedInUserCart(
                        $paymentOrderId,
                        $paymentId,
                        "Your payment was invalid. Your order was not created."
                    );
                    return;
                }

                // 3. If the source of the POST is correct, we can now use the data to create an order
                // based on the message received
                $this->createNewOrderAndDeleteExistingCart($cart, $paymentId);
            }
        } else {
            // perform logic when the validation fails
            if (__DEBUG__) {
                ShopLogger::log("Validation for IPN Notification failed.");
            }
        }
    }

    /**
     * Page where user is redirected back to after
     * he completed the payment on the ogone website
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
        $paymentId = Context::get('PAYID');
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
            // skipping any requests to ogone
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
        if (!isset($this->pspid) || !isset($this->sha_in_passphrase) || !isset($this->gateway_type)) {
            $error_message = 'msg_authorize_missing_fields';
            return FALSE;
        }
        return TRUE;
    }
}

?>