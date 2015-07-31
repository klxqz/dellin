<?php

class dellinShipping extends waShipping {

    protected $dellin;
    protected $cities = array();

    protected function initControls() {

        parent::initControls();
    }

    protected function init() {
        $this->cities = include($this->path . '/lib/config/data/cities.php');
        parent::init();
    }

    protected function sendRequest($url, $data = null, $method = 'POST') {
        if (!extension_loaded('curl') || !function_exists('curl_init')) {
            throw new waException('PHP расширение cURL не доступно');
        }

        if (!($ch = curl_init())) {
            throw new waException('curl init error');
        }

        if (curl_errno($ch) != 0) {
            throw new waException('Ошибка инициализации curl: ' . curl_errno($ch));
        }

        $data = json_encode($data);
        $headers = array("Content-Type: application/json");

        @curl_setopt($ch, CURLOPT_URL, $url);
        @curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($method == 'POST') {
            @curl_setopt($ch, CURLOPT_POST, 1);
            @curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $response = @curl_exec($ch);
        $app_error = null;
        if (curl_errno($ch) != 0) {
            $app_error = 'Ошибка curl: ' . curl_error($ch);
        }
        curl_close($ch);
        if ($app_error) {
            throw new waException($app_error);
        }
        if (empty($response)) {
            throw new waException('Пустой ответ от сервера');
        }

        $json = json_decode($response, true);

        $return = json_decode($response, true);
        if (!is_array($return)) {
            return $response;
        } else {
            return $return;
        }
    }

    protected function mb_ucfirst($str, $encoding = 'UTF-8') {
        $str = mb_ereg_replace('^[\ ]+', '', $str);
        $str = mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding) .
                mb_substr($str, 1, mb_strlen($str), $encoding);
        return $str;
    }

    public function calculate() {

        if (empty($this->appKey)) {
            return 'Укажите «Ключ приложения».';
        }

        $weight = $this->getTotalWeight();

        $derival_city = $this->mb_ucfirst(trim($this->derivalPoint));
        if (empty($this->cities[$derival_city])) {
            return 'Неверный город отправки груза';
        }
        $derivalPoint = $this->cities[$derival_city];


        $address = $this->getAddress();
        $address_city = $this->mb_ucfirst(trim($address['city']));
        $arrivalPoint = $this->cities[$address_city];

        if (empty($arrivalPoint['code'])) {
            return 'Неверный город получения груза';
        }


        $data = array(
            'appKey' => $this->appKey,
            'derivalPoint' => $derivalPoint['code'],
            'arrivalPoint' => $arrivalPoint['code'],
            'sizedVolume' => $this->sizedVolume,
            'sizedWeight' => $weight,
        );


        $result = $this->sendRequest('http://api.dellin.ru/v1/public/calculator.json', $data);

        if (!empty($result['errors'])) {
            if (is_array($result['errors'])) {
                foreach ($result['errors'] as $key => $val) {
                    $result['errors'][$key] = $key . ' ' . $val;
                }
                return implode(', ', $result['errors']);
            } else {
                return $result['errors'];
            }
        }
        //print_r($result);

        return array(
            'delivery' => array(
                'est_delivery' => $result['time']['nominative'],
                'currency' => 'RUB',
                'rate' => $result['price'],
                'description' => null,
            ),
        );
    }

    public function allowedCurrency() {
        return 'RUB';
    }

    public function allowedWeightUnit() {
        return 'kg';
    }

    public function requestedAddressFields() {

        return array(
            'city' => array('cost' => true),
        );
    }

}
