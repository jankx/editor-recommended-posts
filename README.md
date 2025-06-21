# Editor Recommended Posts

Plugin WordPress để quản lý bài viết đề xuất cho các loại bài viết khác nhau.

## Tính năng

### 1. Meta Box cho từng bài viết
- Thêm meta box "Gợi ý sản phẩm liên quan" vào trang chỉnh sửa bài viết
- Tìm kiếm và chọn bài viết để gợi ý
- Hỗ trợ cả bài viết thường và sản phẩm WooCommerce
- Hiển thị thumbnail và giá sản phẩm (nếu có)

### 2. Global Suggestions Settings
- Trang cài đặt toàn cục tại **Settings > Gợi ý bài viết**
- Cấu hình danh sách bài viết gợi ý toàn cục cho từng loại bài viết
- Những bài viết này sẽ xuất hiện trong danh sách gợi ý khi chỉnh sửa bài viết
- Hiển thị preview global suggestions trong meta box

### 3. Hiển thị bài viết đề xuất
- Tự động hiển thị bài viết đề xuất ở trang chi tiết
- Sử dụng layout Card với tùy chọn cấu hình
- Hỗ trợ WooCommerce products với ProductsRenderer

## Cách sử dụng

### Cấu hình Global Suggestions

1. Vào **Settings > Gợi ý bài viết**
2. Chọn loại bài viết cần cấu hình (Post, Product)
3. Tìm kiếm và thêm bài viết vào danh sách gợi ý toàn cục
4. Lưu cài đặt

### Chỉnh sửa bài viết

1. Mở trang chỉnh sửa bài viết
2. Tìm meta box "Gợi ý sản phẩm liên quan"
3. Tìm kiếm và chọn bài viết để gợi ý
4. Các bài viết global suggestions sẽ hiển thị ở phần preview
5. Lưu bài viết

## Hooks và Filters

### Filters

- `jankx/recommended/post_types` - Thay đổi danh sách post types được hỗ trợ
- `jankx/recommeded/{post_type}` - Thay đổi post type cho gợi ý
- `jankx/recommeded/{post_type}/query_args` - Tùy chỉnh query args
- `jankx/recommended/{post_type}/layout` - Thay đổi layout hiển thị
- `jankx/recommended/{post_type}/layout/options` - Tùy chỉnh layout options

### Options

- `recommended_render_hook` - Hook để hiển thị bài viết đề xuất (mặc định: `jankx/template/main_content_sidebar/end`)
- `recommended_render_priority` - Priority cho hook hiển thị (mặc định: 50)

## Cấu trúc thư mục

```
editor-recommended-posts/
├── src/
│   ├── RecommendedPosts.php
│   └── Options.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── editor-recommended-posts.php
└── README.md
```

## Yêu cầu

- WordPress 5.0+
- PHP 7.4+
- Jankx Framework
- WooCommerce (tùy chọn, để hỗ trợ sản phẩm)
