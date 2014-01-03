<?php

namespace Adyen;

use InvalidArgumentException;

class PaymentResponse
{
	/** @var string */
	const SIGNATURE_FIELD = 'merchantSig'; 

	/**
	 * Available Adyen parameters
	 * @var array
	 */
	private $adyenFields = array('authResult', 'pspReference', 'merchantReference', 'skinCode', 'paymentAmount',
								 'currencyCode', 'additionalAmount', 'customPaymentMethod', 'merchantReturnData');

	/**
	 * @var array
	 */
	private $parameters;

	/**
	 * @var string
	 */
	private $signature;

	/**
	 * @param array $httpRequest Typically $_REQUEST
	 * @throws \InvalidArgumentException
	 */
	public function __construct(array $httpRequest)
	{
		// use lowercase internally
		//$httpRequest = array_change_key_case($httpRequest, CASE_UPPER);

		// set signature
		$this->signature = $this->extractSignature($httpRequest);

		// filter request for Adyen parameters
		$this->parameters = $this->filterRequestParameters($httpRequest);
	}

	/**
	 * Filter http request parameters
	 * @param array $requestParameters
	 */
	private function filterRequestParameters(array $httpRequest)
	{
		// filter request for Adyen parameters
		return array_intersect_key($httpRequest, array_flip($this->adyenFields));
	}

	/**
	 * Set Adyen signature
	 * @param array $parameters
	 * @throws \InvalidArgumentException
	 */
	private function extractSignature(array $parameters)
	{
		if(!array_key_exists(self::SIGNATURE_FIELD, $parameters) || $parameters[self::SIGNATURE_FIELD] == '') {
			throw new InvalidArgumentException('signature parameter not present in parameters.');
		}
		return $parameters[self::SIGNATURE_FIELD];
	}

	/**
	 * Checks if the response is valid
	 * @return bool
	 */
	public function isValid($secret)
	{
		return $this->calculateSignature($this->getPlains(), $secret) == $this->signature;
	}

	/**
	 * Retrieves a response parameter
	 * @param string $param
	 * @throws \InvalidArgumentException
	 */
	public function getParam($key)
	{
		if(method_exists($this, 'get'.$key)) {
			return $this->{'get'.$key}();
		}

		// always use uppercase
		$key = strtoupper($key);

		if(!array_key_exists($key, $this->parameters)) {
			throw new InvalidArgumentException('Parameter ' . $key . ' does not exist.');
		}

		return $this->parameters[$key];
	}

	/**
	 * @return int Amount in cents
	 */
	public function getAmount()
	{
		$value = trim($this->parameters['paymentAmount']);

		$withoutDecimals = '#^\d*$#';
		$oneDecimal = '#^\d*\.\d$#';
		$twoDecimals = '#^\d*\.\d\d$#';

		if(preg_match($withoutDecimals, $value)) {
			return (int) ($value.'00');
		}

		if(preg_match($oneDecimal, $value)) {
			return (int) (str_replace('.', '', $value).'0');
		}

		if(preg_match($twoDecimals, $value)) {
			return (int) (str_replace('.', '', $value));
		}

		throw new \InvalidArgumentException("Not a valid currency amount");
	}

	protected function getPlains()
	{
		return $parameters['authResult'] + $parameters['pspReference'] + $parameters['merchantReference'] +
			 $parameters['skinCode'] + $parameters['merchantReturnData'];
	}

	protected function calculateSignature($plaintext, $secret) {
		if (function_exists('hash_hmac')) {
			return base64_encode(hash_hmac('sha1', $plaintext, $secret, true));
		} else {
			return base64_encode(pack('H*',$this->hmacsha1($secret, $plaintext)));
		}
	}

	public function isSuccessful()
	{
		// @todo use constants
		return in_array($this->getParam('authResult'), array(5, 9));
	}

	public function toArray()
	{
		return $this->parameters + array(self::SIGNATURE_FIELD => $this->signature);
	}
}
