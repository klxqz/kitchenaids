<?php

class curlRequest {

    public function __construct() {
        if (!extension_loaded('curl') || !function_exists('curl_init')) {
            throw new waException('PHP расширение cURL не доступно');
        }
    }

    protected function curlInit() {
        if (!($ch = curl_init())) {
            throw new waException('curl init error');
        }
        if (curl_errno($ch) != 0) {
            throw new waException('Ошибка инициализации curl: ' . curl_errno($ch));
        }
        return $ch;
    }

    public function post($url, $postdata = array()) {
        $ch = $this->curlInit();
        $data = array();
        foreach ($postdata as $name => $value) {
            $data[] = "$name=$value";
        }

        $post = implode('&', $data);

        @curl_setopt($ch, CURLOPT_URL, $url);
        @curl_setopt($ch, CURLOPT_POST, 1);
        @curl_setopt($ch, CURLOPT_HEADER, 1);
        @curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        @curl_setopt($ch, CURLE_OPERATION_TIMEOUTED, 120);
        

        $html_body = @curl_exec($ch);
        $app_error = null;
        if (curl_errno($ch) != 0) {
            $app_error = 'Ошибка curl: ' . curl_errno($ch);
        }
        $info = curl_getinfo($ch);

        curl_close($ch);
        if ($app_error) {
            throw new waException($app_error);
        }
        $info['html_body'] = $html_body;

        return $info;
    }

    public function get($url, $getdata = array()) {
        $ch = $this->curlInit();
        $data = array();
        
        @curl_setopt($ch, CURLOPT_URL, $url);
        @curl_setopt($ch, CURLOPT_POST, 0);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        @curl_setopt($ch, CURLE_OPERATION_TIMEOUTED, 120);
        if(isset($getdata['cookie'])) {
            curl_setopt($ch, CURLOPT_COOKIE, implode(';', array_map('trim',$getdata['cookie'])));
        }

        $html_body = @curl_exec($ch);
        $app_error = null;
        if (curl_errno($ch) != 0) {
            $app_error = 'Ошибка curl: ' . curl_errno($ch);
        }
        $info = curl_getinfo($ch);

        curl_close($ch);
        if ($app_error) {
            throw new waException($app_error);
        }
        $info['html_body'] = $html_body;

        return $info;
    }

}
