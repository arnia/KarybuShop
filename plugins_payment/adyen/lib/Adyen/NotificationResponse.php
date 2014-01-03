<?php

namespace Adyen;

use InvalidArgumentException;

class NotificationResponse
{
	/**
	 * Available Adyen parameters
	 * @var array
	 */
	private $adyenFields = array('live', 'eventCode', 'pspReference', 'originalReference',
                'merchantReference', 'merchantAccountCode', 'eventDate', 'success', 'paymentMethod',
                'operations', 'reason', 'amount', 'currency', 'value');

	/**
	 * @var array
	 */
	private $parameters;

	/**
	 * @param array $httpRequest Typically $_REQUEST
	 * @throws \InvalidArgumentException
	 */
	public function __construct(array $httpRequest)
	{
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
		$value = trim($this->parameters['amount']);

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

    public function getCurrency()
    {
        return trim($this->parameters['currency']);
    }

	public function toArray()
	{
		return $this->parameters;
	}
}
