jQuery(document).ready(function($) {
    let selectedPosts = new Set();
    let searchTimeout;
    const $hiddenInput = $('input[name="jankx_recommended_posts"]');

    // Load danh sách bài viết đã chọn từ input hidden
    const initialPosts = $hiddenInput.val() ? JSON.parse($hiddenInput.val()) : [];
    initialPosts.forEach(postId => selectedPosts.add(postId));

    // Xử lý tìm kiếm
    $('#jankx-post-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchPosts();
        }, 500);
    }).on('keypress', function(e) {
        // Xử lý khi nhấn Enter
        if (e.which === 13) {
            e.preventDefault();
            clearTimeout(searchTimeout);
            searchPosts();
        }
    });

    // Hàm tìm kiếm bài viết
    function searchPosts() {
        const search = $('#jankx-post-search').val();
        const postType = $('#jankx-post-type').val();
        const currentPostId = $('#post_ID').val();

        $.ajax({
            url: jankxRecommendedPosts.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_recommended_posts',
                nonce: jankxRecommendedPosts.nonce,
                search: search,
                post_type: postType,
                current_post_id: currentPostId
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data);
                }
            }
        });
    }

    // Hiển thị kết quả tìm kiếm
    function displaySearchResults(posts) {
        const $results = $('.jankx-recommended-posts-results');
        $results.empty();

        posts.forEach(function(post) {
            const isSelected = selectedPosts.has(post.ID);
            const $card = $(`
                <div class="jankx-post-card" data-post-id="${post.ID}">
                    ${post.thumbnail ? `<img src="${post.thumbnail}" alt="${post.title}">` : ''}
                    <h4>${post.title}</h4>
                    ${post.price ? `<div class="price">${post.price}</div>` : ''}
                    <div class="actions">
                        <button type="button" class="${isSelected ? 'remove-post' : 'add-post'}">
                            ${isSelected ? 'Xóa' : 'Thêm'}
                        </button>
                    </div>
                </div>
            `);

            $results.append($card);
        });
    }

    // Xử lý thêm/xóa bài viết
    $(document).on('click', '.jankx-post-card .add-post, .jankx-post-card .remove-post', function(e) {
        e.preventDefault();
        const $card = $(this).closest('.jankx-post-card');
        const postId = parseInt($card.data('post-id'));
        const isAdding = $(this).hasClass('add-post');

        if (isAdding) {
            selectedPosts.add(postId);
            $(this).text('Xóa').removeClass('add-post').addClass('remove-post');
            addSelectedPost($card);
        } else {
            selectedPosts.delete(postId);
            $(this).text('Thêm').removeClass('remove-post').addClass('add-post');
            $(`.jankx-selected-post[data-post-id="${postId}"]`).remove();
        }

        updateHiddenInput();
    });

    // Xử lý xóa bài viết đã chọn
    $(document).on('click', '.jankx-selected-post .remove-selected', function(e) {
        e.preventDefault();
        const postId = parseInt($(this).closest('.jankx-selected-post').data('post-id'));
        selectedPosts.delete(postId);
        $(this).closest('.jankx-selected-post').remove();
        $(`.jankx-post-card[data-post-id="${postId}"] .remove-post`).text('Thêm').removeClass('remove-post').addClass('add-post');
        updateHiddenInput();
    });

    // Thêm bài viết vào danh sách đã chọn
    function addSelectedPost($card) {
        const postId = parseInt($card.data('post-id'));
        const $selectedPost = $(`
            <div class="jankx-selected-post" data-post-id="${postId}">
                ${$card.find('img').length ? `<img src="${$card.find('img').attr('src')}" alt="${$card.find('h4').text()}">` : ''}
                <h4>${$card.find('h4').text()}</h4>
                ${$card.find('.price').length ? `<div class="price">${$card.find('.price').html()}</div>` : ''}
                <button type="button" class="remove-selected">×</button>
            </div>
        `);

        $('.jankx-selected-posts').append($selectedPost);
    }

    // Cập nhật input ẩn
    function updateHiddenInput() {
        const postsArray = Array.from(selectedPosts).map(id => parseInt(id));
        $hiddenInput.val(JSON.stringify(postsArray));
    }

    // Đảm bảo dữ liệu được lưu khi submit form
    $('#post').on('submit', function() {
        updateHiddenInput();
    });
});