<?php
namespace UNSProjectApp\Helpers;

class View
{
    /**
     * @param string $src
     * @param array $params
     */
    public static function load($src, $params = []){
        if(is_array($params) && !empty($params)) {
            foreach ($params as $key => $value) {
                $$key = $value;
            }
        }

        $path = plugin_dir_path( __DIR__ ).'Views/'.$src;

        include $path;
    }
}
