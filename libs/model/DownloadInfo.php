<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Razvan G Nutu
 * Date: 1/30/13
 * Time: 10:08 AM
 * To change this template use File | Settings | File Templates.
 *
 * @brief This will store additional information for a Downloadable product in a order
 */
class DownloadInfo extends BaseItem
{
    private $order_srl;
    private $product_srl;

    private $token;
    private $ip = "";
    private $validity_date;
    private $counter = 0;


    public function __construct($order_srl, $product_srl){
        if (!isset($order_srl) || !isset($product_srl)){
            throw new ShopException("Error while creating instance of ".get_class($this));
        }
        $this->order_srl = $order_srl;
        $this->product_srl = $product_srl;
        $this->token = strtoupper(uniqid());
        $this->validity_date = $this->generateValidityDate();
    }

    /**
     * Constructor-like helper to obtain an instance from an array
     * @param $data
     * @return DownloadInfo
     */
    public static function fromArray($data){
        $instance = new self($data['order_srl'], $data['product_srl']);
        $instance->token = isset($data['token']) ? $data['token'] : null;
        $instance->ip = isset($data['ip']) ? $data['ip'] : $instance->ip;
        $instance->validity_date = isset($data['validity_date']) ?
            $data['validity_date'] : $instance->validity_date;
        $instance->counter = isset($data['counter']) ?
            $data['counter'] : $instance->counter;
        return $instance;
    }

    public function getOrderSrl()
    {
        return $this->order_srl;
    }

    public function getProductSrl()
    {
        return $this->product_srl;
    }

    public function setCounter($counter){
        $this->counter = $counter;
    }

    public function getCounter(){
        return $this->counter;
    }

    /**
     * @brief used to determine genuine downloads by IP;
     * Not used at this moment.
     * @param $ip
     * @throws ShopException
     */
    public function setIp($ip){
        if (filter_var($ip, FILTER_VALIDATE_IP)){
            $this->ip = $ip;
        } else{
            throw new ShopException("Invalid IP");
        }
    }

    /**
     * @brief used to determine genuine downloads by IP;
     * Not used at this moment.
     * @return null
     */
    public function getIp(){
        return $this->ip;
    }

    public function getToken(){
        return $this->token;
    }

    /**
     * @brief used to filter genuine downloads until a certain date only;
     * Not used at this moment
     * @return the date until download operation can be performed
     */
    public function getValidityDate(){
        return $this->validity_date;
    }

    public function reset(){
        $this->ip = "";
        $this->counter = 0;
        $this->validity_date = $this->generateValidityDate();
    }

    private function generateValidityDate(){
        $currentDate = date_create();
        date_add($currentDate, date_interval_create_from_date_string('1 day'));
        return date_format($currentDate, "YmdHms");
    }
}
