<?php

namespace Adyen;

use Adyen\ParameterFilter;

use InvalidArgumentException;
use RuntimeException;
use BadMethodCallException;

class PaymentRequest
{
	const TEST = "";
	const PRODUCTION = "";

	/** @var array of ParameterFilter */
	private $parameterFilters; 

	private $secret = "";

	private $adyenUri = self::TEST;

	private $parameters = array();

	private $adyenFields = array(
		'merchantAccount', 'paymentAmount', 'currencyCode', 'merchantReference', 'shipBeforeDate', 'skinCode',
		'shopperLocale', 'orderData', 'sessionValidity', 'merchantSig'
	);

	private $requiredfields = array(
		'merchantAccount', 'currencyCode', 'paymentAmount', 'skinCode', 'sessionValidity', 'merchantSig'
	);

	public $allowedcurrencies = array(
		'AED', 'ANG', 'ARS', 'AUD', 'AWG', 'BGN', 'BRL', 'BYR', 'CAD', 'CHF',
		'CNY', 'CZK', 'DKK', 'EEK', 'EGP', 'EUR', 'GBP', 'GEL', 'HKD', 'HRK',
		'HUF', 'ILS', 'ISK', 'JPY', 'KRW', 'LTL', 'LVL', 'MAD', 'MXN', 'NOK',
		'NZD', 'PLN', 'RON', 'RUB', 'SEK', 'SGD', 'SKK', 'THB', 'TRY', 'UAH',
		'USD', 'XAF', 'XOF', 'XPF', 'ZAR'
	);

	public $allowedlanguages = array(
		'en_US' => 'English', 'cs_CZ' => 'Czech', 'de_DE' => 'German',
		'dk_DK' => 'Danish', 'el_GR' => 'Greek', 'es_ES' => 'Spanish',
		'fr_FR' => 'French', 'it_IT' => 'Italian', 'ja_JP' => 'Japanese',
		'nl_BE' => 'Flemish', 'nl_NL' => 'Dutch', 'no_NO' => 'Norwegian',
		'pl_PL' => 'Polish', 'pt_PT' => 'Portugese', 'ru_RU' => 'Russian',
		'se_SE' => 'Swedish', 'sk_SK' => 'Slovak', 'tr_TR' => 'Turkish'
	);

	public function __construct($secret="")
	{
		$this->secret = $secret;
		$this->addParameterFilter(new ParameterFilter);

		$today = new \DateTime();
		$this->parameters['sessionValidity'] = $today->modify('+3 hours')->format(DATE_ATOM);
	}

	public function addParameterFilter(ParameterFilter $parameterFilter)
	{
		$this->parameterFilters[] = $parameterFilter;
	}

	/** @return string */
	public function getAdyenUri()
	{
		return $this->adyenUri;
	}

	/** Adyen uri to send the customer to. Usually PaymentRequest::TEST or PaymentRequest::PRODUCTION */
	public function setAdyenUri($adyenUri)
	{
		$this->validateUri($adyenUri);
		$this->adyenUri = $adyenUri;
	}

	public function setMerchantAccount($merchant)
	{
		if (strlen($merchant) > 50) {
			throw new InvalidArgumentException("Merchant is too long");
		}
		$this->parameters['merchantAccount'] = $merchant;
	}

	/**
	 * Set amount in cents, eg EUR 12.34 is written as 1234
	 */
	public function setPaymentAmount($amount)
	{
		if(!is_int($amount)) {
			throw new InvalidArgumentException("Integer expected. Amount is always in cents");
		}
		if($amount <= 0) {
			throw new InvalidArgumentException("Amount must be a positive number");
		}
		if($amount >= 1.0E+15) {
			throw new InvalidArgumentException("Amount is too high");
		}
		$this->parameters['paymentAmount'] = $amount;

	}

	public function setCurrencyCode($currency)
	{
		if(!in_array(strtoupper($currency), $this->allowedcurrencies)) {
			throw new InvalidArgumentException("Unknown currency");
		}
		$this->parameters['currencyCode'] = $currency;
	}

	public function setCountryCode($country)
	{
		if (!preg_match('/^[A-Z]{2}$/', strtoupper($country))) {
			throw new InvalidArgumentException("Illegal country code");
		}
		$this->parameters['countryCode'] = $country;
	}

	/**
	 * ISO code eg nl-BE
	 */
	public function setShopperLocale($language)
	{
		if(!array_key_exists($language, $this->allowedlanguages)) {
			throw new InvalidArgumentException("Invalid language ISO code");
		}
		$this->parameters['shopperLocale'] = $language;
	}

