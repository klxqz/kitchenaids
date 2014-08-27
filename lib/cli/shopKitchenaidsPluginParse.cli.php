<?php

class shopKitchenaidsPluginParseCli extends waCliController {

    public function execute() {
        $path_data_parser = wa()->getAppPath('plugins/kitchenaids/lib/classes/dataParser.class.php','shop');
        $path_curl = wa()->getAppPath('plugins/kitchenaids/lib/classes/curlRequest.class.php','shop');
        $path_simple_html_dom = wa()->getAppPath('plugins/kitchenaids/lib/classes/vender/simple_html_dom.php','shop');
        require_once($path_data_parser);
        require_once($path_curl);
        require_once($path_simple_html_dom);
        
        
        $parser = new dataParser();
        $parser->getCategories();
    }

}
