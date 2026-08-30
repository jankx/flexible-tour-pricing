<?php

namespace Jankx\Extensions\TourPricing;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\TourPricing\Admin\TourPricingMetaBox;
use Jankx\Extensions\TourPricing\Rest\TourPricingController;
use Jankx\Extensions\TourPricing\Frontend\AddToCartIntegration;
use Jankx\Extensions\TourPricing\Order\OrderRecorder;

/**
 * Tour Date Pricing Extension
 *
 * Extends the Travel extension so each tour departure date can carry its
 * own price per passenger group (adult / child / infant). Prices are set
 * through a calendar UI in the tour editor, resolved by date on the
 * frontend, applied across the e-commerce cart/checkout flow and fully
 * recorded inside order items for later financial reporting.
 *
 * @package Jankx\Extensions\TourPricing
 */
class TourPricingExtension extends AbstractExtension
{
    const EXTENSION_ID = 'tour-pricing';

    protected static $instance;

    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'Jankx\\Extensions\\TourPricing\\';
            $base_dir = __DIR__ . '/src/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        // Admin meta box: calendar to set per-date, per-group prices.
        if (is_admin()) {
            (new TourPricingMetaBox())->register();
            (new \Jankx\Extensions\TourPricing\Admin\SettingsPage())->register();
        }

        // Register the meta keys used by the pricing engine.
        add_action('init', [$this, 'register_meta']);

        // REST endpoint used by the frontend to resolve price for a date.
        add_action('rest_api_init', function () {
            (new TourPricingController())->register_routes();
        });

        // Frontend integrations (e-commerce add to cart) + order recording.
        (new AddToCartIntegration())->register();
        (new OrderRecorder())->register();

        // Frontend assets.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function register_meta(): void
    {
        if (!class_exists('\Jankx\Extensions\Travel\PostTypes\TourPostType')) {
            return;
        }

        register_meta('post', '_tour_price_calendar', [
            'object_subtype' => Constants::TOUR_POST_TYPE,
            'type' => 'array',
            'description' => 'Giá theo ngày khởi hành (map ngày → giá theo nhóm khách)',
            'single' => true,
            'default' => [],
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => ['type' => 'object'],
                ],
            ],
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function enqueue_frontend_assets(): void
    {
        if (!class_exists('\Jankx\Extensions\Travel\PostTypes\TourPostType')) {
            return;
        }

        $tourType = Constants::TOUR_POST_TYPE;
        if (!is_singular($tourType)) {
            return;
        }

        wp_enqueue_script(
            'jankx-tour-pricing-frontend',
            $this->get_extension_url() . '/assets/frontend.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script('jankx-tour-pricing-frontend', 'jankxTourPricing', [
            'restUrl' => esc_url_raw(rest_url(TourPricingController::REST_NAMESPACE)),
            'i18n' => [
                'loading' => __('Đang tải giá...', 'jankx'),
                'currency' => function_exists('jankx_currency_format') ? '' : '₫',
            ],
        ]);

        wp_enqueue_style(
            'jankx-tour-pricing-frontend',
            $this->get_extension_url() . '/assets/frontend.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !class_exists('\Jankx\Extensions\Travel\PostTypes\TourPostType')) {
            return;
        }

        if ($screen->post_type !== Constants::TOUR_POST_TYPE) {
            return;
        }

        wp_enqueue_style(
            'jankx-tour-pricing-admin',
            $this->get_extension_url() . '/assets/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'jankx-tour-pricing-admin',
            $this->get_extension_url() . '/assets/admin.js',
            [],
            '1.0.0',
            true
        );
    }
}