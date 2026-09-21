(function () {
    'use strict';

    // Only registers the toolbar button — everything the button opens
    // is a plain <dialog> (assets/js/shortcode-modal.js), not TinyMCE's
    // own windowManager, which has no real radio-button field type to
    // build "all rooms, or one room by ID" with. window.tinymce is
    // WordPress core's own global; this file is only ever enqueued via
    // mce_external_plugins, so it's always present by the time this runs.
    tinymce.PluginManager.add('scrb_shortcode', function (editor) {
        editor.addButton('scrb_shortcode', {
            text: 'SC Room Bookings',
            icon: false,
            onclick: function () {
                if (window.scrbOpenShortcodeModal) {
                    window.scrbOpenShortcodeModal();
                }
            },
        });
    });
})();
