(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        var dialog = document.getElementById('scrb-shortcode-modal');

        if (!dialog) {
            return;
        }

        var allRoomsRadio = dialog.querySelector('input[value="rooms"]');
        var oneRoomRadio = dialog.querySelector('input[value="room"]');
        var select = document.getElementById('scrb-shortcode-room-select');
        var insertButton = document.getElementById('scrb-shortcode-insert');
        var cancelButton = document.getElementById('scrb-shortcode-cancel');

        var rooms = window.scrbShortcodeRooms || [];

        rooms.forEach(function (room) {
            var option = document.createElement('option');
            option.value = room.id;
            option.textContent = room.name;
            select.appendChild(option);
        });

        function updateSelectState() {
            select.disabled = !oneRoomRadio.checked;
        }

        allRoomsRadio.addEventListener('change', updateSelectState);
        oneRoomRadio.addEventListener('change', updateSelectState);

        // The TinyMCE button (assets/js/tinymce-button.js) calls this —
        // defined as a global rather than an event listener on the
        // dialog itself, since that file has no reliable way to know
        // this one's already run by the time its own button is clicked.
        window.scrbOpenShortcodeModal = function () {
            dialog.showModal();
        };

        cancelButton.addEventListener('click', function () {
            dialog.close();
        });

        // Click-on-backdrop-to-close, same idiom as the front-end
        // booking widget's own dialog (assets/js/booking-widget.js).
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        insertButton.addEventListener('click', function () {
            var shortcode;

            if (oneRoomRadio.checked) {
                if (!select.value) {
                    select.focus();

                    return;
                }

                shortcode = '[sc_room id="' + select.value + '"]';
            } else {
                shortcode = '[sc_rooms]';
            }

            insertAtCaret(shortcode);
            dialog.close();
        });
    }

    // The classic editor's own TinyMCE instance if it's focused/active,
    // otherwise the "Text" (HTML) view's plain textarea — a click on
    // this plugin's own toolbar button only ever opens the dialog while
    // one of those two is what the user's actually looking at, so
    // between them this covers every way WordPress's classic editor can
    // be showing right now.
    function insertAtCaret(shortcode) {
        if (window.tinymce && window.tinymce.activeEditor && !window.tinymce.activeEditor.isHidden()) {
            window.tinymce.activeEditor.execCommand('mceInsertContent', false, shortcode);

            return;
        }

        var textarea = document.getElementById('content');

        if (!textarea) {
            return;
        }

        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;
        var value = textarea.value;

        textarea.value = value.slice(0, start) + shortcode + value.slice(end);
        textarea.selectionStart = textarea.selectionEnd = start + shortcode.length;
        textarea.focus();
    }
})();
