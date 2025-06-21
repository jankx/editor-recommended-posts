jQuery(document).ready(function($) {
    // Khởi tạo cho meta box
    initMetaBox();

    // Khởi tạo cho global suggestions settings
    initGlobalSuggestions();

    function initMetaBox() {
        const $metaBox = $('.jankx-recommended-posts-container');
        if ($metaBox.length === 0) return;

        let selectedPosts = new Set();
        let searchTimeout;
        const $hiddenInput = $('input[name="jankx_recommended_posts"]');
        const $searchContainer = $('.jankx-recommended-posts-search');
        const $results = $('.jankx-recommended-posts-results');

        // Thêm loading spinner vào container tìm kiếm
        $searchContainer.append('<div class="loading"></div>');
        const $loading = $searchContainer.find('.loading');

        // Load danh sách bài viết đã chọn từ input hidden
        const initialPosts = $hiddenInput.val() ? JSON.parse($hiddenInput.val()) : [];
        initialPosts.forEach(postId => selectedPosts.add(postId));

        // Xử lý tìm kiếm
        $('#jankx-post-search').on('input', function() {
            clearTimeout(searchTimeout);
            $results.addClass('loading').empty();
            searchTimeout = setTimeout(function() {
                searchPosts();
            }, 500);
        }).on('keypress', function(e) {
            // Xử lý khi nhấn Enter
            if (e.which === 13) {
                e.preventDefault();
                clearTimeout(searchTimeout);
                $results.addClass('loading').empty();
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
                },
                complete: function() {
                    $results.removeClass('loading');
                }
            });
        }

        // Hiển thị kết quả tìm kiếm
        function displaySearchResults(posts) {
            const $results = $('.jankx-recommended-posts-results');
            $results.empty();

            if (posts.length === 0) {
                $results.html('<div class="no-results">Không tìm thấy bài viết nào phù hợp với từ khóa tìm kiếm.</div>');
                return;
            }

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
        $metaBox.on('click', '.jankx-post-card .add-post, .jankx-post-card .remove-post', function(e) {
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
        $metaBox.on('click', '.jankx-selected-post .remove-selected', function(e) {
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
    }

    function initGlobalSuggestions() {
        const $globalContainer = $('.jankx-global-suggestions-container');
        if ($globalContainer.length === 0) return;

        let searchTimeout;

        // Xử lý tìm kiếm cho từng post type
        $globalContainer.each(function() {
            const $container = $(this);
            const postType = $container.data('post-type');
            const $searchInput = $container.find('.jankx-global-search-input');
            const $results = $container.find('.jankx-global-suggestions-results');
            const $hiddenInput = $container.find('.jankx-global-suggestions-input');
            let selectedPosts = new Set();

            // Load danh sách bài viết đã chọn từ input hidden
            const initialPosts = $hiddenInput.val() ? JSON.parse($hiddenInput.val()) : [];
            initialPosts.forEach(postId => selectedPosts.add(postId));

            // Xử lý tìm kiếm
            $searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                $results.addClass('loading').empty();
                searchTimeout = setTimeout(function() {
                    searchGlobalPosts($container, postType, selectedPosts);
                }, 500);
            }).on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    clearTimeout(searchTimeout);
                    $results.addClass('loading').empty();
                    searchGlobalPosts($container, postType, selectedPosts);
                }
            });

            // Xử lý nút thêm
            $container.find('.jankx-add-global-suggestion').on('click', function() {
                const search = $searchInput.val();
                if (search.trim()) {
                    clearTimeout(searchTimeout);
                    $results.addClass('loading').empty();
                    searchGlobalPosts($container, postType, selectedPosts);
                }
            });

            // Hàm tìm kiếm bài viết
            function searchGlobalPosts($container, postType, selectedPosts) {
                const search = $container.find('.jankx-global-search-input').val();

                $.ajax({
                    url: jankxRecommendedPosts.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_recommended_posts',
                        nonce: jankxRecommendedPosts.nonce,
                        search: search,
                        post_type: postType,
                        current_post_id: 0
                    },
                    success: function(response) {
                        if (response.success) {
                            displayGlobalSearchResults($container, response.data, selectedPosts);
                        }
                    },
                    complete: function() {
                        $container.find('.jankx-global-suggestions-results').removeClass('loading');
                    }
                });
            }

            // Hiển thị kết quả tìm kiếm global
            function displayGlobalSearchResults($container, posts, selectedPosts) {
                const $results = $container.find('.jankx-global-suggestions-results');
                $results.empty();

                if (posts.length === 0) {
                    $results.html('<div class="no-results">Không tìm thấy bài viết nào phù hợp với từ khóa tìm kiếm.</div>');
                    return;
                }

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
        });

        // Sử dụng event delegation cho toàn bộ document
        $(document).on('click', '.jankx-global-suggestions-container .jankx-post-card .add-post, .jankx-global-suggestions-container .jankx-post-card .remove-post', function(e) {
            e.preventDefault();
            const $card = $(this).closest('.jankx-post-card');
            const $container = $(this).closest('.jankx-global-suggestions-container');
            const postId = parseInt($card.data('post-id'));
            const isAdding = $(this).hasClass('add-post');

            // Lấy selectedPosts từ container
            const $hiddenInput = $container.find('.jankx-global-suggestions-input');
            let selectedPosts = new Set();
            const currentPosts = $hiddenInput.val() ? JSON.parse($hiddenInput.val()) : [];
            currentPosts.forEach(postId => selectedPosts.add(postId));

            if (isAdding) {
                selectedPosts.add(postId);
                $(this).text('Xóa').removeClass('add-post').addClass('remove-post');
                addGlobalSelectedPost($container, $card);
            } else {
                selectedPosts.delete(postId);
                $(this).text('Thêm').removeClass('remove-post').addClass('add-post');
                $container.find(`.jankx-global-selected-post[data-post-id="${postId}"]`).remove();
            }

            updateGlobalHiddenInput($container, selectedPosts);
        });

        // Xử lý xóa bài viết đã chọn global
        $(document).on('click', '.jankx-global-suggestions-container .jankx-global-selected-post .remove-global-selected', function(e) {
            e.preventDefault();
            const $container = $(this).closest('.jankx-global-suggestions-container');
            const postId = parseInt($(this).closest('.jankx-global-selected-post').data('post-id'));

            // Lấy selectedPosts từ container
            const $hiddenInput = $container.find('.jankx-global-suggestions-input');
            let selectedPosts = new Set();
            const currentPosts = $hiddenInput.val() ? JSON.parse($hiddenInput.val()) : [];
            currentPosts.forEach(postId => selectedPosts.add(postId));

            selectedPosts.delete(postId);
            $(this).closest('.jankx-global-selected-post').remove();
            $container.find(`.jankx-post-card[data-post-id="${postId}"] .remove-post`).text('Thêm').removeClass('remove-post').addClass('add-post');
            updateGlobalHiddenInput($container, selectedPosts);
        });

        // Thêm bài viết vào danh sách đã chọn global
        function addGlobalSelectedPost($container, $card) {
            const postId = parseInt($card.data('post-id'));
            const $selectedPost = $(`
                <div class="jankx-global-selected-post" data-post-id="${postId}">
                    ${$card.find('img').length ? `<img src="${$card.find('img').attr('src')}" alt="${$card.find('h4').text()}">` : ''}
                    <h5>${$card.find('h4').text()}</h5>
                    ${$card.find('.price').length ? `<div class="price">${$card.find('.price').html()}</div>` : ''}
                    <button type="button" class="remove-global-selected">×</button>
                </div>
            `);

            $container.find('.jankx-global-selected-posts').append($selectedPost);
        }

        // Cập nhật input ẩn global
        function updateGlobalHiddenInput($container, selectedPosts) {
            const postsArray = Array.from(selectedPosts).map(id => parseInt(id));
            $container.find('.jankx-global-suggestions-input').val(JSON.stringify(postsArray));
        }
    }
});