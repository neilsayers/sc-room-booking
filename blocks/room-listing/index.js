(function (wp) {
    'use strict';

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var __ = wp.i18n.__;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;

    // wp.serverSideRender's own export shape has moved around between
    // WP versions (a plain component vs. { default: component }) —
    // covering both rather than assuming whichever this site happens
    // to run.
    var ServerSideRender = wp.serverSideRender && wp.serverSideRender.default
        ? wp.serverSideRender.default
        : wp.serverSideRender;

    registerBlockType('sc-room-bookings/room-listing', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            return el(
                Fragment,
                {},
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: __('Room Listing', 'sc-room-bookings') },
                        el(TextControl, {
                            label: __('Room type', 'sc-room-bookings'),
                            help: __('Leave blank for every configured type, or a single type\'s key (a comma list also works).', 'sc-room-bookings'),
                            value: attributes.roomType,
                            onChange: function (value) {
                                setAttributes({ roomType: value });
                            },
                        }),
                        el(TextControl, {
                            label: __('Limit', 'sc-room-bookings'),
                            help: __('0 for no limit.', 'sc-room-bookings'),
                            type: 'number',
                            min: 0,
                            value: attributes.limit,
                            onChange: function (value) {
                                setAttributes({ limit: parseInt(value, 10) || 0 });
                            },
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'sc-room-bookings/room-listing',
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
