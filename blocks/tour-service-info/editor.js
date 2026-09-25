/**
 * Gutenberg editor script — jankx/tour-service-info
 *
 * The block heading is provided by an InnerBlocks heading slot instead of a
 * hardcoded <h3>. The rest of the UI is a static preview; the real UI is
 * server-side rendered (render.php) on the frontend.
 */
(function (wp) {
    'use strict';

    var registerBlockType = wp.blocks.registerBlockType;
    var el                = wp.element.createElement;
    var __                = wp.i18n.__;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var InnerBlocks       = wp.blockEditor.InnerBlocks;
    var PanelBody         = wp.components.PanelBody;
    var ToggleControl     = wp.components.ToggleControl;

    var DEFAULT_HEADING = {
        level: 3,
        placeholder: __('Thêm tiêu đề…', 'jankx'),
    };

    var headingTemplate = [
        ['core/heading', Object.assign({ content: '' }, DEFAULT_HEADING)],
    ];

    function chip(dow, day) {
        return el('span', { className: 'jtsi-date-chip jtsi-date-chip--preview' },
            el('span', { className: 'jtsi-date-chip__dow' }, dow),
            el('span', { className: 'jtsi-date-chip__day' }, day),
            el('span', { className: 'jtsi-date-chip__price' }, '—'));
    }

    registerBlockType('jankx/tour-service-info', {
        edit: function (props) {
            var attrs    = props.attributes;
            var setAttr  = props.setAttributes;

            // Seed the inner heading with the legacy title attribute so existing
            // content keeps its heading.
            if (attrs.title && headingTemplate[0][1].content === '') {
                headingTemplate[0][1] = Object.assign({}, headingTemplate[0][1], { content: attrs.title });
            }

            var preview = el('div', { className: 'jtsi-editor-preview' },
                el('div', { className: 'jtsi-section' },
                    el('p', { className: 'jtsi-section__label' }, __('Chọn ngày', 'jankx')),
                    el('div', { className: 'jtsi-date-chips' },
                        chip('Th2', '12'),
                        chip('Th3', '13'),
                        chip('Th4', '14'),
                        el('span', { className: 'jtsi-date-chip jtsi-date-chip--preview' },
                            el('span', { className: 'jtsi-date-chip__dow' }, __('Tất cả', 'jankx')),
                            el('span', { className: 'jtsi-date-chip__icon', 'aria-hidden': 'true' }, '⁝'))
                    )
                ),
                el('div', { className: 'jtsi-section jtsi-section--qty' },
                    el('p', { className: 'jtsi-section__label' }, __('Chọn số lượng', 'jankx')),
                    el('div', { className: 'jtsi-qty-row' },
                        el('span', { className: 'jtsi-qty-row__label' }, __('Người lớn', 'jankx')),
                        el('span', { className: 'jtsi-qty-row__price' }, '—'),
                        el('div', { className: 'jtsi-qty-row__stepper' }, el('span', { className: 'jtsi-stepper-btn' }, '−')))
                ),
                el('div', { className: 'jtsi-footer' },
                    el('span', { className: 'jtsi-footer__total' }, '0 ₫'),
                    el('span', { className: 'jtsi-btn jtsi-btn--outline' }, __('Thêm vào giỏ hàng', 'jankx')),
                    el('span', { className: 'jtsi-btn jtsi-btn--primary' }, __('Đặt ngay', 'jankx'))
                )
            );

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: __('Cài đặt block', 'jankx'), initialOpen: true },
                        el(ToggleControl, {
                            label:    __('Hiện nút "Tất cả"', 'jankx'),
                            checked:  attrs.showCalendarLink,
                            onChange: function (v) { setAttr({ showCalendarLink: v }); },
                        })
                    )
                ),
                el('div', { key: 'preview', className: 'jtsi-editor' },
                    el('div', { className: 'jtsi-editor-heading' },
                        el(InnerBlocks, {
                            allowedBlocks: ['core/heading', 'core/paragraph'],
                            template: headingTemplate,
                            templateLock: false,
                        })
                    ),
                    preview
                )
            ];
        },
        save: function () {
            return el(InnerBlocks.Content);
        },
    });
})(window.wp);