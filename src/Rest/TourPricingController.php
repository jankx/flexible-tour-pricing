<?php

namespace Jankx\Extensions\TourPricing\Rest;

use Jankx\Extensions\TourPricing\Pricing\PriceComputer;
use Jankx\Extensions\TourPricing\Pricing\PriceRepository;
use Jankx\Extensions\TourPricing\Settings;

/**
 * REST API for the Tour Date Pricing extension.
 *
 * Routes:
 *   GET /wp-json/jankx/tour-pricing/v1/tour/{id}/price?date=Y-m-d&groups[adult]=2
 *
 * @package Jankx\Extensions\TourPricing\Rest
 */
class TourPricingController
{
    const REST_NAMESPACE = 'jankx/tour-pricing/v1';

    public function register_routes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/tour/(?P<id>\d+)/price', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getPrices'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id'     => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'date'   => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'groups' => [
                    'default'           => [],
                    'type'              => 'array',
                    'sanitize_callback' => function ($value) {
                        $value = is_array($value) ? $value : [];
                        $clean = [];
                        foreach ($value as $groupId => $qty) {
                            $clean[sanitize_key((string) $groupId)] = max(0, (int) $qty);
                        }
                        return $clean;
                    },
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/groups', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getGroups'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function getGroups(): \WP_REST_Response
    {
        return rest_ensure_response([
            'groups'     => Settings::getGroups(),
            'base_group' => Settings::getBaseGroup(),
        ]);
    }

    public function getPrices(\WP_REST_Request $request): \WP_REST_Response
    {
        $tourId = (int) $request->get_param('id');
        $date = sanitize_text_field((string) $request->get_param('date'));
        $qtyMap = (array) $request->get_param('groups');

        $tour = get_post($tourId);
        if (!$tour || $tour->post_type !== \Jankx\Extensions\TourPricing\Constants::TOUR_POST_TYPE) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Tour không tồn tại.', 'jankx'),
            ], 404);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Ngày không hợp lệ.', 'jankx'),
            ], 400);
        }

        $prices = PriceComputer::getPricesForDate($tourId, $date);
        $quantities = PriceComputer::normalizeQuantities($qtyMap);
        $subtotal = PriceComputer::calculateSubtotal($prices, $quantities);

        return rest_ensure_response([
            'success'    => true,
            'tour_id'    => $tourId,
            'date'       => $date,
            'base_price' => PriceRepository::getBasePrice($tourId),
            'prices'     => $prices,
            'quantities' => $quantities,
            'subtotal'   => $subtotal,
        ]);
    }
}