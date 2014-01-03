<?php

namespace Adyen;

class ParameterFilter
{
	private $allowed = array(
		'merchantAccount', 'paymentAmount', 'currencyCode', 'merchantReference', 'shipBeforeDate', 'skinCode',
		'shopperLocale', 'orderData', 'sessionValidity', 'merchantSig', 'merchantReturnData', 'countryCode',
		'shopperEmail', 'shopperReference', 'allowedMethods', 'blockedMethods', 'offset', 'shopperStatement',
		'offerEmail','pspReference', 'additionalAmount', 'customPaymentMethod', 'paymentMethod', 'recurringContract',
		'modificationAmount', 'originalReference', 'reference', 'response', 'merchantAccountCode', 'eventDate',
		'success', 'operations', 'reason', 'amount', 'deliveryAddressType', 'billingAddressType',
		'billingAddress.street','billingAddress.houseNumberOrName','billingAddress.city','billingAddress.postalCode',
		'billingAddress.stateOrProvince','billingAddress.country','billingAddressSig','billingAddressType',
		'shopper.firstName','shopper.infix','shopper.lastName','shopper.gender','shopper.dateOfBirthDayOfMonth',
		'shopper.dateOfBirthMonth','shopper.dateOfBirthYear','shopper.telephoneNumber','ShopperType','shopperSig',
		'resultUrl'
	);

	public function filter(array $parameters)
	{
		return array_intersect_key($parameters, array_flip($this->allowed));
	} 
}
