<?php
/** @var array $departures */
/** @var array $calendar */
/** @var array $groups */
/** @var string $baseGroup */
/** @var float $basePrice */
/** @var string $fieldCalendar */
if (!defined('ABSPATH')) {
    exit;
}
$today = current_time('Y-m-d');

// Build lookups from the departure list.
$departureSet = [];
foreach ($departures as $row) {
    $date = (string) ($row['date'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $departureSet[$date] = true;
    }
}

// A departure day "has a price" when any configured group has a value > 0.
function jankx_tp_cal_has_price(array $calendar, string $date): bool
{
    $prices = $calendar[$date] ?? [];
    if (!is_array($prices)) {
        return false;
    }
    foreach ($prices as $price) {
        if ((int) $price > 0) {
            return true;
        }
    }
    return false;
}

function jankx_tp_cal_compact_price(float $price): string
{
    if ($price >= 1000000) {
        $v = number_format($price / 1000000, 1, '.', '');
        return rtrim(rtrim($v, '0'), '.') . 'tr';
    }
    if ($price >= 1000) {
        return rtrim(rtrim(number_format($price / 1000, 1, '.', ''), '0'), '.') . 'k';
    }
    return (string) round($price);
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

    <?php if (empty($departureSet)) : ?>
        <p class="jankx-tour-pricing-calendar__empty">
            <?php esc_html_e('Chưa có ngày khởi hành. Vui lòng thêm lịch khởi hành ở mục "Lịch khởi hành" phía trên (giá theo từng ngày được thiết lập theo các ngày khởi hành).', 'jankx'); ?>
        </p>
    <?php else : ?>
        <?php
        // Collect the months spanned by the departures, newest last.
        $monthSet = [];
        foreach (array_keys($departureSet) as $date) {
            $monthSet[substr($date, 0, 7)] = true;
        }
        ksort($monthSet);
        $months = array_keys($monthSet);
        $activeMonth = $monthSet[substr($today, 0, 7)] ?? null ? substr($today, 0, 7) : ($months[0] ?? '');
        $calendarHasPrice = function (string $date) use ($calendar) {
            return jankx_tp_cal_has_price($calendar, $date);
        };
        $pricedCount = 0;
        foreach (array_keys($departureSet) as $date) {
            if ($calendarHasPrice($date)) {
                $pricedCount++;
            }
        }
        ?>
        <div class="jankx-tour-pricing-cal" data-active-month="<?php echo esc_attr($activeMonth); ?>">
            <div class="jankx-tour-pricing-cal__nav">
                <button type="button" class="button jankx-tour-pricing-cal__nav--prev" aria-label="<?php esc_attr_e('Tháng trước', 'jankx'); ?>">&larr;</button>
                <select class="jankx-tour-pricing-cal__jump" aria-label="<?php esc_attr_e('Chuyển nhanh tới tháng', 'jankx'); ?>">
                    <?php foreach ($months as $monthOption) : ?>
                        <option value="<?php echo esc_attr($monthOption); ?>" <?php selected($monthOption, $activeMonth); ?>>
                            <?php echo esc_html(date_i18n('m/Y', strtotime($monthOption . '-01'))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button jankx-tour-pricing-cal__nav--next" aria-label="<?php esc_attr_e('Tháng sau', 'jankx'); ?>">&rarr;</button>
            </div>

            <?php foreach ($months as $monthKey) : ?>
                <?php
                [$year, $monthNumber] = [substr($monthKey, 0, 4), substr($monthKey, 5, 2)];
                $monthFirst = new DateTime($year . '-' . $monthNumber . '-01');
                $daysInMonth = (int) $monthFirst->format('t');
                $startCol = ((int) $monthFirst->format('N') - 1 + 7) % 7;
                ?>
                <section class="jankx-tour-pricing-cal__month" data-month="<?php echo esc_attr($monthKey); ?>">
                    <h4 class="jankx-tour-pricing-cal__month-title"><?php echo esc_html(date_i18n('F Y', $monthFirst->getTimestamp())); ?></h4>
                    <div class="jankx-tour-pricing-cal__grid">
                        <?php foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $dow) : ?>
                            <span class="jankx-tour-pricing-cal__dow"><?php echo esc_html($dow); ?></span>
                        <?php endforeach; ?>

                        <?php for ($i = 0; $i < $startCol; $i++) : ?>
                            <span class="jankx-tour-pricing-cal__day is-empty"></span>
                        <?php endfor; ?>

                        <?php for ($day = 1; $day <= $daysInMonth; $day++) : ?>
                            <?php
                            $date = sprintf('%04d-%02d-%02d', (int) $year, (int) $monthNumber, $day);
                            if (!isset($departureSet[$date])) :
                                ?>
                                <span class="jankx-tour-pricing-cal__day is-muted"><span class="jankx-tour-pricing-cal__num"><?php echo esc_html($day); ?></span></span>
                            <?php else : ?>
                                <?php
                                $prices = is_array($calendar[$date] ?? null) ? $calendar[$date] : [];
                                $baseValue = (int) ($prices[$baseGroup] ?? 0);
                                $hasPrice = $calendarHasPrice($date);
                                $chip = '';
                                if ($baseValue > 0) {
                                    $chip = jankx_tp_cal_compact_price($baseValue);
                                } elseif ($hasPrice) {
                                    foreach ($prices as $value) {
                                        if ((int) $value > 0) {
                                            $chip = jankx_tp_cal_compact_price((float) $value);
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <button type="button"
                                    class="jankx-tour-pricing-cal__day is-departure<?php echo $hasPrice ? ' is-priced' : ''; ?>"
                                    data-date="<?php echo esc_attr($date); ?>"
                                    title="<?php echo esc_attr($hasPrice ? sprintf(__('Ngày %s — đã đặt giá', 'jankx'), date_i18n(get_option('date_format'), strtotime($date))) : sprintf(__('Ngày %s — chưa đặt giá', 'jankx'), date_i18n(get_option('date_format'), strtotime($date)))); ?>">
                                    <span class="jankx-tour-pricing-cal__num"><?php echo esc_html($day); ?></span>
                                    <?php if ($chip) : ?>
                                        <span class="jankx-tour-pricing-cal__price-chip"><?php echo esc_html($chip); ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <p class="jankx-tour-pricing-cal__legend">
                <span class="jankx-tour-pricing-cal__swatch is-priced"></span>
                <?php echo esc_html(sprintf(__('Ngày khởi hành có giá (%d/%d)', 'jankx'), $pricedCount, count($departureSet))); ?>
                <span class="jankx-tour-pricing-cal__swatch"></span>
                <?php esc_html_e('Ngày khởi hành chưa đặt giá', 'jankx'); ?>
            </p>
        </div>

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
                    <tr data-date="<?php echo esc_attr($date); ?>" class="jankx-tour-pricing-calendar__row">
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
                                    name="<?php echo esc_attr(sprintf('%s[%s][%s]', $fieldCalendar, $date, $groupId)); ?>"
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