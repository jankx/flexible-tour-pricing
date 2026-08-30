<?php
/** @var array $departures */
/** @var array $calendar */
/** @var array $groups */
/** @var string $baseGroup */
/** @var float $basePrice */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="jankx-tour-pricing-calendar">
    <p class="description">
        <?php esc_html_e('Nhập giá (VNĐ) cho từng ngày khởi hành theo nhóm khách.', 'jankx'); ?>
        <?php if ($basePrice > 0) : ?>
            <span class="jankx-tour-pricing-calendar__base">
                <?php echo esc_html(sprintf('Giá gốc tour: %s.', number_format($basePrice, 0, ',', '.') . '₫')); ?>
            </span>
        <?php endif; ?>
    </p>

    <?php if (empty($departures)) : ?>
        <p class="jankx-tour-pricing-calendar__empty">
            <?php esc_html_e('Chưa có ngày khởi hành. Vui lòng thêm lịch khởi hành ở mục "Lịch khởi hành" phía trên (giá theo từng ngày được thiết lập theo các ngày khởi hành).', 'jankx'); ?>
        </p>
    <?php else : ?>
        <table class="form-table jankx-travel-form-table jankx-tour-pricing-calendar__table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Ngày khởi hành', 'jankx'); ?></th>
                    <?php foreach ($groups as $group) : ?>
                        <th>
                            <?php echo esc_html($group['label']); ?>
                            <?php if (!empty($group['min_age']) || $group['min_age'] === 0) : ?>
                                <span class="jankx-tour-pricing-calendar__age">(
                                    <?php
                                    if ($group['min_age'] === null && $group['max_age'] === null) {
                                        esc_html_e('mọi lứa tuổi', 'jankx');
                                    } elseif ($group['min_age'] !== null && $group['max_age'] !== null) {
                                        echo esc_html(sprintf(__('%d–%d tuổi', 'jankx'), $group['min_age'], $group['max_age']));
                                    } elseif ($group['min_age'] !== null) {
                                        echo esc_html(sprintf(__('từ %d tuổi', 'jankx'), $group['min_age']));
                                    } else {
                                        echo esc_html(sprintf(__('đến %d tuổi', 'jankx'), $group['max_age']));
                                    }
                                    ?>
                                )</span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                usort($departures, function ($a, $b) {
                    return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
                });
                foreach ($departures as $row) :
                    $date = (string) ($row['date'] ?? '');
                    if (empty($date)) {
                        continue;
                    }
                    $prices = is_array($calendar[$date] ?? null) ? $calendar[$date] : [];
                    ?>
                    <tr data-date="<?php echo esc_attr($date); ?>">
                        <td>
                            <div class="jankx-tour-pricing-calendar__date">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($date))); ?>
                            </div>
                            <?php if (isset($row['note']) && $row['note'] !== '') : ?>
                                <div class="jankx-tour-pricing-calendar__note"><?php echo esc_html($row['note']); ?></div>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($groups as $group) :
                            $groupId = $group['id'];
                            $value = isset($prices[$groupId]) && $prices[$groupId] > 0 ? (int) $prices[$groupId] : '';
                            ?>
                            <td>
                                <input type="number"
                                    step="1000"
                                    min="0"
                                    class="jankx-tour-pricing-calendar__price"
                                    name="<?php echo esc_attr(sprintf('%s[%s][%s]', TourPricingMetaBox::FIELD_CALENDAR, $date, $groupId)); ?>"
                                    value="<?php echo esc_attr($value); ?>"
                                    placeholder="<?php echo esc_attr($groupId === $baseGroup && $basePrice > 0 ? number_format($basePrice) : '0'); ?>"
                                    <?php echo $groupId === $baseGroup && empty($prices) && $basePrice > 0 ? 'data-fallback="' . esc_attr((string) $basePrice) . '"' : ''; ?>
                                />
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <button type="button" class="button-link jankx-tour-pricing-calendar__reset" data-date="<?php echo esc_attr($date); ?>">
                                <?php esc_html_e('Xoá giá', 'jankx'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>