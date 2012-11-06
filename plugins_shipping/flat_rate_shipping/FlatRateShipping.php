<?php

require_once dirname(__FILE__) . '/../ShippingMethodAbstract.php';

class FlatRateShipping extends ShippingMethodAbstract
{
    public function __construct()
    {
        $this->shipping_method_dir = _XE_PATH_ . 'modules/shop/plugins_shipping/flat_rate_shipping';
        parent::__construct();
    }

    /**
     * Calculates shipping rates
     *
     * @param Cart $cart SHipping cart for which to calculate shipping
     * @param Address $shipping_address Address to which products should be shipped
     */
    public function calculateShipping(Cart $cart, Address $shipping_address = null)
    {
        if($this->type == 'per_item')
        {
            $products = $cart->getProducts();
            $total_quantity = 0;
            foreach($products as $product)
                $total_quantity += $product->quantity;
            return $total_quantity * $this->price;
        }

        return $this->price;
    }

	/**
	 * Checks is custom plugin parameters are set and valid;
	 * If no validation is needed, just return true;
	 * @return mixed
	 */
	public function isConfigured(&$error_message = 'msg_invalid_request')
	{
		if(!isset($this->price))
		{
			$error_message = 'msg_missing_shipping_price';
			return false;
		}

		if(!isset($this->type))
		{
			$error_message = 'msg_missing_shipping_type';
			return false;
		}

		if(!in_array($this->type, array('per_item', 'per_order')))
		{
			$error_message = 'msg_invalid_shipping_type';
			return false;
		}
		if(!is_numeric($this->price))
		{
			$error_message = 'msg_invalid_shipping_price';
			return false;
		}
		return true;
	}
}