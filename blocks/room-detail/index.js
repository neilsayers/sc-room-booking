(function (wp) {
    'use strict';

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var __ = wp.i18n.__;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var Placeholder = wp.components.Placeholder;

    var ServerSideRender = wp.serverSideRender && wp.serverSideRender.default
        ? wp.serverSideRender.default
        : wp.serverSideRender;

    // Localized by Blocks::registerEditorScripts() — the same {id, name}
    // shape Admin\ShortcodeButton's own TinyMCE modal uses, so this
    // dropdown never needs its own REST round-trip just to list rooms.
    var rooms = window.scrbBlockRooms || [];
    var roomOptions = [{ label: __('Select a room…', 'sc-room-bookings'), value: 0 }].concat(
        rooms.map(function (room) {
            return { label: room.name, value: room.id };
        })
    );

    registerBlockType('sc-room-bookings/room-detail', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var roomPicker = el(SelectControl, {
                label: __('Room', 'sc-room-bookings'),
                value: attributes.roomId,
                options: roomOptions,
                onChange: function (value) {
                    setAttributes({ roomId: parseInt(value, 10) || 0 });
                },
            });

            var controls = el(
                InspectorControls,
                {},
                el(PanelBody, { title: __('Room Detail', 'sc-room-bookings') }, roomPicker)
            );

            if (!attributes.roomId) {
                return el(
                    Fragment,
                    {},
                    controls,
                    el(
                        Placeholder,
                        {
                            icon: 'admin-multisite',
                            label: __('Room Detail', 'sc-room-bookings'),
                            instructions: __('Pick which room this shows.', 'sc-room-bookings'),
                        },
                        roomPicker
                    )
                );
            }

            return el(
                Fragment,
                {},
                controls,
                el(ServerSideRender, {
                    block: 'sc-room-bookings/room-detail',
                    attributes: attributes,
                })
            );
        },
        save: function () {
            // Dynamic block — the front end is always render.php's
            // own output, never anything saved into post_content.
            return null;
        },
    });
})(window.wp);
