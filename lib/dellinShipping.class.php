<?php

class dellinShipping extends waShipping {

    protected $dellin;

    protected function initControls() {

        parent::initControls();
    }

    protected function install() {

        $file_db = $this->path . '/lib/config/db.php';
        if (file_exists($file_db)) {
            $schema = include($file_db);
            $model = new waModel();
            $model->createSchema($schema);
        }
        // check install.php
        $file = $this->path . '/lib/config/install.php';
        if (file_exists($file)) {
            $app_id = $this->app_id;
            include($file);
            // clear db scheme cache, see waModel::getMetadata
            try {
                // remove files
                $path = waConfig::get('wa_path_cache') . '/db/';
                waFiles::delete($path, true);
            } catch (waException $e) {
                waLog::log($e->__toString());
            }
            // clear runtime cache
            waRuntimeCache::clearAll();
        }
    }

    protected function prepareData($data) {
        $result = '';
        $filename = $this->path . '/config.php';
        $f = fopen($filename, 'w+');
        fwrite($f, "array(\r\n");
        foreach ($data as $key => $item) {
            fwrite($f, "\t'" . $item['city'] . "' => array('code' => '" . $item['code'] . "'),\r\n");
        }
        fwrite($f, ");\r\n");
        fclose($f);
    }

    protected function init() {
        $autoload = waAutoload::getInstance();
        $autoload->add('dellinPluginModel', "wa-plugins/shipping/dellin/lib/models/dellinPlugin.model.php");

        try {
            $model = new waModel();
            $model->query("SELECT * FROM `dellinplugin` WHERE 0");
        } catch (waDbException $e) {
            $this->install();
        }

        $model = new dellinPluginModel();
        $data = $model->getAll();
        $this->prepareData($data);


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

    public function calculate() {

        if (empty($this->appKey)) {
            return 'Укажите «Ключ приложения».';
        }

        $weight = $this->getTotalWeight();
        $model = new dellinPluginModel();
        $derivalPoint = $model->getByField('city', $this->derivalPoint);

        if (empty($derivalPoint['code'])) {
            return 'Неверный город отправки груза';
        }


        $address = $this->getAddress();
        $arrivalPoint = $model->getByField('city', $address['city']);

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
