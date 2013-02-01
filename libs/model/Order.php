<?php

class Order extends BaseItem implements IProductItemsContainer
{
    const
        ORDER_STATUS_HOLD = "Hold",
        ORDER_STATUS_PENDING = "Pending",
        ORDER_STATUS_PROCESSING = "Processing",
        ORDER_STATUS_COMPLETED = "Completed",
        ORDER_STATUS_CANCELED = "Canceled";

    public
        $order_srl,
        $module_srl,
        $cart_srl,
        $member_srl,
        $client_name,
        $client_email,
        $client_company,
        $billing_address,
        $shipping_address,
        $payment_method,
        $shipping_method,
		$shipping_variant,
        $shipping_cost,
        $total,
        $vat,
        $order_status,
        $ip,
        $regdate,
        $invoice,
        $shipment,
        $transaction_id,
        $discount_min_order,
        $discount_type,
        $discount_amount,
        $discount_tax_phase,
        $discount_reduction_value,
        $coupon_discount_value,
        $currency,
        $download_email_address;

    /** @var Cart */
    public $cart;

    /** @var OrderRepository */
    public $repo;

    public function save()
    {
        $rez = $this->order_srl ? $this->repo->update($this) : $this->repo->insert($this);
        if ($this->cart && $coupon = $this->cart->getCoupon()) $coupon->useOnce(true);
        return $rez;
    }

    public function __construct($data=null)
    {
        if ($data) {
            if($data instanceof Cart)
            {
                $this->cart = $data;
                $this->loadFromCart($data);
                parent::__construct();
                return;
            }
            foreach (array('billing_address', 'shipping_address', 'shipping_method', 'payment_method') as $val) {
                if (!isset($orderData[$val])) {
                    //throw new ShopException("Missing $val, can't continue.");
                }
            }
        }
        parent::__construct($data);
    }

    public function loadFromCart(Cart $cart)
    {
        $shopInfo = new ShopInfo($cart->module_srl);
        $this->cart_srl = $cart->cart_srl;
        $this->module_srl = $cart->module_srl;
        $this->member_srl = $cart->member_srl;
        $this->client_name = $cart->getBillingAddress()->firstname . ' ' . $cart->getBillingAddress()->lastname;
        $this->client_email = $cart->getBillingAddress()->email;
        $this->client_company = $cart->getBillingAddress()->company;
        $this->billing_address = (string) $cart->getBillingAddress();
        $this->shipping_address = (string) $cart->getShippingAddress();
        $this->payment_method = $cart->getPaymentMethodName();
        $this->shipping_method = $cart->getShippingMethodName();
		$this->shipping_variant = $cart->getShippingMethodVariant();
        $this->shipping_cost = $cart->getShippingCost();
        $this->total = $cart->getTotal(true);
        $this->vat = ($shopInfo->getVAT() ? $shopInfo->getVAT() : 0);
        $this->order_status = Order::ORDER_STATUS_PENDING;
        $this->ip = $_SERVER['REMOTE_ADDR'];
        $this->currency = $shopInfo->getCurrency();
        $this->download_email_address = $cart->getExtra('download_email_address');
        if ($discount = $cart->getDiscount()) {
            $this->discount_min_order = $discount->getMinValueForDiscount();
            $this->discount_type = $shopInfo->getShopDiscountType();
            $this->discount_amount = $discount->getReductionValue();
            $this->discount_tax_phase = $discount->calculateBeforeApplyingVAT() ? 'pre_taxes' : 'post_taxes';
            $this->discount_reduction_value = $discount->getReductionValue();
        }
        if ($coupon = $cart->getCoupon()) {
            $this->coupon_discount_value = $cart->getCouponDiscount();
        }
    }

    public function getBillToName()
    {
        return $this->client_name;
    }

    public function getShipToName()
    {
        return reset(explode(',',$this->shipping_address));
    }

