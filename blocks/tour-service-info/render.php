<?php
/**
 * Server-side render: jankx/tour-service-info block
 *
 * Renders the "Thông tin gói dịch vụ" card:
 *   – Date chip row  (next 6 upcoming departures + "Tất cả" button)
 *   – Group-qty stepper rows  (Người lớn, Trẻ em …)
 *   – Footer bar  (total price  |  Thêm vào giỏ hàng  |  Đặt ngay)
 *
 * All live price / quantity logic is handled by the existing frontend.js
 * (jankx-tour-pricing-frontend).  This template just provides the markup.
 *
 * @package Jankx\Extensions\TourPricing
 */

use Jankx\Extensions\TourPricing\Pricing\PriceComputer;
use Jankx\Extensions\TourPricing\Settings;
use Jankx\Extensions\TourPricing\Constants;

// Resolve the tour ID: attribute → current singular post.
$tourId = (int) ($attributes['tourId'] ?? 0);
if (!$tourId) {
    $tourId = get_the_ID() ?: get_queried_object_id();
}
if (!$tourId || get_post_type($tourId) !== Constants::TOUR_POST_TYPE) {
    // In the editor preview show a placeholder instead of nothing.
    if (defined('REST_REQUEST') && REST_REQUEST) {
        echo '<div class="jtsi-placeholder">' . esc_html__('Thông tin gói dịch vụ (cần mở trong trang tour)', 'jankx') . '</div>';
    }
    return;
}

$title       = !empty($attributes['title']) ? $attributes['title'] : __('Thông tin gói dịch vụ', 'jankx');
$showCalLink = !empty($attributes['showCalendarLink']);
$today       = current_time('Y-m-d');
$groups      = Settings::getGroups();         // [['id'=>'adult','label'=>'Người lớn'], …]
$baseGroup   = Settings::getBaseGroup();

/* ── Departures ────────────────────────────────────────────────── */
$allDepartures = get_post_meta($tourId, '_tour_departures', true);
$allDepartures = is_array($allDepartures) ? $allDepartures : [];

$futureDates = [];
foreach ($allDepartures as $row) {
    $d = (string) ($row['date'] ?? '');
    if ($d && $d >= $today) {
        $futureDates[$d] = $row;
    }
}
ksort($futureDates);

// Up to 6 chips; rest accessible via "Tất cả" popover (handled by JS).
$chipDates  = array_slice($futureDates, 0, 6, true);

/* ── Prices for chip dates ─────────────────────────────────────── */
$chipPrices = [];
foreach ($chipDates as $date => $row) {
    $price = PriceComputer::getGroupPrice($tourId, $date, $baseGroup);
    $chipPrices[$date] = $price;
}

/* ── Block wrapper attrs ───────────────────────────────────────── */
$blockAttrs = get_block_wrapper_attributes(['class' => 'jtsi-block']);
?>
<div <?php echo $blockAttrs; ?>
     data-tour-id="<?php echo esc_attr($tourId); ?>"
     data-rest-url="<?php echo esc_url(rest_url('jankx/tour-pricing/v1')); ?>">

    <h3 class="jtsi-title"><?php echo esc_html($title); ?></h3>

    <?php if (!empty($chipDates)) : ?>
    <!-- ── Date chip section ──────────────────────────────────── -->
    <div class="jtsi-section">
        <p class="jtsi-section__label"><?php esc_html_e('Chọn ngày', 'jankx'); ?></p>
        <div class="jtsi-date-chips" role="radiogroup" aria-label="<?php esc_attr_e('Chọn ngày khởi hành', 'jankx'); ?>">
            <?php foreach ($chipDates as $date => $row) :
                $ts    = strtotime($date);
                $day   = date_i18n('j', $ts);
                $month = date_i18n('N', $ts);   // day-of-week number → convert below
                $dow   = (int) date_i18n('N', $ts); // 1=Mon … 7=Sun
                $dowLabels = [1=>'Th2',2=>'Th3',3=>'Th4',4=>'Th5',5=>'Th6',6=>'Th7',7=>'CN'];
                $dowLabel  = $dowLabels[$dow] ?? '';
                $price     = $chipPrices[$date] ?? 0;
                $priceLabel = $price > 0
                    ? number_format($price / 1000, 0, ',', '.') . 'K'
                    : '—';
            ?>
            <button type="button"
                    class="jtsi-date-chip"
                    data-date="<?php echo esc_attr($date); ?>"
                    aria-pressed="false">
                <span class="jtsi-date-chip__dow"><?php echo esc_html($dowLabel); ?></span>
                <span class="jtsi-date-chip__day"><?php echo esc_html($day); ?></span>
                <span class="jtsi-date-chip__price"><?php echo esc_html($priceLabel); ?></span>
            </button>
            <?php endforeach; ?>

            <?php if ($showCalLink && count($futureDates) > 6) : ?>
            <button type="button" class="jtsi-date-chip jtsi-date-chip--all" data-action="show-all">
                <span class="jtsi-date-chip__dow"><?php esc_html_e('Tất cả', 'jankx'); ?></span>
                <span class="jtsi-date-chip__icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Group qty section ──────────────────────────────────── -->
    <?php if (!empty($groups)) : ?>
    <div class="jtsi-section jtsi-section--qty">
        <p class="jtsi-section__label"><?php esc_html_e('Chọn số lượng', 'jankx'); ?></p>
        <?php foreach ($groups as $group) :
            $gId    = $group['id'];
            $gLabel = $group['label'];
            $initQty = ($gId === $baseGroup) ? 1 : 0;
        ?>
        <div class="jtsi-qty-row" data-group="<?php echo esc_attr($gId); ?>">
            <span class="jtsi-qty-row__label"><?php echo esc_html($gLabel); ?></span>
            <span class="jtsi-qty-row__price" data-group-price="<?php echo esc_attr($gId); ?>">—</span>
            <div class="jtsi-qty-row__stepper">
                <button type="button" class="jtsi-stepper-btn" data-action="dec" aria-label="<?php esc_attr_e('Giảm', 'jankx'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
                <input type="number"
                       class="jtsi-qty-input"
                       name="group_qty[<?php echo esc_attr($gId); ?>]"
                       value="<?php echo esc_attr($initQty); ?>"
                       min="0" step="1"
                       aria-label="<?php echo esc_attr($gLabel); ?>">
                <button type="button" class="jtsi-stepper-btn" data-action="inc" aria-label="<?php esc_attr_e('Tăng', 'jankx'); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Footer bar ────────────────────────────────────────── -->
    <div class="jtsi-footer">
        <span class="jtsi-footer__total" aria-live="polite">0 ₫</span>
        <div class="jtsi-footer__actions">
            <?php
            $cartUrl = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '#';
            ?>
            <button type="button"
                    class="jtsi-btn jtsi-btn--outline jtsi-btn--add-cart"
                    data-tour-id="<?php echo esc_attr($tourId); ?>">
                <?php esc_html_e('Thêm vào giỏ hàng', 'jankx'); ?>
            </button>
            <button type="button"
                    class="jtsi-btn jtsi-btn--primary jtsi-btn--book-now"
                    data-tour-id="<?php echo esc_attr($tourId); ?>">
                <?php esc_html_e('Đặt ngay', 'jankx'); ?>
            </button>
        </div>
    </div>
    <?php else : ?>
    <p class="jtsi-empty"><?php esc_html_e('Hiện chưa có lịch khởi hành nào.', 'jankx'); ?></p>
    <?php endif; ?>

    <!-- Hidden date input wired by JS -->
    <input type="hidden" name="departure_date" class="jtsi-hidden-date" value="">
</div>
