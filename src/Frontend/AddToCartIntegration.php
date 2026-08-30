<?php

namespace Jankx\Extensions\TourPricing\Frontend;

use Jankx\Extensions\TourPricing\Constants;
use Jankx\Extensions\TourPricing\Pricing\PriceComputer;
use Jankx\Extensions\TourPricing\Settings;

/**
 * Integrates date-based pricing into the shared e-commerce add-to-cart flow.
 *
 * Replaces the default single-price / single-quantity add-to-cart fields on
 * a tour with a departure-date selector plus per-group quantity inputs, and
 * makes sure the cart subtotal is computed from the actual group breakdown.
 *
 * @package Jankx\Extensions\TourPricing\Frontend
 */
class AddToCartIntegration
{
    public function register(): void
    {
        add_filter('jankx/ecommerce/add_to_cart/form', [$this, 'replaceFormFields'], 10, 5);
        add_filter('jankx/ecommerce/cart/item/subtotal', [$this, 'cartItemSubtotal'], 10, 2);
        add_filter('jankx/travel/tour/starting_price', [$this, 'startingPrice'], 10, 2);
        add_filter('jankx/travel/departure_calendar/price', [$this, 'departureCalendarPrice'], 10, 3);
    }

    /**
     * Replace the default add-to-cart fields with a date + group selector when
     * the product is a tour that has configured departures.
     *
     * @param string $formBody
     * @param object|null $product
     * @param int $postId
     * @param string $productType
     * @param array $attributes
     */
    public function replaceFormFields($formBody, $product, $postId, $productType, $attributes = [])
    {
        if (!Settings::isEnabled()) {
            return $formBody;
        }

        if ($productType !== Constants::TOUR_POST_TYPE) {
            return $formBody;
        }

        $departures = get_post_meta((int) $postId, '_tour_departures', true);
        $departures = is_array($departures) ? $departures : [];
        if (empty($departures)) {
            return $formBody;
        }

        $groups = Settings::getGroups();
        $baseGroup = Settings::getBaseGroup();
        $today = current_time('Y-m-d');

        // Only offer future departure dates as selectable options.
        $options = [];
        foreach ($departures as $row) {
            $date = (string) ($row['date'] ?? '');
            if (empty($date) || $date < $today) {
                continue;
            }
            $options[$date] = trim((string) ($row['note'] ?? ''));
        }
        ksort($options);

        if (empty($options)) {
            return $formBody;
        }

        $optionsMarkup = '';
        foreach ($options as $date => $note) {
            $label = date_i18n(get_option('date_format'), strtotime($date));
            if ($note) {
                $label .= ' — ' . $note;
            }
            $optionsMarkup .= '<option value="' . esc_attr($date) . '">' . esc_html($label) . '</option>';
        }

        $groupsMarkup = '';
        foreach ($groups as $i => $group) {
            $groupId = $group['id'];
            $value = $groupId === $baseGroup ? 1 : 0;
            $groupsMarkup .= '<div class="jankx-tour-pricing-group">'
                . '<label for="jankx-add-to-cart-group-' . esc_attr($groupId) . '-' . esc_attr($postId) . '">'
                . esc_html($group['label']) . '</label>'
                . '<input type="number" min="0" step="1" value="' . esc_attr($value) . '" '
                . 'name="group_qty[' . esc_attr($groupId) . ']" '
                . 'id="jankx-add-to-cart-group-' . esc_attr($groupId) . '-' . esc_attr($postId) . '" '
                . 'class="jankx-input jankx-tour-pricing-qty" />'
                . '</div>';
        }

        $wrapper = '<div class="jankx-add-to-cart__field jankx-tour-pricing-departure">'
            . '<label for="jankx-departure-' . esc_attr($postId) . '">'
            . esc_html__('Ngày khởi hành', 'jankx') . '</label>'
            . '<select id="jankx-departure-' . esc_attr($postId) . '" name="departure_date" class="jankx-input jankx-tour-pricing-departure__select" '
            . 'data-tour-id="' . esc_attr($postId) . '">'
            . '<option value="">' . esc_html__('— Chọn ngày —', 'jankx') . '</option>'
            . $optionsMarkup
            . '</select>'
            . '</div>';

        $wrapper .= '<div class="jankx-tour-pricing-groups">' . $groupsMarkup . '</div>';

        $wrapper .= '<div class="jankx-add-to-cart__row">'
            . '<span class="jankx-add-to-cart__price jankx-tour-pricing-total" data-tour-id="' . esc_attr($postId) . '">—</span>'
            . '<button type="submit" class="jankx-btn jankx-btn-primary jankx-add-to-cart__btn">'
            . esc_html__('Thêm vào giỏ hàng', 'jankx') . '</button>'
            . '</div>';

        return $wrapper;
    }

    /**
     * Recompute the cart line subtotal from the group quantity breakdown when
     * present on the item's args.
     *
     * @param float $subtotal
     * @param \Jankx\Extensions\Ecommerce\Cart\CartItem $cartItem
     */
    public function cartItemSubtotal($subtotal, $cartItem): float
    {
        if (!Settings::isEnabled()) {
            return (float) $subtotal;
        }

        $args = $cartItem->getArgs();
        $date = (string) ($args['departure_date'] ?? '');
        $qtyMap = is_array($args['group_qty'] ?? null) ? $args['group_qty'] : [];

        if ($date === '' && empty($qtyMap)) {
            return (float) $subtotal;
        }

        $tourId = $cartItem->getProductId();
        if (get_post_type($tourId) !== Constants::TOUR_POST_TYPE) {
            return (float) $subtotal;
        }

        $prices = PriceComputer::getPricesForDate($tourId, $date);
        $qty = PriceComputer::normalizeQuantities($qtyMap);

        return PriceComputer::calculateSubtotal($prices, $qty);
    }

    /**
     * "Từ {price}" starting price for the tour across scheduled dates.
     *
     * @param mixed $price
     * @param int $postId
     */
    public function startingPrice($price, $postId): string
    {
        if (!Settings::isEnabled() || empty($postId)) {
            return $price;
        }

        if (get_post_type((int) $postId) !== Constants::TOUR_POST_TYPE) {
            return $price;
        }

        $min = PriceComputer::getStartingPrice((int) $postId);

        if ($min > 0) {
            return (string) round($min);
        }

        return $price;
    }

    /**
     * Render the price for a single departure date inside the departure
     * calendar block.
     *
     * @param mixed $current
     * @param int $tourId
     * @param string $date
     */
    public function departureCalendarPrice($current, $tourId, $date): string
    {
        if (!Settings::isEnabled()) {
            return $current;
        }

        $tourId = (int) $tourId;
        if (!$tourId || get_post_type($tourId) !== Constants::TOUR_POST_TYPE) {
            return $current;
        }

        $baseGroup = Settings::getBaseGroup();
        $price = PriceComputer::getGroupPrice($tourId, $date, $baseGroup);

        if ($price > 0) {
            return esc_html($this->formatPrice($price));
        }

        return $current;
    }

    protected function formatPrice(float $price, string $currency = 'VND'): string
    {
        if (class_exists('\Jankx\Extensions\Ecommerce\Currency\CurrencyManager')
            && method_exists('\Jankx\Extensions\Ecommerce\Currency\CurrencyManager', 'formatPrice')
        ) {
            return \Jankx\Extensions\Ecommerce\Currency\CurrencyManager::formatPrice($price);
        }

        if (function_exists('jankx_currency_format')) {
            return jankx_currency_format($price, $currency);
        }

        return number_format($price, 0, ',', '.') . '₫';
    }
}