    public function saveCartProducts(Cart $cart)
    {
        if (!$this->order_srl) throw new ShopException('Order not persisted');
        if (!$cart->cart_srl) throw new ShopException('Cart not persisted');
        //remove all already existing links
        $this->repo->deleteOrderProducts($this->order_srl);
        //set the new links
        /** @var $cart_product ICartItemProduct */
        $cart_products = $cart->getProducts();
        foreach ($cart_products as $cart_product) {
            if ($cart_product->available && $cart_product->product_srl) {
                $this->repo->insertOrderProduct($this->order_srl, $cart_product);
                if($cart_product->getProduct()->isDownloadable()){
                    // additional information for downloadable products
                    $downloadInfo = new DownloadInfo($this->order_srl, $cart_product->getProduct()->product_srl);
                    $this->repo->insertDownloadInfo($downloadInfo);
                }
            }
        }
    }

    public function getProducts()
    {
        return $this->repo->getOrderItems($this);
    }

    public function getShippingCost()
    {
        return $this->shipping_cost;
    }

    /**
     * Shipping method name
     */
    public function getShippingMethodName()
    {
        return $this->shipping_method;
    }

	public function getShippingMethodVariant()
	{
		return $this->shipping_variant;
	}

    public function getTotalBeforeDiscount()
    {
        return $this->total + $this->discount_amount - $this->shipping_cost;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function getVAT()
    {
        return $this->vat /100 * ($this->total - $this->shipping_cost);
    }

    /**
     * Returns discount object - used for calculating discounts
     * We return dummy objects, since we just want name and description
     */
    private function getDiscount()
    {
        switch ($this->discount_type)
        {
            case Discount::DISCOUNT_TYPE_FIXED_AMOUNT:
                return new FixedAmountDiscount($this->getTotal(), $this->discount_amount,$this->discount_min_order);
            case Discount::DISCOUNT_TYPE_PERCENTAGE:
                return new PercentageDiscount($this->getTotal(), $this->discount_amount,$this->discount_min_order);
            return null;
        }
    }

    /**
     * Discount name
     */
    public function getDiscountName()
    {
        $discount = $this->getDiscount();
        return $discount ? $discount->getName() : null;
    }

    /**
     * Discount description
     */
    public function getDiscountDescription()
    {
        $discount = $this->getDiscount();
        return $discount ? $discount->getDescription() : null;
    }

    /**
     * Discount amount
     */
    public function getDiscountAmount()
    {
        return $this->discount_amount;
    }

	private static function sendNewOrderMailToCustomer($shop, $order)
	{
		// Don't send anything if customer email is not configured
		if(!$order->client_email)
		{
			ShopLogger::log("Failed to send order email for order #$order->order_srl; Customer email is not set.");
			return;
		}

		global $lang;

		// 1. Send email to customer
		$email_subject = sprintf($lang->order_email_subject
			, $shop->getShopTitle()
			, ShopDisplay::priceFormat($order->total, $shop->getCurrencySymbol())
		);

		Context::set('email_order', $order);
		$oTemplateHandler = TemplateHandler::getInstance();
		$order_content = $oTemplateHandler->compile('./modules/shop/tpl', 'order_email.html');
		$email_content = sprintf($lang->order_email_content
			, $order->client_name
			, getFullSiteUrl('', 'act', 'dispShopViewOrder', 'order_srl', $order->order_srl)
			, $order->order_srl
			, $order_content
		);

		// Don't send anything if client email is not configured
		if(!$order->client_email)
		{
			ShopLogger::log("Failed to send order email to customer for order #$order->order_srl; Shop email is not configured");
			return;
		}
		$oMail = new Mail();
		$oMail->setTitle($email_subject);
		$oMail->setContent($email_content);
		$oMail->setSender($shop->getShopTitle(), $shop->getShopEmail());
		$oMail->setReceiptor(false, $order->client_email);
		$oMail->send();

	}

    public static function sendDownloadCodesToCustomer($order_srl){
        $repo = new OrderRepository();
        $order = $repo->getOrderBySrl($order_srl);
        $shop = new ShopInfo($order->module_srl);

        // do not send anything if customer's email for receiving codes is not set
        if (!isset($order->download_email_address)){
            ShopLogger::log("Failed to send download codes email for order #$order->order_srl; customer's email is not set.");
            return false;
        }
        if ($shop->getShopEmail() == null){
            ShopLogger::log("Failed to send download codes email for order #$order->order_srl; shop email is not set.");
            return false;
        }

        global $lang;

        $shopTitle = $shop->getShopTitle() != null ? $shop->getShopTitle() :  $shop->getBrowserTitle();
        $products_and_codes = self::getProductsAndCodesHTML($order);
        $downloadPageUrl = getFullUrl('','act','dispShopDownloadProduct','vid',$shop->getMid());
        $signature = $shop->getBrowserTitle();
        $shopEmail = $shop->getShopEmail();

        $email_subject = sprintf($lang->download_email_subject,
                            $order->order_srl, $shopTitle);
        $email_content = sprintf($lang->download_email_content,
                            $order->client_name, $order->order_srl, $products_and_codes,
                            $downloadPageUrl, $signature);
        $oMail = new Mail();
        $oMail->setTitle($email_subject);
        $oMail->setContent($email_content);
        $oMail->setSender($shopTitle, $shopEmail);
        $oMail->setReceiptor(false, $order->download_email_address);
        return $oMail->send();
    }

	private static function sendNewOrderMailToAdministrator($shop, $order)
	{
		// Don't send anything if admin email is not configured
		if(!$shop->getEmail())
		{
			ShopLogger::log("Failed to send order email to admin for order #$order->order_srl; Admin email is not configured");
			return;
		}

		global $lang;

		$admin_email_subject = sprintf($lang->admin_order_email_subject
			, $order->client_name
			, ShopDisplay::priceFormat($order->total, $shop->getCurrencySymbol())
		);

		Context::set('email_order', $order);
		$oTemplateHandler = TemplateHandler::getInstance();
		$order_content = $oTemplateHandler->compile('./modules/shop/tpl', 'order_email.html');

		$admin_email_content = sprintf($lang->admin_order_email_content
			, getFullSiteUrl('', 'act', 'dispShopToolViewOrder', 'order_srl', $order->order_srl)
			, $order->order_srl
			, $order_content
		);

		$oMail = new Mail();
		$oMail->setTitle($admin_email_subject);
		$oMail->setContent($admin_email_content);
		$oMail->setSender($shop->getShopTitle(), $shop->getShopEmail());
		$oMail->setReceiptor(false, $shop->getEmail());
		$oMail->send();
	}

	/**
	 * Send email to user notifying him of the newly created order
	 */
	public static function sendNewOrderEmails($order_srl)
	{
		$repo = new OrderRepository();
		$order = $repo->getOrderBySrl($order_srl);

		$shop = new ShopInfo($order->module_srl);

		// Don't send anything if shop email is not configured
		if(!$shop->getShopEmail())
		{
			ShopLogger::log("Failed to send order email for order #$order->order_srl; Shop email is not configured");
			return;
		}

		self::sendNewOrderMailToCustomer($shop, $order);
		self::sendNewOrderMailToAdministrator($shop, $order);
	}

    private static function getProductsAndCodesHTML(Order $order){
        global $lang;
        $result = '';
        foreach($order->getProducts() as $orderProduct){
            /**
             * @var $orderProduct OrderProduct
             */
            if ($orderProduct->isDownloadable()){
                $productName = $orderProduct->getTitle();
                $token = $order->repo->getDownloadInfo
                                ($order->order_srl, $orderProduct->product_srl)->getToken();
                $result.=sprintf($lang->download_email_product_line, $productName, $token);
                $result.="\n";
            }
        }
        return $result;
    }

    public function allProductsAreDownloadable(){
        /** @var $orderProduct OrderProduct */
        foreach ($this->getProducts() as $orderProduct){
            if (!$orderProduct->isDownloadable())
                return false;
        }
        return true;
    }

}