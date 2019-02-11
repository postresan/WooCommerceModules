<?php
/**
 * author: ramin bazghandi
 */
class postresan_helper
{
    private $username;
    private $password;
    const METHOD_POST   = 'post';
    const METHOD_GET    = 'get';
    const BUY_COD       = 1;
    const BUY_ONLINE    = 2;
    const DELIVERY_PISHTAZ      = 2;
    const DELIVERY_SEFARESHI    = 1;
    /**
     * list of errors
     *
     * @var array
     */
    private $errors = array();
    /**
     * @param string $webserviceUrl
     * @param string $apiKey
     */
    public function __construct($username,$password)
    {
        $this->url = "http://service.postresan.com/RestCod.svc/";
        $this->username = $username;
        $this->password = $password;
    }


    # فراخوانی وب سرویس ثبت سفارش
    public function registerOrder($des_city,$name,$family,$ordersdetail,$mobile,$phone,$email,$address,$postCode,$buy_type,$send_type,$pm,$ip,$discount)
    {
      $params      = array(
          "DestinationCityId" => $des_city,
          "SendTypeId" => $send_type,
          "OrderDetails" => $ordersdetail,
          "PaymentTypeId" => $buy_type,
          "OrderDetails" => $ordersdetail,
          "FirstName" => $name,
          "LastName" => $family,
          "Email" => $email,
          "Mobile" => $mobile,
          "Telephone" => $phone,
          "PostalCode" => $postCode,
          "Address" => $address,
          "Message" => $pm,
          "IpAddress" => $ip,
          "Discount" => $discount
      );
        return $this->call('CreateOrder',$params);
    }

    # فراخوانی وب سرویس محاسبه هزینه پست
    public function getPrices($des_city,$price,$weight,$buy_type,$delivery_type)
    {
      $params = array(
      "CityId" =>  $des_city,
      "SendTypeId" =>  $delivery_type,
      "PaymentTypeId" =>  $buy_type,
      "TotalPrice" =>  $price,
      "Weight" =>  $weight,
      "ExactPrice" =>  0
    );
        return $this->call('CalculatePrice',$params);
    }

    private function call($url,$params,$methodType = postresan_helper::METHOD_POST)
    {
        // flush error list
        $url = $this->url . $url;
        $this->errors = array();
        $credential  = array(
            "Username" => $this->username,
            "Password" => $this->password
        );
        $params['CredentialRequest'] = $credential;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json; charset=UTF-8"));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        $result= curl_exec($curl);
        curl_close($curl);
        $result = json_decode($result,true);
        return $result;

    }
    /**
     * check valid json
     *
     * @param $string
     * @return bool
     */
    private function isJson($string) {
        return ((is_string($string) && (is_object(json_decode($string)) || is_array(json_decode($string))))) ? true : false;//PHP Version 5.2.17 server
    }
    /**
     * parse webservice response
     *
     * @param array $response
     * @return bool
     * @throws FrotelResponseException
     * @throws FrotelWebserviceException
     */
    private function parseResponse($response)
    {
        if (!isset($response['code'],$response['message'],$response['result']))
            throw new FrotelResponseException('پاسخ دریافتی از سرور معتبر نیست.');
        if ($response['code'] == 0)
            return $response['result'];
        $this->errors[] = $response['message'];
        throw new FrotelWebserviceException($response['message']);
    }
    /**
     * get list of errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
class postresanResponseException extends Exception{}
class postresanWebserviceException extends Exception{}
