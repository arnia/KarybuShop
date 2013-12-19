<?php

/*
* TODO
*
* - notify() - IPN  - http://www.alex.com/karybu/shop/?act=procShopPaymentNotify&payment_method_name=ogone
* - onOrderConfirmationPageLoad() - create order - http://www.alex.com/karybu/shop/?act=dispShopOrderConfirmation&payment_method_name=ogone
*
* - redirect method - onOrderConfirmationPageLoad()
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

    public function getPaymentFormAction()
    {
        return $this->getGatewayType();
    }

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
        $passphrase = new Passphrase($this->sha_in_passphrase);
        $shaComposer = new AllParametersShaComposer($passphrase);
        $shaComposer->addParameterFilter(new ShaInParameterFilter);

        $paymentRequest = new PaymentRequest($shaComposer);
        $paymentRequest->setOgoneUri($this->getGatewayType());
        $paymentRequest->setPspid($this->pspid);
        $cart = Context::get('cart');
        $paymentRequest->setOrderid($cart->cart_srl);

        $paymentRequest->setCurrency('EUR');
        //$paymentRequest->setLanguage(Context::getLangType());

        //$paymentRequest->setPaymentMethod('CreditCard');
        //$paymentRequest->setBrand('VISA');

        $paymentRequest->setCn($cart->getCustomerFirstname()." ".$cart->getCustomerLastname());
        //$paymentRequest->setEmail($cart->getExtra('email'));

        // $this->getNotifyUrl()
        // $this->getOrderConfirmationPageUrl()
        $paymentRequest->setAccepturl($this->getOrderConfirmationPageUrl());

        //$paymentRequest->setDeclineurl('http://www.alex.com/karybu/shop/?act=dispShopCheckout');
        //$paymentRequest->setExceptionurl('http://www.alex.com/karybu/shop/?act=dispShopCheckout');
        //$paymentRequest->setCancelurl('http://www.alex.com/karybu/shop/?act=dispShopCheckout');

        //$paymentRequest->setBackurl('http://www.alex.com/karybu/shop/?act=dispShopPlaceOrder');
        //$paymentRequest->setHomeurl('http://www.alex.com/karybu/shop/');
        //$paymentRequest->setCatalogurl('http://www.alex.com/karybu/shop/');

        //$paymentRequest->setDynamicTemplateUri('http://example.com/template');

        //$paymentRequest->setFeedbackMessage("Thanks for ordering");
        //$paymentRequest->setOrderDescription("Four horses and a carriage");


        $paymentRequest->setAmount($cart->getTotal() * 100); // in cents

        $paymentRequest->validate();

        $formGenerator = new SimpleFormGenerator;
        $formGenerator->showSubmitButton(false);
        $html = $formGenerator->render($paymentRequest);

        return $html;
    }

    public function getGatewayType(){
        if($this->properties->gateway_type == 'TEST'){
            return "https://secure.ogone.com/ncol/test/orderstandard_utf8.asp";
        }else{
            return "https://secure.ogone.com/ncol/prod/orderstandard_utf8.asp";
        }
    }
    /**
     * Handles all IPN notifications from Paypal
     */
    public function notify($cart)
    {
        $args = $_REQUEST;

        if(__DEBUG__)
        {
            ShopLogger::log("Received IPN Notification: " . http_build_query($args));
        }

        $paymentResponse = new PaymentResponse($args);
        $passphrase = new Passphrase($this->sha_out_passphrase);
        $shaComposer = new AllParametersShaComposer($passphrase);
        $shaComposer->addParameterFilter(new ShaOutParameterFilter);

        if($paymentResponse->isValid($shaComposer) && $paymentResponse->isSuccessful()) {
            // handle payment confirmation
            // params: ACCEPTANCE, STATUS, PAYID, ORDERID
            $params = $paymentResponse->toArray();
            $paymentOrderId = $paymentResponse->getParam('ORDERID');
            $paymentId =  $paymentResponse->getParam('PAYID');
            $paymentAmount =  $paymentResponse->getParam('AMOUNT');
            $paymentCurrency =  $paymentResponse->getParam('CURRENCY');

            if(__DEBUG__)
            {
                ShopLogger::log("Successfully validated IPN data: " . http_build_query($params));

            }

            if(!$order = $this->orderCreatedForThisTransaction($paymentOrderId))
            {
                $paymentStatus = $paymentResponse->getParam('STATUS');
                $paymentAcceptance = $paymentResponse->getParam('ACCEPTANCE');
                // check the payment_status is Completed
                if($paymentStatus != 5) // status != Authorized
                {
                    ShopLogger::log("Payment is not completed. Payment status [" . $paymentStatus. "] received");
                    $this->markTransactionAsFailedInUserCart(
                        $paymentOrderId,
                        $paymentId,
                        "Your payment was not completed. Your order was not created."
                    );
                    return;
                }

                $cart = new Cart($paymentOrderId);
                if(($paymentAmount != $cart->getTotal()) || ($paymentCurrency != $cart->getCurrency()))
                {
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
        }
        else {
            // perform logic when the validation fails
            if(__DEBUG__)
            {
                ShopLogger::log("Validation for IPN Notification failed.");
            }
        }
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