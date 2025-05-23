jQuery(document).ready(function($) {
    let selectedPosts = new Set();
    let searchTimeout;
    const $hiddenInput = $('#jankx_recommended_posts');

    // Load danh sách bài viết đã chọn
    $('.jankx-selected-post').each(function() {
        selectedPosts.add($(this).data('post-id'));
    });

    // Xử lý tìm kiếm
    $('#jankx-post-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchPosts();
        }, 500);
    });

    // Xử lý thay đổi loại bài viết
    $('#jankx-post-type').on('change', function() {
        searchPosts();
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
                        <button class="${isSelected ? 'remove-post' : 'add-post'}">
                            ${isSelected ? 'Xóa' : 'Thêm'}
                        </button>
                    </div>
                </div>
            `);

            $results.append($card);
        });
    }

    // Xử lý thêm/xóa bài viết
    $(document).on('click', '.jankx-post-card .add-post, .jankx-post-card .remove-post', function() {
        const $card = $(this).closest('.jankx-post-card');
        const postId = $card.data('post-id');
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
    $(document).on('click', '.jankx-selected-post .remove-selected', function() {
        const postId = $(this).closest('.jankx-selected-post').data('post-id');
        selectedPosts.delete(postId);
        $(this).closest('.jankx-selected-post').remove();
        $(`.jankx-post-card[data-post-id="${postId}"] .remove-post`).text('Thêm').removeClass('remove-post').addClass('add-post');
        updateHiddenInput();
    });

    // Thêm bài viết vào danh sách đã chọn
    function addSelectedPost($card) {
        const postId = $card.data('post-id');
        const $selectedPost = $(`
            <div class="jankx-selected-post" data-post-id="${postId}">
                ${$card.find('img').length ? `<img src="${$card.find('img').attr('src')}" alt="${$card.find('h4').text()}">` : ''}
                <h4>${$card.find('h4').text()}</h4>
                ${$card.find('.price').length ? `<div class="price">${$card.find('.price').html()}</div>` : ''}
                <button class="remove-selected">×</button>
            </div>
        `);

        $('.jankx-selected-posts').append($selectedPost);
    }

    // Cập nhật input ẩn
    function updateHiddenInput() {
        $hiddenInput.val(JSON.stringify(Array.from(selectedPosts)));
    }
});