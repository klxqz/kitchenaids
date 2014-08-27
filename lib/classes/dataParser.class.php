<?php

class dataParser {

    protected $curl;

    public function __construct() {
        $this->curl = new curlRequest();
    }

    public function getCategories() {
        $url = 'http://kitchenaids.ru/';
        $response = $this->curl->get($url);
        if ($response['http_code'] != 200) {
            throw new Exception('Ошибка CURL запроса');
        }
        $html = str_get_html($response['html_body']);
        $links = $html->find('#column-left .box-category ul li a');
        $categories = array();
        foreach ($links as $link) {
            $categories[] = array('url' => $link->href, 'name' => $link->innertext);
        }
        $html->__destruct();
        return $categories;
    }

    public function getCategoryDescription($url) {
        $response = $this->curl->get($url);
        if ($response['http_code'] != 200) {
            throw new Exception('Ошибка CURL запроса');
        }
        $html = str_get_html($response['html_body']);
        if ($description = $html->find('#content .category-info', 0)) {
            return $description->innertext;
        }
        $html->__destruct();
        return false;
    }

    public function getCategoryProducts($url) {
        $response = $this->curl->get($url);
        if ($response['http_code'] != 200) {
            throw new Exception('Ошибка CURL запроса');
        }
        $html = str_get_html($response['html_body']);

        $elements = $html->find('#content .product-list .grid-element');
        $products = array();
        if ($elements) {
            foreach ($elements as $element) {
                $product = array();
                $name = $element->find('.name a', 0);
                $product['url'] = $name->href;
                $product['name'] = $name->innertext;

                $product['summary'] = $element->find('.description', 0)->innertext;
                $price = trim($element->find('.price', 0)->innertext);
                $price = str_replace(' ', '', $price);
                $price = str_replace('р.', '', $price);
                $product['price'] = $price;

                $products[] = $product;
            }
            $html->__destruct();
            return $products;
        }
        $html->__destruct();
        return false;
    }

    public function getProduct($url) {
        $response = $this->curl->get($url);
        if ($response['http_code'] != 200) {
            throw new Exception('Ошибка CURL запроса');
        }
        $html = str_get_html($response['html_body']);

        $product = array();
        $product_page = $html->find('#content', 0);

        if ($product_page) {
            if ($html->find('head title', 0)) {
                $product['title'] = $html->find('head title', 0)->innertext;
            }
            if ($html->find('head meta[name=keywords]', 0)) {
                $product['meta_keywords'] = $html->find('head meta[name=keywords]', 0)->content;
            }
            if ($html->find('head meta[name=description]', 0)) {
                $product['meta_description'] = $html->find('head meta[name=description]', 0)->content;
            }

            $product['name'] = @$product_page->find('.product-info h1', 0)->innertext;
            $stock = @$product_page->find('.cart-block .stock', 0)->innertext;
            $sku = preg_replace("'<[^>]*?>.*?</[^>]*?>'si", "", $stock);
            $product['sku'] = trim($sku);
            $product['description'] = @$product_page->find('#description', 0)->innertext;
            $video = $product_page->find('#product-video iframe', 0);
            if ($video) {
                $product['video'] = $video->src;
            }

            $instruction = $product_page->find('#quick-start a', 0);
            if ($instruction) {
                $product['instruction'] = $instruction->href;
            }


            $features = array();
            $harakter = $product_page->find('#harakter div.atr');
            if ($harakter) {
                foreach ($harakter as $h) {
                    $feature = array();
                    $feature['name'] = trim($h->find('span', 0)->innertext);
                    $feature['value'] = trim($h->find('span', 1)->innertext);
                    $features[] = $feature;
                }
            }
            $product['features'] = $features;

            $images = array();
            $MagicThumb = $product_page->find('.MagicToolboxSelectorsContainer a');
            if ($MagicThumb) {
                foreach ($MagicThumb as $thumb) {
                    $images[] = $thumb->href;
                }
            } else {
                $img_link = $product_page->find('a.MagicZoomPlus', 0);
                if($img_link){
                    $images[] = $img_link->href;
                }
            }
            $product['images'] = $images;


            return $product;
        }

        $html->__destruct();
        return false;
    }

}
