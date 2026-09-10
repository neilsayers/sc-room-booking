(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        initRepeater({
            containerId: 'scrb-price-options',
            templateId: 'scrb-price-option-template',
            addButtonSelector: '.scrb-add-price-option',
        });

        initRepeater({
            containerId: 'scrb-layout-variants',
            templateId: 'scrb-layout-variant-template',
            addButtonSelector: '.scrb-add-layout-variant',
            onRowAdded: bindImagePicker,
        });

        document.querySelectorAll('#scrb-layout-variants .scrb-repeater-row').forEach(bindImagePicker);

        initGalleryImages();
    }

    /**
     * Wires one "add row" button + row container pair. Rows are
     * plain HTML cloned from a <template>'s innerHTML with __INDEX__
     * swapped for an incrementing counter — the counter only ever
     * goes up (removed rows aren't reused) so two rows never collide
     * on the same array index when the form posts.
     */
    function initRepeater(options) {
        var container = document.getElementById(options.containerId);
        var template = document.getElementById(options.templateId);
        var addButton = document.querySelector(options.addButtonSelector);

        if (!container || !template || !addButton) {
            return;
        }

        var max = parseInt(container.dataset.max, 10) || 0;
        var counter = container.querySelectorAll('.scrb-repeater-row').length;

        addButton.addEventListener('click', function () {
            if (max && rowCount(container) >= max) {
                return;
            }

            var html = template.innerHTML.replace(/__INDEX__/g, String(counter));
            counter += 1;

            var wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            var row = wrapper.firstElementChild;
            container.appendChild(row);

            if (typeof options.onRowAdded === 'function') {
                options.onRowAdded(row);
            }

            updateAddButtonState(container, addButton, max);
        });

        container.addEventListener('click', function (event) {
            var removeButton = event.target.closest('.scrb-remove-row');

            if (!removeButton || !container.contains(removeButton)) {
                return;
            }

            // Always leave at least one row — the first row represents
            // this room's default value (its default price, or its
            // single Capacity figure), not an optional extra.
            if (rowCount(container) <= 1) {
                return;
            }

            removeButton.closest('.scrb-repeater-row').remove();
            updateAddButtonState(container, addButton, max);
        });

        updateAddButtonState(container, addButton, max);
    }

    function rowCount(container) {
        return container.querySelectorAll('.scrb-repeater-row').length;
    }

    function updateAddButtonState(container, addButton, max) {
        addButton.disabled = Boolean(max) && rowCount(container) >= max;
    }

    /**
     * Opens WordPress's own media modal (wp_enqueue_media(), see
     * MetaBoxes\RoomDetailsMetaBox::enqueueAssets()) for one layout
     * option row's image button, and writes the chosen attachment's ID
     * + a thumbnail preview back into that row.
     */
    function bindImagePicker(row) {
        var pickButton = row.querySelector('.scrb-pick-image');

        if (!pickButton || pickButton.dataset.scrbBound) {
            return;
        }

        pickButton.dataset.scrbBound = '1';

        pickButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (!window.wp || !wp.media) {
                return;
            }

            var idInput = row.querySelector('.scrb-image-id');
            var preview = row.querySelector('.scrb-image-preview');

            var frame = wp.media({
                title: 'Select an image',
                button: {text: 'Use this image'},
                multiple: false,
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail)
                    ? attachment.sizes.thumbnail.url
                    : attachment.url;

                idInput.value = attachment.id;
                preview.innerHTML = '<img src="' + thumbUrl + '" alt="">';
                pickButton.textContent = 'Change image';
            });

            frame.open();
        });
    }

    /**
     * "Gallery images" is a flat list of attachment IDs rather than a
     * repeater of rows — each image is its own <input type="hidden">
     * sharing the name scrb[gallery_images][], the same "repeated same
     * name = PHP array on submit" pattern the Availability days
     * checkboxes already use, just built with JS instead of PHP since
     * items are added/removed live. No <template> needed here since
     * every item has the same one field (an ID); layout options
     * needed one because each row is really three different fields.
     */
    function initGalleryImages() {
        var container = document.getElementById('scrb-gallery-images');
        var addButton = document.querySelector('.scrb-add-gallery-images');

        if (!container || !addButton) {
            return;
        }

        var max = parseInt(container.dataset.max, 10) || 0;

        var updateAddButton = function () {
            addButton.disabled = Boolean(max) && itemCount() >= max;
        };

        var itemCount = function () {
            return container.querySelectorAll('.scrb-gallery-item').length;
        };

        addButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (!window.wp || !wp.media || (max && itemCount() >= max)) {
                return;
            }

            var existingIds = Array.prototype.map.call(
                container.querySelectorAll('.scrb-gallery-item input'),
                function (input) { return input.value; }
            );

            var frame = wp.media({
                title: 'Select images',
                button: {text: 'Add to gallery'},
                multiple: true,
            });

            frame.on('select', function () {
                var attachments = frame.state().get('selection').toJSON();

                attachments.forEach(function (attachment) {
                    if (max && itemCount() >= max) {
                        return;
                    }

                    if (existingIds.indexOf(String(attachment.id)) !== -1) {
                        return;
                    }

                    var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail)
                        ? attachment.sizes.thumbnail.url
                        : attachment.url;

                    var item = document.createElement('span');
                    item.className = 'scrb-gallery-item';
                    item.innerHTML = '<img src="' + thumbUrl + '" alt="">'
                        + '<input type="hidden" name="scrb[gallery_images][]" value="' + attachment.id + '">'
                        + '<button type="button" class="button-link scrb-remove-gallery-image" aria-label="Remove image">&times;</button>';

                    container.appendChild(item);
                    existingIds.push(String(attachment.id));
                });

                updateAddButton();
            });

            frame.open();
        });

        container.addEventListener('click', function (event) {
            var removeButton = event.target.closest('.scrb-remove-gallery-image');

            if (!removeButton) {
                return;
            }

            removeButton.closest('.scrb-gallery-item').remove();
            updateAddButton();
        });

        updateAddButton();
    }
})();
