<?php
namespace Adyen\Notifications;

class RequestItem {
    public $amount;
    public $eventCode;
    public $eventDate;
    public $merchantAccountCode;
    public $merchantReference;
    public $originalReference;
    public $pspReference;
    public $reason;
    public $success;
    public $paymentMethod;
    public $operations;
    public $additionalData;
}
