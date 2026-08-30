<?php

namespace Jankx\Extensions\TourPricing\Admin;

use Jankx\Extensions\TourPricing\Constants;
use Jankx\Extensions\TourPricing\Settings;

/**
 * Admin settings page: enable the extension and configure the passenger
 * groups used by the date-based pricing engine.
 *
 * @package Jankx\Extensions\TourPricing\Admin
 */
class SettingsPage
{
    const OPTION_GROUP = 'tour_pricing_settings';

    public function register(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_page']);
    }

    public function register_settings(): void
    {
        register_setting(self::OPTION_GROUP, 'jankx_options', [
            'type'              => 'array',
            'default'           => [],
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
    }

    /**
     * Merge only this extension's keys into the shared jankx_options array so
     * other extensions' settings are never wiped out.
     */
    public function sanitize($input)
    {
        $input = is_array($input) ? $input : [];
        $current = get_option('jankx_options', []);
        $current = is_array($current) ? $current : [];

        $current[Settings::FIELD_ENABLED] = !empty($input[Settings::FIELD_ENABLED]) ? 1 : 0;

        $baseGroup = isset($input[Settings::FIELD_BASE_GROUP])
            ? sanitize_key((string) $input[Settings::FIELD_BASE_GROUP])
            : Settings::DEFAULT_BASE_GROUP;

        $groups = $this->sanitizeGroups($input[Settings::FIELD_GROUPS] ?? []);
        if (empty($groups)) {
            $groups = Settings::getDefaultGroups();
        }

        // Ensure the base group actually exists among the configured groups.
        $ids = array_column($groups, 'id');
        if (!in_array($baseGroup, $ids, true)) {
            $baseGroup = $groups[0]['id'];
        }
        $current[Settings::FIELD_BASE_GROUP] = $baseGroup;
        $current[Settings::FIELD_GROUPS] = $groups;

        return $current;
    }

    protected function sanitizeGroups($input): array
    {
        $input = is_array($input) ? $input : [];

        $groups = [];
        $seen = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $fallback = isset($row['fallback']) && $row['fallback'] === 'base' ? 'base' : 'zero';
            $groups[] = [
                'id'       => $id,
                'label'    => sanitize_text_field((string) ($row['label'] ?? $id)),
                'min_age'  => isset($row['min_age']) && $row['min_age'] !== '' ? absint($row['min_age']) : '',
                'max_age'  => isset($row['max_age']) && $row['max_age'] !== '' ? absint($row['max_age']) : '',
                'fallback' => $fallback,
            ];
        }

        return $groups;
    }

    public function register_page(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . Constants::TOUR_POST_TYPE,
            __('Giá tour theo ngày', 'jankx'),
            __('Giá theo ngày', 'jankx'),
            'manage_options',
            'tour-pricing',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $saved = Settings::getAll();
        $groups = Settings::getGroups();
        $baseGroup = Settings::getBaseGroup();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Giá tour theo ngày khởi hành', 'jankx'); ?></h1>
            <p>
                <?php esc_html_e('Cấu hình nhóm khách và cách tính giá mặc định. Giá chi tiết theo từng ngày được thiết lập trong chính từng tour (mục "Giá theo ngày khởi hành").', 'jankx'); ?>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Bật tính năng', 'jankx'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="jankx_options[<?php echo esc_attr(Settings::FIELD_ENABLED); ?>]"
                                    value="1" <?php checked(!empty($saved[Settings::FIELD_ENABLED])); ?> />
                                <?php esc_html_e('Cho phép thiết lập giá theo từng ngày khởi hành.', 'jankx'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Nhóm giá gốc', 'jankx'); ?></th>
                        <td>
                            <select name="jankx_options[<?php echo esc_attr(Settings::FIELD_BASE_GROUP); ?>]">
                                <?php foreach ($groups as $group) : ?>
                                    <option value="<?php echo esc_attr($group['id']); ?>" <?php selected($baseGroup, $group['id']); ?>>
                                        <?php echo esc_html($group['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Nhóm dùng giá gốc _tour_price làm mặc định khi một ngày chưa được thiết lập giá.', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Các nhóm khách', 'jankx'); ?></th>
                        <td>
                            <table class="widefat striped jankx-tour-pricing-groups-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('ID', 'jankx'); ?></th>
                                        <th><?php esc_html_e('Tên nhóm', 'jankx'); ?></th>
                                        <th><?php esc_html_e('Tuổi tối thiểu', 'jankx'); ?></th>
                                        <th><?php esc_html_e('Tuổi tối đa', 'jankx'); ?></th>
                                        <th><?php esc_html_e('Mặc định khi chưa có giá', 'jankx'); ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groups as $i => $group) : ?>
                                        <tr>
                                            <td>
                                                <input type="text"
                                                    name="jankx_options[<?php echo esc_attr(Settings::FIELD_GROUPS); ?>][<?php echo esc_attr($i); ?>][id]"
                                                    value="<?php echo esc_attr($group['id']); ?>"
                                                    class="regular-text" style="width:90px" />
                                            </td>
                                            <td>
                                                <input type="text"
                                                    name="jankx_options[<?php echo esc_attr(Settings::FIELD_GROUPS); ?>][<?php echo esc_attr($i); ?>][label]"
                                                    value="<?php echo esc_attr($group['label']); ?>" class="regular-text" />
                                            </td>
                                            <td>
                                                <input type="number" min="0"
                                                    name="jankx_options[<?php echo esc_attr(Settings::FIELD_GROUPS); ?>][<?php echo esc_attr($i); ?>][min_age]"
                                                    value="<?php echo esc_attr($group['min_age']); ?>" style="width:70px" />
                                            </td>
                                            <td>
                                                <input type="number" min="0"
                                                    name="jankx_options[<?php echo esc_attr(Settings::FIELD_GROUPS); ?>][<?php echo esc_attr($i); ?>][max_age]"
                                                    value="<?php echo esc_attr($group['max_age']); ?>" style="width:70px" />
                                            </td>
                                            <td>
                                                <select
                                                    name="jankx_options[<?php echo esc_attr(Settings::FIELD_GROUPS); ?>][<?php echo esc_attr($i); ?>][fallback]">
                                                    <option value="zero" <?php selected($group['fallback'], 'zero'); ?>><?php esc_html_e('0 (miễn phí)', 'jankx'); ?></option>
                                                    <option value="base" <?php selected($group['fallback'], 'base'); ?>><?php esc_html_e('Giá gốc tour', 'jankx'); ?></option>
                                                </select>
                                            </td>
                                            <td>
                                                <button type="button"
                                                    class="button-link jankx-tour-pricing-groups-remove"><?php esc_html_e('Xoá', 'jankx'); ?></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="description">
                                <?php esc_html_e('"Tuổi tối thiểu / tối đa" để trống nghĩa là không giới hạn.', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}