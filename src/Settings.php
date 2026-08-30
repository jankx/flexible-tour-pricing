<?php

namespace Jankx\Extensions\TourPricing;

/**
 * Global settings for the Tour Date Pricing extension.
 *
 * Settings are stored inside the shared `jankx_options` option array so they
 * play nice with the rest of the Jankx extension ecosystem.
 *
 * @package Jankx\Extensions\TourPricing
 */
class Settings
{
    const FIELD_ENABLED    = 'tour_pricing_enabled';
    const FIELD_BASE_GROUP = 'tour_pricing_base_group';
    const FIELD_GROUPS     = 'tour_pricing_groups';
    const FIELD_MIN_DEPARTURES = 'tour_pricing_min_departures';

    const DEFAULT_BASE_GROUP = 'adult';

    /**
     * Default passenger groups: adult / child / infant.
     *
     * Each group supports a fallback policy used when a departure date has no
     * explicitly configured price for that group:
     *   - "base"  = use the tour base price (`_tour_price`)
     *   - "zero"  = free / not offered
     * @return array
     */
    public static function getDefaultGroups(): array
    {
        return [
            [
                'id'       => 'adult',
                'label'    => __('Người lớn', 'jankx'),
                'min_age'  => 12,
                'max_age'  => '',
                'fallback' => 'base',
            ],
            [
                'id'       => 'child',
                'label'    => __('Trẻ em', 'jankx'),
                'min_age'  => 2,
                'max_age'  => 11,
                'fallback' => 'zero',
            ],
            [
                'id'       => 'infant',
                'label'    => __('Em bé', 'jankx'),
                'min_age'  => 0,
                'max_age'  => 1,
                'fallback' => 'zero',
            ],
        ];
    }

    public static function getOption(string $key, $default = null)
    {
        $options = get_option('jankx_options', []);

        return isset($options[$key]) ? $options[$key] : $default;
    }

    public static function getAll(): array
    {
        return get_option('jankx_options', []);
    }

    public static function isEnabled(): bool
    {
        return (bool) self::getOption(self::FIELD_ENABLED, true);
    }

    public static function getBaseGroup(): string
    {
        return (string) self::getOption(self::FIELD_BASE_GROUP, self::DEFAULT_BASE_GROUP);
    }

    /**
     * Get the configured passenger groups (normalized).
     *
     * @return array[] List of [id, label, min_age, max_age, fallback]
     */
    public static function getGroups(): array
    {
        $groups = self::getOption(self::FIELD_GROUPS, self::getDefaultGroups());
        $groups = is_array($groups) ? $groups : [];

        if (empty($groups)) {
            $groups = self::getDefaultGroups();
        }

        $normalized = [];
        foreach ($groups as $group) {
            if (!is_array($group) || empty($group['id'])) {
                continue;
            }
            $groupId = sanitize_key($group['id']);
            $fallback = isset($group['fallback']) && in_array($group['fallback'], ['base', 'zero'], true)
                ? $group['fallback']
                : 'zero';
            $normalized[] = [
                'id'       => $groupId,
                'label'    => (string) ($group['label'] ?? $groupId),
                'min_age'  => isset($group['min_age']) && $group['min_age'] !== '' ? (int) $group['min_age'] : null,
                'max_age'  => isset($group['max_age']) && $group['max_age'] !== '' ? (int) $group['max_age'] : null,
                'fallback' => $fallback,
            ];
        }

        if (empty($normalized)) {
            $normalized = self::getDefaultGroups();
        }

        return $normalized;
    }

    /**
     * Group ids currently configured.
     *
     * @return string[]
     */
    public static function getGroupIds(): array
    {
        return array_column(self::getGroups(), 'id');
    }

    public static function isGroupValid(string $groupId): bool
    {
        return in_array($groupId, self::getGroupIds(), true);
    }

    public static function getMinDepartures(): int
    {
        return (int) self::getOption(self::FIELD_MIN_DEPARTURES, 1);
    }
}