<?php

$path_data_parser = wa()->getAppPath('plugins/kitchenaids/lib/classes/dataParser.class.php', 'shop');
$path_curl = wa()->getAppPath('plugins/kitchenaids/lib/classes/curlRequest.class.php', 'shop');
$path_simple_html_dom = wa()->getAppPath('plugins/kitchenaids/lib/classes/vender/simple_html_dom.php', 'shop');
require_once($path_data_parser);
require_once($path_curl);
require_once($path_simple_html_dom);

class shopKitchenaidsPluginSettingsAction extends waViewAction {

    public function execute() {

        $db_model = new waModel();
        $db_model->query("TRUNCATE TABLE `shop_kitchenaids_category`");
        $db_model->query("TRUNCATE TABLE `shop_kitchenaids_product`");
        $db_model->query("TRUNCATE TABLE `shop_kitchenaids_feature`");




        $this->getCategories();
        $this->getCategoryDescription();
        $this->getCategoryProducts();

        $this->getProducts();









        exit('dsdsd');
    }

    public function getCategories() {
        $parser = new dataParser();
        $category_model = new shopKitchenaidsPluginCategoryModel();
        $categories = $parser->getCategories();
        foreach ($categories as &$category) {
            $id = $category_model->insert($category);
        }
        unset($category);
    }

    public function getCategoryDescription() {
        $parser = new dataParser();
        $category_model = new shopKitchenaidsPluginCategoryModel();
        $categories = $category_model->getAll();
        foreach ($categories as $category) {
            $description = $parser->getCategoryDescription($category['url']);
            $category_model->updateById($category['id'], array('description' => $description));
        }
    }

    public function getCategoryProducts() {
        $parser = new dataParser();
        $category_model = new shopKitchenaidsPluginCategoryModel();
        $product_model = new shopKitchenaidsPluginProductModel();

        $categories = $category_model->getAll();
        foreach ($categories as $category) {
            $products = $parser->getCategoryProducts($category['url']);
            foreach ($products as $product) {
                $product['category_id'] = $category['id'];
                if (!$product_model->getByField('url', $product['url'])) {
                    $product_model->insert($product);
                }
            }
        }
    }

    public function getProducts() {
        $parser = new dataParser();
        $product_model = new shopKitchenaidsPluginProductModel();
        $feature_model = new shopKitchenaidsPluginFeatureModel();
        $image_model = new shopKitchenaidsPluginImageModel();

        $products = $product_model->getAll();
        
        foreach ($products as $p) {
            $product = $parser->getProduct($p['url']);
            $product['id'] = $p['id'];
            $product_model->updateByField('url', $p['url'], $product);
            foreach ($product['features'] as $feature) {
                $feature['product_id'] = $p['id'];
                $feature_model->insert($feature);
            }
            foreach ($product['images'] as $image) {
                $image['product_id'] = $p['id'];
                $image_model->insert($image);
            }
        }
    }

}
