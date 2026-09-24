/**
 * Gutenberg editor script — jankx/tour-service-info
 *
 * Registers the block in the editor with a simple placeholder preview.
 * The real UI is server-side rendered (render.php) and shown in iframed
 * preview mode automatically by WordPress.
 */
(function (wp) {
    'use strict';

    var registerBlockType = wp.blocks.registerBlockType;
    var el                = wp.element.createElement;
    var __                = wp.i18n.__;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody         = wp.components.PanelBody;
    var TextControl       = wp.components.TextControl;
    var ToggleControl     = wp.components.ToggleControl;
    var ServerSideRender  = wp.serverSideRender;

    registerBlockType('jankx/tour-service-info', {
        edit: function (props) {
            var attrs    = props.attributes;
            var setAttr  = props.setAttributes;

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: __('Cài đặt block', 'jankx'), initialOpen: true },
                        el(TextControl, {
                            label:    __('Tiêu đề', 'jankx'),
                            value:    attrs.title,
                            onChange: function (v) { setAttr({ title: v }); },
                        }),
                        el(ToggleControl, {
                            label:    __('Hiện nút "Tất cả"', 'jankx'),
                            checked:  attrs.showCalendarLink,
                            onChange: function (v) { setAttr({ showCalendarLink: v }); },
                        })
                    )
                ),
                el(ServerSideRender, {
                    key:        'ssr',
                    block:      'jankx/tour-service-info',
                    attributes: attrs,
                })
            ];
        },
        // save() returns null – fully server-side rendered
        save: function () { return null; },
    });
})(window.wp);
