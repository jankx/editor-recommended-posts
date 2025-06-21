<?php

namespace Jankx\RecommendedPosts;

if (!defined('ABSPATH')) {
    exit('Cheating huh?');
}

use Jankx\Adapter\Options\Helper;

class Options
{
    protected $option_group = 'jankx_recommended_posts_options';
    protected const OPTION_PREFIX = 'jankx_recommended_posts_global_suggestions_';
    protected $page_slug = 'jankx-recommended-posts-settings';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Debug: hook để kiểm tra khi form được submit
        add_action('update_option', array($this, 'debug_option_update'), 10, 3);

        // Migration từ option cũ sang option mới
        add_action('admin_init', array($this, 'maybe_migrate_old_options'));
    }

    public function maybe_migrate_old_options()
    {
        // Chỉ migration một lần
        if (get_option('jankx_recommended_posts_migration_done')) {
            return;
        }

        self::migrateOldOptions();
        update_option('jankx_recommended_posts_migration_done', true);
    }

    public function debug_option_update($option, $old_value, $value)
    {
        // Chỉ debug các option của chúng ta
        if (strpos($option, static::OPTION_PREFIX) === 0) {
            error_log('Option updated: ' . $option);
            error_log('Old value: ' . print_r($old_value, true));
            error_log('New value: ' . print_r($value, true));
        }
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'options-general.php',
            'Gợi ý bài viết toàn cục',
            'Gợi ý bài viết',
            'manage_options',
            $this->page_slug,
            array($this, 'render_settings_page')
        );
    }

    public function register_settings()
    {
        $support_post_types = apply_filters('jankx/recommended/post_types', array('post', 'product'));

        foreach ($support_post_types as $post_type) {
            $option_name = static::OPTION_PREFIX . $post_type;

            register_setting(
                $this->option_group,
                $option_name,
                array(
                    'type' => 'array',
                    'sanitize_callback' => array($this, 'sanitize_global_suggestions'),
                    'default' => array()
                )
            );

            add_settings_section(
                'global_suggestions_section_' . $post_type,
                'Cài đặt gợi ý toàn cục cho ' . $post_type,
                array($this, 'render_section_description'),
                $this->page_slug
            );

            add_settings_field(
                'global_suggestions_field_' . $post_type,
                'Gợi ý toàn cục cho ' . $post_type,
                array($this, 'render_global_suggestions_field'),
                $this->page_slug,
                'global_suggestions_section_' . $post_type,
                array('post_type' => $post_type)
            );
        }
    }

    public function render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php esc_html_e('Cấu hình gợi ý bài viết toàn cục cho các loại bài viết khác nhau.', 'jankx'); ?></p>

            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Cài đặt đã được lưu thành công!', 'jankx'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->page_slug);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_section_description()
    {
        echo '<p>' . esc_html__('Cấu hình các bài viết sẽ được gợi ý toàn cục cho từng loại bài viết. Những bài viết này sẽ xuất hiện trong danh sách gợi ý khi chỉnh sửa bài viết.', 'jankx') . '</p>';
    }

    public function render_global_suggestions_field($args)
    {
        $post_type = $args['post_type'];
        $option_name = static::OPTION_PREFIX . $post_type;
        $suggestions = get_option($option_name, array());

        $post_type_obj = get_post_type_object($post_type);
        $post_type_label = $post_type_obj ? $post_type_obj->labels->name : $post_type;

        // Debug: hiển thị dữ liệu đã lưu
        if (defined('WP_DEBUG') && constant('WP_DEBUG') === true && current_user_can('manage_options')) {
            echo '<div style="background: #f0f0f0; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc;">';
            echo '<strong>Debug Info for ' . esc_html($post_type) . ':</strong><br>';
            echo 'Option name: ' . esc_html($option_name) . '<br>';
            echo 'Raw option data: ' . print_r($suggestions, true) . '<br>';
            echo '</div>';
        }

        echo '<div class="jankx-global-suggestions-section">';
        echo '<h3>' . esc_html($post_type_label) . '</h3>';
        echo '<div class="jankx-global-suggestions-container" data-post-type="' . esc_attr($post_type) . '">';

        // Search box
        echo '<div class="jankx-global-suggestions-search">';
        echo '<input type="text" class="jankx-global-search-input" placeholder="' . esc_attr__('Tìm kiếm bài viết...', 'jankx') . '">';
        echo '<button type="button" class="button jankx-add-global-suggestion">' . esc_html__('Thêm', 'jankx') . '</button>';
        echo '</div>';

        // Results container
        echo '<div class="jankx-global-suggestions-results"></div>';

        // Selected posts
        echo '<div class="jankx-global-suggestions-selected">';
        echo '<h4>' . esc_html__('Bài viết đã chọn:', 'jankx') . '</h4>';
        echo '<div class="jankx-global-selected-posts">';

        if (!empty($suggestions)) {
            foreach ($suggestions as $post_id) {
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

                echo '<div class="jankx-global-selected-post" data-post-id="' . esc_attr($post_id) . '">';
                if ($thumbnail) {
                    echo '<img src="' . esc_url($thumbnail) . '" alt="' . esc_attr($post_obj->post_title) . '">';
                }
                echo '<h5>' . esc_html($post_obj->post_title) . '</h5>';
                if ($price) {
                    echo '<div class="price">' . $price . '</div>';
                }
                echo '<button type="button" class="remove-global-selected">×</button>';
                echo '</div>';
            }
        }

        echo '</div>';
        echo '</div>';

        // Hidden input for form submission
        echo '<input type="hidden" name="' . esc_attr($option_name) . '" class="jankx-global-suggestions-input" value="' . esc_attr(json_encode($suggestions)) . '">';

        echo '</div>';
        echo '</div>';
    }

    public function sanitize_global_suggestions($input)
    {
        // Debug: log input để kiểm tra
        error_log('Global suggestions input: ' . print_r($input, true));

        // Nếu input là JSON string, decode nó
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                $input = $decoded;
            } else {
                $input = array();
            }
        }

        if (!is_array($input)) {
            return array();
        }

        // Sanitize array of post IDs
        $sanitized = array_map('intval', array_filter($input));

        // Debug: log output để kiểm tra
        error_log('Global suggestions sanitized: ' . print_r($sanitized, true));

        return $sanitized;
    }

    public function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'settings_page_' . $this->page_slug) {
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

    public static function getGlobalSuggestions($post_type)
    {
        $option_name = static::OPTION_PREFIX . $post_type;
        $suggestions = get_option($option_name, array());

        // Đảm bảo trả về array
        if (!is_array($suggestions)) {
            return array();
        }

        return $suggestions;
    }

    /**
     * Migration từ option cũ sang option mới
     */
    public static function migrateOldOptions()
    {
        $old_option = 'jankx_recommended_posts_global_suggestions';
        $old_data = get_option($old_option, array());

        if (!empty($old_data) && is_array($old_data)) {
            foreach ($old_data as $post_type => $suggestions) {
                $new_option = static::OPTION_PREFIX . $post_type;
                update_option($new_option, $suggestions);
            }

            // Xóa option cũ
            delete_option($old_option);
        }
    }
}