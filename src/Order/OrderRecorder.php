<?php

namespace Jankx\Extensions\TourPricing\Order;

use Jankx\Extensions\TourPricing\Constants;
use Jankx\Extensions\TourPricing\Pricing\PriceComputer;
use Jankx\Extensions\TourPricing\Settings;

/**
 * Records the full date/group price breakdown so every order (and booking
 * request) carries an audit trail of exactly what the customer paid for,
 * providing the data foundation for later financial reporting.
 *
 * @package Jankx\Extensions\TourPricing\Order
 */
class OrderRecorder
{
    public function register(): void
    {
        add_filter('jankx/ecommerce/order/item_data', [$this, 'orderItemData'], 10, 3);
        add_filter('jankx/ecommerce/order/item_total', [$this, 'orderItemTotal'], 10, 2);
        add_action('jankx/travel/booking_request_created', [$this, 'bookingRequestBreakdown'], 10, 2);
    }

    /**
     * Enrich an order item with the group price breakdown coming from the
     * cart item args (departure_date + group_qty).
     *
     * @param array $item
     * @param \Jankx\Extensions\Ecommerce\Cart\CartItem $cartItem
     * @param object|null $product
     */
    public function orderItemData(array $item, $cartItem, $product = null): array
    {
        if (!Settings::isEnabled()) {
            return $item;
        }

        $args = is_callable([$cartItem, 'getArgs']) ? $cartItem->getArgs() : [];
        $date = (string) ($args['departure_date'] ?? '');
        $qtyMap = is_array($args['group_qty'] ?? null) ? $args['group_qty'] : [];

        if ($date === '' || empty($qtyMap)) {
            return $item;
        }

        $tourId = (int) $item['product_id'];
        if (get_post_type($tourId) !== Constants::TOUR_POST_TYPE) {
            return $item;
        }

        $prices = PriceComputer::getPricesForDate($tourId, $date);
        $qty = PriceComputer::normalizeQuantities($qtyMap);
        $subtotal = PriceComputer::calculateSubtotal($prices, $qty);

        $lineItems = [];
        foreach ($qty as $groupId => $groupQty) {
            $unitPrice = (float) ($prices[$groupId] ?? 0);
            $lineItems[] = [
                'group'      => $groupId,
                'label'      => $this->groupLabel($groupId),
                'qty'        => $groupQty,
                'unit_price' => $unitPrice,
                'total'      => (float) round($unitPrice * $groupQty, 2),
            ];
        }

        $baseGroup = Settings::getBaseGroup();
        $item['meta']['departure_date'] = $date;
        $item['meta']['price_breakdown'] = [
            'date'       => $date,
            'currency'   => 'VND',
            'prices'     => $prices,
            'quantities' => $qty,
            'line_items' => $lineItems,
            'subtotal'   => $subtotal,
        ];
        $item['unit_price'] = isset($prices[$baseGroup]) && $prices[$baseGroup] > 0
            ? (float) $prices[$baseGroup]
            : (float) $item['unit_price'];

        return $item;
    }

    /**
     * Recompute the order item total from the recorded breakdown subtotal.
     *
     * @param float $total
     * @param \Jankx\Extensions\Ecommerce\Order\OrderItem $orderItem
     */
    public function orderItemTotal($total, $orderItem): float
    {
        $meta = is_callable([$orderItem, 'getMeta']) ? $orderItem->getMeta() : [];
        $breakdown = is_array($meta['price_breakdown'] ?? null) ? $meta['price_breakdown'] : [];

        if (!empty($breakdown['subtotal'])) {
            return (float) $breakdown['subtotal'];
        }

        return (float) $total;
    }

    /**
     * Booking requests (quote flow) also store the price breakdown for the
     * selected date so the sales team already sees the quoted prices.
     */
    public function bookingRequestBreakdown(int $postId, int $tourId): void
    {
        $raw = isset($_POST['booking_price_json']) ? sanitize_text_field(wp_unslash($_POST['booking_price_json'] ?? '')) : '';
        if ($raw) {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                update_post_meta($postId, '_booking_price_breakdown', $data);
            }
        }

        $groups = isset($_POST['booking_groups']) ? (array) $_POST['booking_groups'] : [];
        if ($groups) {
            update_post_meta($postId, '_booking_groups', PriceComputer::normalizeQuantities($groups));
        }
    }

    protected function groupLabel(string $groupId): string
    {
        foreach (Settings::getGroups() as $group) {
            if ($group['id'] === $groupId) {
                return $group['label'];
            }
        }

        return $groupId;
    }
}