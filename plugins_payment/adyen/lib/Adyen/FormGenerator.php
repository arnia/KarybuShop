<?php
namespace Adyen;

use Adyen\PaymentRequest;
use InvalidArgumentException;

class FormGenerator {

	private $paymentRequest;

	private $showSubmitButton = true;

	private $formName = 'adyen';

	public function render(PaymentRequest $paymentRequest)
	{
		$this->paymentRequest = $paymentRequest;
		ob_start();
		include __DIR__.'/template/simpleForm.php';
		return ob_get_clean();
	}

	protected function getParameters()
	{
		return $this->paymentRequest->toArray();
	}

	protected function getAdyenUri()
	{
		return $this->paymentRequest->getAdyenUri();
	}

	public function showSubmitButton($bool = true)
	{
		$this->showSubmitButton = $bool;
	}

	public function setFormName($formName)
	{
		$this->formName = $formName;
	}

}

?>
