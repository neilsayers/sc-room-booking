(function ($) {
    'use strict';

    $(function () {
        $('.scrb-order-list').each(function () {
            var $list = $(this);
            var postType = $list.data('post-type');

            $list.sortable({
                handle: '.scrb-order-handle',
                axis: 'y',
                placeholder: 'scrb-order-row scrb-order-placeholder',
                update: function () {
                    var ids = $list.find('.scrb-order-row').map(function () {
                        return $(this).data('id');
                    }).get();

                    $list.addClass('scrb-order-list--saving');

                    $.post(window.scrbRoomOrder.ajaxUrl, {
                        action: window.scrbRoomOrder.action,
                        nonce: window.scrbRoomOrder.nonce,
                        post_type: postType,
                        ids: ids,
                    }).always(function () {
                        $list.removeClass('scrb-order-list--saving');
                    });
                },
            });
        });
    });
})(jQuery);
