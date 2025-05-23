<?php

use Jankx\RecommendedPosts\RecommendedPosts;

/**
 *
 * Plugin Name: Editor Recommended Posts
 * Author: Puleeno Nguyen
 * Author URI: https://puleeno.com
 * Description:
 */
class Editor_Recommended_Post_Loader {
    public function __construct() {
        Jankx::getInstance()->instance(RecommendedPosts::class, new RecommendedPosts());
    }

    public function load(){
    }

    public function loadAdmin() {
    }
}

$loader = new Editor_Recommended_Post_Loader();
add_action('wp', [$loader, 'load']);
add_action('admin_init', [$loader, 'loadAdmin']);
