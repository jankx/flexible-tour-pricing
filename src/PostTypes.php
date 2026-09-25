<?php

namespace Jankx\Extensions\TourPricing;

/**
 * Post type registry for the Tour Date Pricing extension.
 *
 * The extension ships supporting the "tour" post type. Other extensions can
 * extend it to more post types (e.g. "experience") using one of:
 *
 *   - \Jankx\Extensions\TourPricing\PostTypes::register('experience');
 *   - the `jankx/tour_pricing/supported_post_types` filter:
 *         add_filter('jankx/tour_pricing/supported_post_types', fn ($types) => array_merge($types, ['experience']));
 *
 * A post type may also override which meta keys carry the base price and the
 * departure schedule (per-post-type, filterable):
 *
 *   - `jankx/tour_pricing/base_price_meta_key`    (default "_tour_price")
 *   - `jankx/tour_pricing/departures_meta_key`    (default "_tour_departures")
 *
 * @package Jankx\Extensions\TourPricing
 */
class PostTypes
{
    /**
     * The post types the extension supports out of the box.
     */
    const DEFAULT_POST_TYPES = [Constants::TOUR_POST_TYPE];

    /**
     * Star ratings registered programmatically through static::register().
     *
     * @var string[]
     */
    private static $registered = [];

    /**
     * Add a post type to the extension's supported set.
     *
     * @param string $postType
     */
    public static function register(string $postType): void
    {
        $postType = sanitize_key($postType);
        if ($postType === '' || in_array($postType, self::$registered, true)) {
            return;
        }

        self::$registered[] = $postType;
    }

    /**
     * Remove a post type previously added through static::register().
     *
     * @param string $postType
     */
    public static function unregister(string $postType): void
    {
        self::$registered = array_values(array_diff(self::$registered, [$postType]));
    }

    /**
     * All post types the extension supports.
     *
     * Other extensions may append their post types through the
     * `jankx/tour_pricing/supported_post_types` filter.
     *
     * @return string[]
     */
    public static function getSupported(): array
    {
        $types = array_values(array_unique(array_merge(
            self::DEFAULT_POST_TYPES,
            self::$registered
        )));

        $types = apply_filters('jankx/tour_pricing/supported_post_types', $types);

        return array_values(array_filter(array_unique($types), 'is_string'));
    }

    /**
     * Whether the extension applies to the given post type.
     *
     * @param string $postType
     */
    public static function supports(string $postType): bool
    {
        return in_array($postType, self::getSupported(), true);
    }

    /**
     * Meta key holding the base price for a post type, filterable per type.
     *
     * @param string $postType
     */
    public static function getBasePriceMetaKey(string $postType): string
    {
        $postType = sanitize_key($postType);

        return (string) apply_filters('jankx/tour_pricing/base_price_meta_key', '_tour_price', $postType);
    }

    /**
     * Meta key holding the departure schedule for a post type, filterable per
     * type.
     *
     * @param string $postType
     */
    public static function getDeparturesMetaKey(string $postType): string
    {
        $postType = sanitize_key($postType);

        return (string) apply_filters('jankx/tour_pricing/departures_meta_key', '_tour_departures', $postType);
    }
}