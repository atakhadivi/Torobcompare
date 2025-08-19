<?php
/**
 * Security file to prevent direct access
 *
 * @package Torob_Price_Compare
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Redirect to WordPress admin if accessed directly
wp_redirect(admin_url());
exit;