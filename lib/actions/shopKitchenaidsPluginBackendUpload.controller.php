<?php

$path_data_parser = wa()->getAppPath('plugins/kitchenaids/lib/classes/dataParser.class.php', 'shop');
$path_curl = wa()->getAppPath('plugins/kitchenaids/lib/classes/curlRequest.class.php', 'shop');
$path_simple_html_dom = wa()->getAppPath('plugins/kitchenaids/lib/classes/vender/simple_html_dom.php', 'shop');
require_once($path_data_parser);
require_once($path_curl);
require_once($path_simple_html_dom);

class shopKitchenaidsPluginBackendUploadController extends waLongActionController {

    protected $plugin_id = array('shop', 'kitchenaids');
    protected $steps = array(
        'getCategories' => 'Получение категорий',
        'getCategoryDescription' => 'Получение описания категорий',
        'getCategoryProducts' => 'Получение товаров из категорий',
        'getProducts' => 'Обработка товаров',
    );

    public function getNextStep($current_key) {
        $array_keys = array_keys($this->steps);
        $current_key_index = array_search($current_key, $array_keys);
        if (isset($array_keys[$current_key_index + 1])) {
            return $array_keys[$current_key_index + 1];
        } else {
            return false;
        }
    }

    public function execute() {
        try {
            parent::execute();
        } catch (waException $ex) {
            if ($ex->getCode() == '302') {
                echo json_encode(array('warning' => $ex->getMessage()));
            } else {
                echo json_encode(array('error' => $ex->getMessage()));
            }
        }
    }

    protected function finish($filename) {
        $this->info();
        if ($this->getRequest()->post('cleanup')) {
            return true;
        }
        return false;
    }

    protected function init() {

        $db_model = new waModel();
        $db_model->query("TRUNCATE TABLE `shop_kitchenaids_category`");
        $db_model->query("TRUNCATE TABLE `shop_kitchenaids_product`");
        $db_model->query("TRUNCATE TABLE `shop_kitchenaids_feature`");
        $db_model->query("TRUNCATE TABLE `shop_kitchenaids_image`");
        $db_model->query("TRUNCATE TABLE `shop_kitchenaids_categories`");
        

        $this->data['count'] = 0;
        $this->data['offset'] = 0;
        $this->data['step'] = array_shift(array_keys($this->steps));
        $this->data['timestamp'] = time();
    }

    protected function isDone() {
        return $this->data['step'] == 'getProducts' && $this->data['offset'] >= $this->data['count'] - 1;
    }

    protected function step() {
        switch ($this->data['step']) {
            case 'getCategories':
                $this->getCategories();
                break;
            case 'getCategoryDescription':
                $this->getCategoryDescription();
                break;
            case 'getCategoryProducts':
                $this->getCategoryProducts();
                break;
            case 'getProducts':
                $this->getProducts();
                break;
        }
    }

    protected function info() {
        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
        }
        $response = array(
            'time' => sprintf('%d:%02d:%02d', floor($interval / 3600), floor($interval / 60) % 60, $interval % 60),
            'processId' => $this->processId,
            'progress' => 0.0,
            'ready' => $this->isDone(),
            'offset' => $this->data['offset'],
            'count' => $this->data['count'],
            'step' => $this->steps[$this->data['step']] . ' - ' . $this->data['offset'] . ' из ' . $this->data['count'] .
            ($this->data['step'] == 'getCategoryProducts' ? ' Загружено товаров - ' . $this->data['products_count'] : ''),
        );
        if ($this->data['count']) {
            $response['progress'] = ($this->data['offset'] / $this->data['count']) * 100;
        }

        $response['progress'] = sprintf('%0.3f%%', $response['progress']);

        if ($this->getRequest()->post('cleanup')) {
            $response['report'] = $this->report();
        }

