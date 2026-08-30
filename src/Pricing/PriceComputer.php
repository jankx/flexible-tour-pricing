<?php

namespace Jankx\Extensions\TourPricing\Pricing;

use Jankx\Extensions\TourPricing\Settings;

/**
 * Resolves the applicable price for a tour departure date per passenger
 * group and computes cart/order subtotals from the group breakdown.
 *
 * This is the single entry point the rest of the system (frontend display,
 * cart, checkout, order recording) uses to answer "how much is this tour on
 * date X for these passengers".
 *
 * @package Jankx\Extensions\TourPricing\Pricing
 */
class PriceComputer
{
    /**
     * Resolve the price map for a tour on a given date.
     *
     * When the date has explicit calendar prices those are returned as-is.
     * Otherwise the tour base price is applied to the base group and every
     * other group follows its configured fallback policy.
     *
     * @param int    $tourId
     * @param string $date Y-m-d
     * @return array<string,float> Group id => price
     */
    public static function getPricesForDate(int $tourId, string $date): array
    {
        $tourId = (int) $tourId;

        if (PriceRepository::hasDate($tourId, $date)) {
            $prices = PriceRepository::normalizePrices(PriceRepository::getDatePrices($tourId, $date));
        } else {
            $prices = self::fallbackPrices($tourId);
        }

        return apply_filters('jankx/tour_pricing/prices_for_date', $prices, $tourId, $date);
    }

    /**
     * Fallback price map when a date has no explicit calendar price.
     *
     * @return array<string,float>
     */
    protected static function fallbackPrices(int $tourId): array
    {
        $base = PriceRepository::getBasePrice($tourId);
        $baseGroup = Settings::getBaseGroup();
        $prices = Settings::getGroupIds();

        $map = [];
        foreach ($prices as $groupId) {
            if ($groupId === $baseGroup) {
                $map[$groupId] = $base;
                continue;
            }
            $map[$groupId] = 0.0;
        }

        return $map;
    }

    /**
     * Price of a single group for a date.
     */
    public static function getGroupPrice(int $tourId, string $date, string $groupId): float
    {
        $prices = self::getPricesForDate($tourId, $date);

        return (float) ($prices[$groupId] ?? 0);
    }

    /**
     * Calculate the subtotal for a group quantity map.
     *
     * @param array<string,float> $prices
     * @param array<string,int>   $qtyMap Group id => quantity
     */
    public static function calculateSubtotal(array $prices, array $qtyMap): float
    {
        $total = 0.0;
        foreach ($qtyMap as $groupId => $qty) {
            $price = isset($prices[$groupId]) ? (float) $prices[$groupId] : 0.0;
            $total += $price * max(0, (int) $qty);
        }

        return (float) round($total, 2);
    }

    /**
     * Convenience: subtotal for a tour/date + group quantities.
     *
     * @param array<string,int> $qtyMap
     */
    public static function calculateItemSubtotal(int $tourId, string $date, array $qtyMap): float
    {
        $prices = self::getPricesForDate($tourId, $date);

        return self::calculateSubtotal($prices, $qtyMap);
    }

    /**
     * "Price from" shown on the tour (minimum base/adult price across the
     * configured departure dates, falling back to the tour base price).
     */
    public static function getStartingPrice(int $tourId): float
    {
        $tourId = (int) $tourId;
        $baseGroup = Settings::getBaseGroup();

        $min = null;
        foreach (PriceRepository::getDates($tourId) as $date) {
            $price = self::getGroupPrice($tourId, $date, $baseGroup);
            if ($price > 0 && ($min === null || $price < $min)) {
                $min = $price;
            }
        }

        if ($min !== null) {
            return $min;
        }

        return PriceRepository::getBasePrice($tourId);
    }

    /**
     * Flatten a group quantity map, dropping zero quantities.
     *
     * @return array<string,int>
     */
    public static function normalizeQuantities($qtyMap): array
    {
        $qtyMap = is_array($qtyMap) ? $qtyMap : [];

        $normalized = [];
        foreach ($qtyMap as $groupId => $qty) {
            $groupId = sanitize_key((string) $groupId);
            $qty = max(0, (int) $qty);
            if ($groupId !== '' && $qty > 0) {
                $normalized[$groupId] = $qty;
            }
        }

        return $normalized;
    }

    public static function hasAnyPricedDate(int $tourId): bool
    {
        foreach (PriceRepository::getDates((int) $tourId) as $date) {
            $prices = self::getPricesForDate((int) $tourId, $date);
            foreach ($prices as $price) {
                if ((float) $price > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}