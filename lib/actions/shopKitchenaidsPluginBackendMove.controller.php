<?php

class shopKitchenaidsPluginBackendMoveController extends waLongActionController {

    protected $steps = array(
        'categories' => 'Импорт категорий',
        'products' => 'Импорт товаров',
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

        $this->data['count'] = 0;
        $this->data['offset'] = 0;
        $this->data['step'] = array_shift(array_keys($this->steps));
        $this->data['timestamp'] = time();
    }

    protected function isDone() {
        return $this->data['step'] == 'products' && $this->data['offset'] >= $this->data['count'] - 1 && $this->data['offset'] > 0;
    }

    protected function step() {

        switch ($this->data['step']) {
            case 'categories':
                $this->categories();
                break;
            case 'products':
                $this->products();
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

    public function categories() {
        $category_model = new shopKitchenaidsPluginCategoryModel();
        $categories = $category_model->getAll();
        foreach ($categories as $category) {
            unset($category['id']);
            unset($category['url']);
            $this->saveCategorySettings(null, $category);
        }
        $this->data['step'] = $this->getNextStep($this->data['step']);
    }

    public function products() {

        $product_model = new shopKitchenaidsPluginProductModel();
        $feature_model = new shopKitchenaidsPluginFeatureModel();
        $image_model = new shopKitchenaidsPluginImageModel();
        $categories_model = new shopKitchenaidsPluginCategoriesModel();

        $products = $product_model->getAll();

        $this->data['count'] = count($products);
        if (!empty($this->data['offset'])) {
            $index = $this->data['offset'];
        } else {
            $index = 0;
            $this->data['offset'] = 0;
        }
        $chunk_size = 1;
        $i = 0;

        while ($index + $i < $this->data['count'] && $i <= $chunk_size) {

            $p = $products[$index + $i];
            $images = $image_model->getByField('product_id', $p['id'], true);
            $features = $feature_model->getByField('product_id', $p['id'], true);


            $p['currency'] = 'RUB';
            $p['meta_title'] = $p['title'];
            $_categories = $categories_model->getByField('product_id', $p['id'], true);
            $categories = array();
            foreach ($_categories as $_category) {
                $categories[] = $_category['category_id'];
            }
            unset($p['id']);
            unset($p['url']);
            $p['categories'] = $categories;
            $p['sku_id'] = -1;
            $p['skus'] = array(
                -1 => array(
                    'available' => 1,
                    'sku' => $p['sku'],
                    'price' => $p['price'],
                    'stock' => array(null),
                    'virtual' => 0,
                )
            );

            $this->saveProduct($p, $images, $features);

            if ($this->data['offset'] >= $this->data['count'] - 1) {
                $this->data['offset'] = $index + $i + 1;
                break;
            }
            $this->data['offset'] = $index + $i + 1;
            $i++;
        }
    }

    private function saveProduct($data, $images, $features) {

        $id = (empty($data['id']) || !intval($data['id'])) ? null : $data['id'];

# edit product info - check rights
        $product_model = new shopProductModel();
        if ($id) {
            if (!$product_model->checkRights($id)) {
                throw new waRightsException(_w("Access denied"));
            }
        } else {
            if (!$product_model->checkRights($data)) {
                throw new waRightsException(_w("Access denied"));
            }
        }


        $data['tags'] = array();
        $data['features_selectable'] = array();
        $data['sku_type'] = shopProductModel::SKU_TYPE_FLAT;
        $data['type_id'] = 1;
        $data['features_selectable'] = array();


        $product = new shopProduct($id);

        shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_PRODUCT);

        $url = shopHelper::transliterate($data['name'], 1);
        $data['url'] = $url;


        if ($product->save($data, true)) {
            $this->saveImages(array('id' => $product->getId()), $images);

            $feature_varchar_model = new shopFeatureValuesVarcharModel();
            $p_features_model = new shopProductFeaturesModel();

            foreach ($features as $feature) {
                $feature_id = $this->saveFeature($feature['name']);

                $value = array(
                    'feature_id' => $feature_id,
                    'value' => $feature['value']
                );

                $exist = $feature_varchar_model->getByField($value);
                if ($exist) {
                    $feature_value_id = $exist['id'];
                } else {
                    $feature_value_id = $feature_varchar_model->insert($value);
                }

                $data = array(
                    'product_id' => $product->getId(),
                    'feature_id' => $feature_id,
                    'feature_value_id' => $feature_value_id,
                );
                $p_features_model->insert($data);
            }
        }
    }

    public function saveFeature($name) {
        $feature = array(
            'code' => '',
            'multiple' => 0,
            'name' => $name,
            'type' => 'varchar',
            'types' => array(0, 1)
        );
        $model = new shopFeatureModel();

        $type_features_model = new shopTypeFeaturesModel();

        if (!($exist = $model->getByField('name', $name))) {
            $id = $model->save($feature);
        } else {
            $id = $exist['id'];
        }

        //$feature['types'] = 
                $type_features_model->updateByFeature($id, $feature['types']);

        return $id;
    }

    public function saveImages($product, $images) {


        $config = $this->getConfig();
        $model = new shopProductImagesModel();
        if (!isset($product['id'])) {
            throw new waException("Не определен идентификатор товара");
        }



        foreach ($images as $image) {

            try {

                $image_url = $image['image'];


                if (!trim($image_url)) {
                    throw new waException("Не указана ссылка на изображение");
                }
                $u = @parse_url($image_url);
                if (!$u || !(isset($u['scheme']) && isset($u['host']) && isset($u['path']))) {
                    throw new waException("Некорректная ссылка на изображение");
                } elseif (in_array($u['scheme'], array('http', 'https', 'ftp', 'ftps'))) {
                    
                } else {
                    throw new waException("Неподдерживаемый файловый протокол " . $u['scheme']);
                }
                $name = preg_replace('@[^a-z0-9\._\-]+@', '', basename($image_url));
                waFiles::upload($image_url, $file = wa()->getCachePath('plugins/kitchenaids/' . waLocale::transliterate($name, 'en_US')));
                if ($file && file_exists($file)) {
                    if ($image = waImage::factory($file)) {
                        $data = array(
                            'product_id' => $product['id'],
                            'upload_datetime' => date('Y-m-d H:i:s'),
                            'width' => $image->width,
                            'height' => $image->height,
                            'size' => filesize($file),
                            'original_filename' => $name,
                            'ext' => pathinfo($file, PATHINFO_EXTENSION),
                        );
                        $image_changed = false;

                        $event = wa()->event('image_upload', $image);
                        if ($event) {
                            foreach ($event as $result) {
                                if ($result) {
                                    $image_changed = true;
                                    break;
                                }
                            }
                        }
                        if (empty($data['id'])) {
                            $image_id = $data['id'] = $model->add($data);
                        } else {
                            $image_id = $data['id'];
                            $model->updateById($image_id, $data);
                        }
                        if (!$image_id) {
                            throw new waException("Database error");
                        }
                        $image_path = shopImage::getPath($data);
                        if ((file_exists($image_path) && !is_writable($image_path)) || (!file_exists($image_path) && !waFiles::create($image_path))) {
                            $model->deleteById($image_id);
                            throw new waException(sprintf("The insufficient file write permissions for the %s folder.", substr($image_path, strlen($this->getConfig()->getRootPath()))));
                        }
                        if ($image_changed) {
                            $image->save($image_path);
                            if ($config->getOption('image_save_original') && ($original_file = shopImage::getOriginalPath($data))) {
                                waFiles::copy($file, $original_file);
                            }
                        } else {
                            waFiles::copy($file, $image_path);
                        }
                    } else {
                        throw new waException(sprintf('Файл не является изображением', $file));
                    }
                } elseif ($file) {
                    throw new waException("Ошибка загрузки файла");
                }
            } catch (Exception $ex) {
                
            }
        }
    }

    private function saveCategorySettings($id, &$data) {
        /**
         * @var shopCategoryModel
         */
        $model = new shopCategoryModel();
        if (!$id) {
            if (empty($data['url'])) {
                $url = shopHelper::transliterate($data['name'], false);
                if ($url) {
                    $data['url'] = $model->suggestUniqueUrl($url);
                }
            }
            if (empty($data['name'])) {
                $data['name'] = _w('(no-name)');
            }
            $id = $model->add($data, $data['parent_id']);
        } else {
            $category = $model->getById($id);
            if (!$this->categorySettingsValidate($category, $data)) {
                return false;
            }
            if (empty($data['name'])) {
                $data['name'] = $category['name'];
            }
            if (empty($data['url'])) {
                $data['url'] = $model->suggestUniqueUrl(shopHelper::transliterate($data['name']), $id, $category['parent_id']);
            }
            unset($data['parent_id']);
            $data['edit_datetime'] = date('Y-m-d H:i:s');
            $model->update($id, $data);
        }
        if ($id) {
            if (waRequest::post('enable_sorting')) {
                $data['params']['enable_sorting'] = 1;
            }
            $category_params_model = new shopCategoryParamsModel();
            $category_params_model->set($id, !empty($data['params']) ? $data['params'] : null);

            $category_routes_model = new shopCategoryRoutesModel();
            $category_routes_model->setRoutes($id, isset($data['routes']) ? $data['routes'] : array());

            $data['id'] = $id;
            /**
             * @event category_save
             * @param array $category
             * @return void
             */
            wa()->event('category_save', $data);
        }
        return $id;
    }

}
