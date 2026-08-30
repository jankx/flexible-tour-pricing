<?php

namespace Jankx\Extensions\TourPricing\Pricing;

/**
 * Reads / writes the per-date price calendar stored on a tour.
 *
 * Meta key `_tour_price_calendar` is an associative array keyed by date
 * (Y-m-d). Each value maps a passenger group id (adult/child/infant, ...)
 * to its price in VND for that departure date:
 *
 *   [
 *     "2026-09-05" => ["adult" => 3000000, "child" => 2000000, "infant" => 0],
 *     "2026-09-20" => ["adult" => 3500000, "child" => 2200000, "infant" => 0],
 *   ]
 *
 * @package Jankx\Extensions\TourPricing\Pricing
 */
class PriceRepository
{
    const META_KEY = '_tour_price_calendar';

    public static function get(int $tourId): array
    {
        $calendar = get_post_meta((int) $tourId, self::META_KEY, true);

        return is_array($calendar) ? $calendar : [];
    }

    public static function getDatePrices(int $tourId, string $date): array
    {
        $calendar = self::get((int) $tourId);

        $prices = $calendar[$date] ?? [];

        return is_array($prices) ? $prices : [];
    }

    public static function hasDate(int $tourId, string $date): bool
    {
        $calendar = self::get((int) $tourId);

        return isset($calendar[$date]) && is_array($calendar[$date]);
    }

    public static function getDates(int $tourId): array
    {
        $calendar = self::get((int) $tourId);
        $dates = [];
        foreach ($calendar as $date => $prices) {
            if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $dates[] = $date;
            }
        }
        sort($dates);

        return $dates;
    }

    /**
     * Persist the whole calendar.
     *
     * @param int   $tourId
     * @param array $calendar Keyed by date => group prices map.
     */
    public static function save(int $tourId, array $calendar): void
    {
        $normalized = [];
        foreach ($calendar as $date => $prices) {
            if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            if (!is_array($prices)) {
                continue;
            }

            $clean = [];
            foreach ($prices as $groupId => $price) {
                $clean[sanitize_key((string) $groupId)] = absint($price);
            }
            if ($clean) {
                $normalized[$date] = $clean;
            }
        }

        update_post_meta((int) $tourId, self::META_KEY, $normalized);
    }

    /**
     * Set (or overwrite) the prices for a single date.
     */
    public static function setDatePrices(int $tourId, string $date, array $prices): void
    {
        $calendar = self::get((int) $tourId);
        $calendar[$date] = $prices;
        self::save((int) $tourId, $calendar);
    }

    public static function deleteDate(int $tourId, string $date): void
    {
        $calendar = self::get((int) $tourId);
        unset($calendar[$date]);
        self::save((int) $tourId, $calendar);
    }

    public static function getBasePrice(int $tourId): float
    {
        return (float) get_post_meta((int) $tourId, '_tour_price', true);
    }

    /**
     * Return a price map with every configured group present (missing groups
     * default to zero).
     *
     * @return array<string,float>
     */
    public static function normalizePrices(array $prices): array
    {
        $groupIds = \Jankx\Extensions\TourPricing\Settings::getGroupIds();
        $normalized = [];
        foreach ($groupIds as $groupId) {
            $normalized[$groupId] = 0.0;
        }
        foreach ($prices as $groupId => $price) {
            $groupId = sanitize_key((string) $groupId);
            if (isset($normalized[$groupId])) {
                $normalized[$groupId] = (float) $price;
            }
        }

        return $normalized;
    }
}