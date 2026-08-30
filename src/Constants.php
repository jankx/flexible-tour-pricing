<?php

namespace Jankx\Extensions\TourPricing;

/**
 * Shared constants for the Tour Date Pricing extension.
 *
 * Uses explicit string values instead of referencing the Travel extension's
 * classes so hooks can be registered even when the travel extension has not
 * been autoloaded yet (extension load order is not guaranteed).
 *
 * @package Jankx\Extensions\TourPricing
 */
class Constants
{
    const TOUR_POST_TYPE = 'tour';
}