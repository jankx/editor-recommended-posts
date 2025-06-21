<?php

namespace Jankx\RecommendedPosts;

if (!defined('ABSPATH')) {
    exit('Cheating huh?');
}

use Jankx\PostLayout\Layout\Card;
use Jankx\PostLayout\PostLayoutManager;
use Jankx\Adapter\Options\Helper;
use Jankx\TemplateAndLayout;
use Jankx\WooCommerce\Renderer\ProductsRenderer;

class RecommendedPosts
{
    protected $meta_key = '_jankx_recommended_posts';
    protected $post_types = array('post', 'product');

    protected $appliedPostType = null;

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('save_post', array($this, 'save_meta_box_data'));
        add_action('wp_ajax_get_recommended_posts', array($this, 'get_recommended_posts'));
        add_action('wp_ajax_nopriv_get_recommended_posts', array($this, 'get_recommended_posts'));

        // Hiển thị bài viết đề xuất ở trang chi tiết
        $renderHook = Helper::getOption('recommended_render_hook', 'jankx/template/main_content_sidebar/end');
        $renderPriority = Helper::getOption('recommended_render_priority', 50);

        add_action($renderHook, array($this, 'display_recommended_posts'), $renderPriority);

        // Khởi tạo Options class
        new Options();
    }

    public function add_meta_box()
    {
        $support_post_types = apply_filters('jankx/recommended/post_types', $this->post_types);
        foreach ($support_post_types as $post_type) {
            $this->appliedPostType = static::getRecommendedPostType($post_type);
            $postTypeObject = get_post_type_object($this->appliedPostType);

            $labels = get_post_type_labels($postTypeObject);

            add_meta_box(
                'jankx_recommended_posts',
                'Gợi ý sản phẩm liên quan',
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public function enqueue_admin_scripts($hook)
    {
        global $post_type;
        if (!in_array($post_type, $this->post_types) && $hook !== 'settings_page_jankx-recommended-posts-settings') {
            return;
        }

        wp_enqueue_style(
            'jankx-recommended-posts',
            jankx_get_path_url(sprintf('%s/assets/css/admin.css', dirname(__DIR__))),
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'jankx-recommended-posts',
            jankx_get_path_url(sprintf('%s/assets/js/admin.js', dirname(__DIR__))),
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('jankx-recommended-posts', 'jankxRecommendedPosts', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jankx_recommended_posts_nonce'),
        ));
    }

    public function render_meta_box($post)
    {
        $saved_posts = get_post_meta($post->ID, $this->meta_key, true);
        if (!is_array($saved_posts)) {
            $saved_posts = array();
        }

        // Lấy global suggestions cho post type này
        $global_suggestions = Options::getGlobalSuggestions($this->appliedPostType);

        ?>
        <div class="jankx-recommended-posts-container">
            <?php wp_nonce_field('jankx_recommended_posts', 'jankx_recommended_posts_nonce'); ?>
            <div class="jankx-recommended-posts-search">
                <input type="text" id="jankx-post-search" placeholder="<?php esc_attr_e('Tìm kiếm bài viết...', 'jankx'); ?>">
                <input type="hidden" id="jankx-post-type" value="<?php echo esc_attr($this->appliedPostType); ?>">
            </div>

            <div class="jankx-recommended-posts-results"></div>

            <div class="jankx-recommended-posts-selected">
                <h3><?php esc_html_e('Bài viết đã chọn', 'jankx'); ?></h3>
                <div class="jankx-selected-posts">
                    <?php
                    if (!empty($saved_posts)) {
                        foreach ($saved_posts as $post_id) {
                            $post_obj = get_post($post_id);
                            if (!$post_obj) {
                                continue;
                            }

                            $thumbnail = get_the_post_thumbnail_url($post_id, 'thumbnail');
                            $price = '';
                            if ($post_obj->post_type === 'product') {
                                $product = wc_get_product($post_id);
                                if ($product) {
                                    $price = $product->get_price_html();
                                }
                            }
                            ?>
                            <div class="jankx-selected-post" data-post-id="<?php echo esc_attr($post_id); ?>">
                                <?php if ($thumbnail) : ?>
                                    <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($post_obj->post_title); ?>">
                                <?php endif; ?>
                                <h4><?php echo esc_html($post_obj->post_title); ?></h4>
                                <?php if ($price) : ?>
                                    <div class="price"><?php echo $price; ?></div>
                                <?php endif; ?>
                                <button type="button" class="remove-selected">×</button>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>

            <?php if (!empty($global_suggestions)) : ?>
            <div class="jankx-global-suggestions-preview">
                <h3><?php esc_html_e('Gợi ý toàn cục', 'jankx'); ?></h3>
                <p><?php esc_html_e('Những bài viết sau đây được cấu hình làm gợi ý toàn cục cho loại bài viết này:', 'jankx'); ?></p>
                <div class="jankx-global-suggestions-list">
                    <?php
                    foreach ($global_suggestions as $post_id) {
                        $post_obj = get_post($post_id);
                        if (!$post_obj) continue;

                        $thumbnail = get_the_post_thumbnail_url($post_id, 'thumbnail');
                        $price = '';
                        if ($post_obj->post_type === 'product') {
                            $product = wc_get_product($post_id);
                            if ($product) {
                                $price = $product->get_price_html();
                            }
                        }
                        ?>
                        <div class="jankx-global-suggestion-item">
                            <?php if ($thumbnail) : ?>
                                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($post_obj->post_title); ?>">
                            <?php endif; ?>
                            <h4><?php echo esc_html($post_obj->post_title); ?></h4>
                            <?php if ($price) : ?>
                                <div class="price"><?php echo $price; ?></div>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <input type="hidden" name="jankx_recommended_posts" id="jankx_recommended_posts" value="<?php echo esc_attr(json_encode($saved_posts)); ?>">
        </div>
        <?php
    }

    public function save_meta_box_data($post_id)
    {
        // Kiểm tra nonce
        if (
            !isset($_POST['jankx_recommended_posts_nonce']) ||
            !wp_verify_nonce($_POST['jankx_recommended_posts_nonce'], 'jankx_recommended_posts')
        ) {
            return;
        }

        // Kiểm tra autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Kiểm tra quyền
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Kiểm tra và xử lý dữ liệu
        if (!isset($_POST['jankx_recommended_posts'])) {
            delete_post_meta($post_id, $this->meta_key);
            return;
        }

        $posts = json_decode(stripslashes($_POST['jankx_recommended_posts']), true);

        if (is_array($posts)) {
            // Đảm bảo tất cả các ID là số nguyên
            $posts = array_map('intval', $posts);
            // Loại bỏ các ID trùng lặp
            $posts = array_unique($posts);
            // Lưu vào post meta
            update_post_meta($post_id, $this->meta_key, $posts);
        } else {
            delete_post_meta($post_id, $this->meta_key);
        }
    }

    public function get_recommended_posts()
    {
        check_ajax_referer('jankx_recommended_posts_nonce', 'nonce');

        // Kiểm tra quyền - cho phép cả admin và user có quyền edit_posts
        if (!current_user_can('edit_posts') && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        $current_post_id = isset($_POST['current_post_id']) ? intval($_POST['current_post_id']) : 0;

        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 10,
            's' => $search,
        );

        // Chỉ loại trừ current_post_id nếu nó khác 0
        if ($current_post_id > 0) {
            $args['post__not_in'] = array($current_post_id);
        }

        $query = new \WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_data = array(
                    'ID' => get_the_ID(),
                    'title' => get_the_title(),
                    'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail'),
                );

                if ($post_type === 'product') {
                    $product = wc_get_product(get_the_ID());
                    if ($product) {
                        $post_data['price'] = $product->get_price_html();
                    }
                }

                $posts[] = $post_data;
            }
        }
        wp_reset_postdata();

        wp_send_json_success($posts);
    }

    public function display_recommended_posts()
    {
        // Chỉ hiển thị ở trang chi tiết sản phẩm hoặc bài viết
        $queried_object = get_queried_object();
        $post_id = $queried_object->ID;
        $postType = static::getRecommendedPostType($queried_object->post_type);

        $saved_posts = get_post_meta($post_id, $this->meta_key, true);
        if (empty($saved_posts)) {
            $saved_posts = Options::getGlobalSuggestions($postType);
        }

        // empty data
        if (empty($saved_posts) || !is_array($saved_posts)) {
            return;
        }

        $args = apply_filters("jankx/recommeded/{$postType}/query_args", [
            'post_type' => $postType,
            'post_status' => 'publish',
            'post__in' => $saved_posts,
            'orderby' => 'post__in',
            'posts_per_page' => 8
        ]);

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            return;
        }
        $layout = apply_filters("jankx/recommended/{$postType}/layout", Card::LAYOUT_NAME, $queried_object, $args);
        $recomendedPostLayout = PostLayoutManager::getInstance(
            TemplateAndLayout::getTemplateEngine()->getId()
        )->createLayout(
            $layout,
            $query,
            apply_filters("jankx/recommended/{$postType}/layout/content", null)
        );

        $layoutOptions = [
            'columns' => 4,
            'show_excerpt' => true,
            'excerpt_length' => 30
        ];
        if ($postType === 'post') {
            $layoutOptions['post_meta_features'] = [
                'post_date' => true,
            ];
        }
        $layoutOptions = apply_filters("jankx/recommended/{$postType}/layout/options", $layoutOptions);

        $productsModule = null;
        if ($postType === 'product' && class_exists(ProductsRenderer::class)) {
            $productsModule = new ProductsRenderer(array(
                'layout' => $layout,
            ));
            $productsModule->setMainQuery($query);

            // change container wrap tag
            $layoutOptions['wrap_tag_name'] = 'ul';
            $layoutOptions['wrap_classes'] = 'products columns-' . array_get($layoutOptions, 'columns', 4);


            $productsModule->setLayoutOptions($layoutOptions);
        }

        $recomendedPostLayout->setOptions($layoutOptions);

        jankx_template(
            ['recommeded/' . static::getRecommendedPostType($postType), 'recommeded/posts'],
            [
                'content' => $productsModule !== null ? $productsModule->render() : $recomendedPostLayout->render(false),
            ]
        );
    }

    public static function getRecommendedPostType($postType = 'post')
    {
        return apply_filters('jankx/recommeded/' . $postType, $postType);
    }
}