	public function setShopperEmail($email)
	{
		if(strlen($email) > 50) {
			throw new InvalidArgumentException("Email is too long");
		}
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new InvalidArgumentException("Email is invalid");
		}
		$this->parameters['shopperEmail'] = $email;
	}

	public function setResultUrl($resultUrl)
	{
		$this->validateUri($resultUrl);
		$this->parameters['resultUrl'] = $resultUrl;
	}

	public function validate()
	{
		// calculate signature
		$plain = $this->getPlains();
		$this->parameters['merchantSig'] = $this->calculateSignature($plain);

		if (!empty($this->parameters['billingAddressType'])) {
			$billing = $this->getPlainsBilling();
			$this->parameters['billingAddressSig'] = $this->calculateSignature($billing);
		}

		if (!empty($this->parameters['ShopperType'])) {
			$shopper = $this->getPlainsShopper();
			$this->parameters['shopperSig'] = $this->calculateSignature($shopper);
		}

		// validate parameters
		foreach($this->requiredfields as $field)
		{
			if(empty($this->parameters[$field])) {
				throw new RuntimeException("$field can not be empty");
			}
		}
	}

	public function __call($method, $args)
	{
		if(substr($method, 0, 3) == 'set') {
			$field = lcfirst(substr($method, 3));
			if (in_array($field, $this->adyenFields)) {
				$this->parameters[$field] = $args[0];
				return;
			}
		}

		if(substr($method, 0, 3) == 'get') {
			$field = lcfirst(substr($method, 3));
			if(array_key_exists($field, $this->parameters)) {
				return $this->parameters[$field];
			}
		}

		throw new BadMethodCallException("Unknown method $method");
	}

	public function toArray()
	{
		$this->validate();
		
		// filter parameters
		$parameters = $this->parameters;
		foreach($this->parameterFilters as $parameterFilter) {
			$parameters = $parameterFilter->filter($parameters);
		}

		return $parameters;
	}

	protected function getPlains()
	{
		// filter parameters
		$parameters = $this->parameters;
		foreach($this->parameterFilters as $parameterFilter) {
			$parameters = $parameterFilter->filter($parameters);
		}

		return $parameters['paymentAmount'] + $parameters['currencyCode'] + $parameters['shipBeforeDate'] +
			 $parameters['merchantReference'] + $parameters['skinCode'] + $parameters['merchantAccount'] +
			 $parameters['sessionValidity'] + $parameters['shopperEmail'] + $parameters['shopperReference'] +
			 $parameters['recurringContract'] + $parameters['allowedMethods'] + $parameters['blockedMethods'] +
			 $parameters['shopperStatement'] + $parameters['merchantReturnData'] +
			 $parameters['billingAddressType'] + $parameters['deliveryAddressType'] + $parameters['offset'];
	}

	protected function getPlainsBilling()
	{
		// filter parameters
		$parameters = $this->parameters;
		foreach($this->parameterFilters as $parameterFilter) {
			$parameters = $parameterFilter->filter($parameters);
		}

		return $parameters['billingAddress.street'] + $parameters['billingAddress.houseNumberOrName'] +
			 $parameters['billingAddress.city'] + $parameters['billingAddress.postalCode'] +
			 $parameters['billingAddress.stateOrProvince'] + $parameters['billingAddress.country'];
	}

	protected function getPlainsShopper()
	{
		// filter parameters
		$parameters = $this->parameters;
		foreach($this->parameterFilters as $parameterFilter) {
			$parameters = $parameterFilter->filter($parameters);
		}

		return $parameters['shopper.firstName'] + $parameters['shopper.gender'] +
			 $parameters['shopper.dateOfBirthDayOfMonth'] + $parameters['shopper.dateOfBirthMonth'] +
			 $parameters['shopper.dateOfBirthYear'] + $parameters['shopper.telephoneNumber'] +
			 $parameters['shopper.shopperType'];
	}

	/** @return PaymentRequest */
	public static function createFromArray($secret="", array $parameters)
	{
		$instance = new static($secret);

		foreach($this->parameterFilters as $parameterFilter) {
			$parameters = $parameterFilter->filter($parameters);
		}

		foreach($parameters as $key => $value)
		{
			$instance->{"set$key"}($value); 
		}

		return $instance; 
	}

	protected function validateUri($uri)
	{
		if(!filter_var($uri, FILTER_VALIDATE_URL)) {
			throw new InvalidArgumentException("Uri is not valid");
		}
		if(strlen($uri) > 200) {
			throw new InvalidArgumentException("Uri is too long");
		}
	}

	protected function calculateSignature($plaintext) {
		if (function_exists('hash_hmac')) {
			return base64_encode(hash_hmac('sha1', $plaintext, $this->secret, true));
		} else {
			return base64_encode(pack('H*',$this->hmacsha1($this->secret, $plaintext)));
		}
	}

	protected function hmacsha1($key,$data) {
	    $blocksize=64;
	    $hashfunc='sha1';
	    if (strlen($key)>$blocksize)
	        $key=pack('H*', $hashfunc($key));
	    $key=str_pad($key,$blocksize,chr(0x00));
	    $ipad=str_repeat(chr(0x36),$blocksize);
	    $opad=str_repeat(chr(0x5c),$blocksize);
	    $hmac = pack(
	                'H*',$hashfunc(
	                    ($key^$opad).pack(
	                        'H*',$hashfunc(
	                            ($key^$ipad).$data
	                        )
	                    )
	                )
	            );
	    return bin2hex($hmac);
	}
}