        echo json_encode($response);
    }

    protected function report() {
        $report = '<div class="successmsg"><i class="icon16 yes"></i> ' .
                _w('Updated %d product image.', 'Updated %d product images.', $this->data['count']) .
                ' ' .
                _w('%d product affected.', '%d products affected.', $this->data['count']);

        $interval = 0;
        if (!empty($this->data['timestamp'])) {
            $interval = time() - $this->data['timestamp'];
            $interval = sprintf(_w('%02d hr %02d min %02d sec'), floor($interval / 3600), floor($interval / 60) % 60, $interval % 60);
            $report .= ' ' . sprintf(_w('(total time: %s)'), $interval);
        }

        $report .= '&nbsp;</div>';

        return $report;
    }

    private function error($message) {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path . '/shop/importyml.log');
        waLog::log($message, 'shop/importyml.log');
    }

    public function getCategories() {
        $parser = new dataParser();
        $category_model = new shopKitchenaidsPluginCategoryModel();
        $categories = $parser->getCategories();
        foreach ($categories as &$category) {
            $category_model->insert($category);
        }
        unset($category);

        $categories = $category_model->getAll();
        foreach ($categories as $category) {

            preg_match('@^http:\/\/.+\/(?P<last>.+)$@', $category['url'], $match);
            if (!empty($match['last'])) {
                $parent_url = str_replace($match['last'], '', $category['url']);
                $parent = $category_model->getByField('url', $parent_url);
                if ($parent) {
                    $category_model->updateById($category['id'], array('parent_id' => $parent['id']));
                }
            }
        }

        $this->data['step'] = $this->getNextStep($this->data['step']);
    }

    public function getCategoryDescription() {
        $parser = new dataParser();
        $category_model = new shopKitchenaidsPluginCategoryModel();
        $categories = $category_model->getAll();
        $this->data['count'] = count($categories);
        if (!empty($this->data['offset'])) {
            $index = $this->data['offset'];
        } else {
            $index = 0;
            $this->data['offset'] = 0;
        }
        $chunk_size = 3;
        $i = 0;

        while ($index + $i < $this->data['count'] && $i <= $chunk_size) {



            $category = $categories[$index + $i];
            $description = $parser->getCategoryDescription($category['url']);
            $category_model->updateById($category['id'], array('description' => $description));

            if ($this->data['offset'] >= $this->data['count'] - 1) {
                $this->data['offset'] = 0;
                $this->data['step'] = $this->getNextStep($this->data['step']);
                break;
            }
            $this->data['offset'] = $index + $i + 1;
            $i++;
        }
    }

    public function getCategoryProducts() {
        $parser = new dataParser();
        $category_model = new shopKitchenaidsPluginCategoryModel();
        $product_model = new shopKitchenaidsPluginProductModel();
        $categories_model = new shopKitchenaidsPluginCategoriesModel();

        $products = $product_model->getAll();
        $this->data['products_count'] = count($products);
        unset($products);


        $categories = $category_model->getAll();
        $this->data['count'] = count($categories);
        if (!empty($this->data['offset'])) {
            $index = $this->data['offset'];
        } else {
            $index = 0;
            $this->data['offset'] = 0;
        }
        $chunk_size = 3;
        $i = 0;

        while ($index + $i < $this->data['count'] && $i <= $chunk_size) {

            $category = $categories[$index + $i];

            $products = $parser->getCategoryProducts($category['url']);
            foreach ($products as $product) {
                $product['category_id'] = $category['id'];

                if (!($exist = $product_model->getByField('url', $product['url']))) {

                    $id = $product_model->insert($product);
                    $data = array(
                        'category_id' => $category['id'],
                        'product_id' => $id,
                    );
                    $categories_model->insert($data);
                } else {
                    $data = array(
                        'category_id' => $category['id'],
                        'product_id' => $exist['id'],
                    );
                    $categories_model->insert($data);
                }
            }


            if ($this->data['offset'] >= $this->data['count'] - 1) {
                $this->data['offset'] = 0;
                $this->data['step'] = $this->getNextStep($this->data['step']);
                break;
            }
            $this->data['offset'] = $index + $i + 1;
            $i++;
        }
    }

    public function getProducts() {
        $parser = new dataParser();
        $product_model = new shopKitchenaidsPluginProductModel();
        $feature_model = new shopKitchenaidsPluginFeatureModel();
        $image_model = new shopKitchenaidsPluginImageModel();

        $products = $product_model->getAll();

        $this->data['count'] = count($products);
        if (!empty($this->data['offset'])) {
            $index = $this->data['offset'];
        } else {
            $index = 0;
            $this->data['offset'] = 0;
        }
        $chunk_size = 3;
        $i = 0;

        while ($index + $i < $this->data['count'] && $i <= $chunk_size) {

            $p = $products[$index + $i];
            $url = html_entity_decode($p['url']);
            $product = $parser->getProduct($url);
            $product['id'] = $p['id'];
            $product_model->updateByField('url', $p['url'], $product);
            foreach ($product['features'] as $feature) {
                $feature['product_id'] = $p['id'];
                $feature_model->insert($feature);
            }
            foreach ($product['images'] as $image) {
                $image_model->insert(array('product_id' => $p['id'], 'image' => $image));
            }

            if ($this->data['offset'] >= $this->data['count'] - 1) {
                break;
            }
            $this->data['offset'] = $index + $i + 1;
            $i++;
        }
    }

}
