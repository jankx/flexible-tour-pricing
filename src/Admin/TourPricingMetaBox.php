<?php

namespace Jankx\Extensions\TourPricing\Admin;

use Jankx\Extensions\TourPricing\Constants;
use Jankx\Extensions\TourPricing\Pricing\PriceRepository;
use Jankx\Extensions\TourPricing\Settings;

/**
 * Meta box shown on the tour editor: a calendar (per departure date) to set
 * the price for each passenger group.
 *
 * @package Jankx\Extensions\TourPricing\Admin
 */
class TourPricingMetaBox
{
    const NONCE_ACTION = 'jankx_tour_pricing_meta';
    const NONCE_NAME = 'jankx_tour_pricing_meta_nonce';

    const FIELD_CALENDAR = 'tour_price_calendar';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . Constants::TOUR_POST_TYPE, [$this, 'save']);
    }

    public function add_meta_boxes(): void
    {
        if (!Settings::isEnabled()) {
            return;
        }

        add_meta_box(
            'jankx_tour_price_calendar',
            __('Giá theo ngày khởi hành', 'jankx'),
            [$this, 'render'],
            Constants::TOUR_POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $departures = get_post_meta($post->ID, '_tour_departures', true);
        $departures = is_array($departures) ? $departures : [];

        $calendar = PriceRepository::get($post->ID);
        $groups = Settings::getGroups();
        $baseGroup = Settings::getBaseGroup();
        $basePrice = PriceRepository::getBasePrice($post->ID);

        include __DIR__ . '/views/price-calendar.php';
    }

    public function save(int $postId): void
    {
        if (
            !isset($_POST[self::NONCE_NAME]) ||
            !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)
        ) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $calendar = PriceRepository::get($postId);

        // Incoming per-date prices overwrite the stored values.
        $input = isset($_POST[self::FIELD_CALENDAR]) && is_array($_POST[self::FIELD_CALENDAR])
            ? $_POST[self::FIELD_CALENDAR]
            : [];

        // Dates expected after this save: every date submitted in the
        // calendar plus any brand-new departure dates added by the departure
        // repeater in the same request. Dates that are in the stored calendar
        // but no longer rendered (departure removed) are dropped.
        $expectedDates = array_keys($input);
        if (!empty($_POST['tour_departure_date']) && is_array($_POST['tour_departure_date'])) {
            foreach ($_POST['tour_departure_date'] as $departureDate) {
                $departureDate = sanitize_text_field((string) $departureDate);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $departureDate)) {
                    $expectedDates[] = $departureDate;
                }
            }
        }
        $expectedDates = array_values(array_unique($expectedDates));

        foreach ($calendar as $date => $prices) {
            if (!in_array((string) $date, $expectedDates, true)) {
                unset($calendar[$date]);
            }
        }

        $groupIds = Settings::getGroupIds();

        foreach ($input as $date => $values) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                continue;
            }

            $values = is_array($values) ? $values : [];

            $clean = [];
            foreach ($groupIds as $groupId) {
                $raw = isset($values[$groupId]) ? (int) $values[$groupId] : 0;
                $clean[$groupId] = max(0, $raw);
            }

            $calendar[(string) $date] = $clean;
        }

        PriceRepository::save($postId, $calendar);
    }
}