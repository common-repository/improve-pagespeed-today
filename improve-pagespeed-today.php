<?php
/*
* Plugin Name: PageSpeed.today
* Version: 1.1.7
* Plugin URI: https://pagespeed.today/
* Description: Essential PageSpeed optimization - Image optimization - Minify CSS & JavaScript - PageSpeed.today official WordPress Plugin.
* Author: PageSpeed.today
* Text Domain: pagespeed-today
* Domain Path: /lang/
* License: GPL v3
*/

/**
 * PageSpeed.today Plugin
 * Copyright (C) 2017, PageSpeed today - hello@pagespeed.today
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('ABSPATH')) exit;

if (!defined('PAGESPEED_TODAY_PLUGIN_DIR')) define('PAGESPEED_TODAY_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Load plugin class files

require_once ('includes/class-pagespeed-today.php');

require_once ('includes/class-pagespeed-today-settings.php');

// Load plugin libraries

require_once ('includes/lib/class-pagespeed-today-admin-api.php');

require_once ('includes/lib/third-party/Zip.php');

/**
 * Returns the main instance of PageSpeed_today to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object PageSpeed_today
 */
function PageSpeed_today()
{
    @header('X-Powered-By: PageSpeed.today');
    $instance = PageSpeed_today::instance(__FILE__, '1.0.0');
    if (is_null($instance->settings)) {
        $instance->settings = PageSpeed_today_Settings::instance($instance);
    }

    return $instance;
}

/**
 * Display error notice on plugin settings page
 */
function admin_notice__error()
{
    $class = 'notice notice-error';
    $message = 'Something went wrong. Try again later or contact Support.';
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class) , esc_html($message));
}

/**
 * Display success notice on plugin settings page
 */
function admin_notice__success()
{
    $class = 'notice notice-success';
    $message = 'Success!';
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class) , esc_html($message));
}

/**
 * Display success notice on plugin settings page
 */
function admin_notice__empty()
{
    $class = 'notice notice-warning';
    $message = 'Issues fixed by PageSpeed.Today were not detected on this page.';
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class) , esc_html($message));
}

/**
 * Display delay notice on plugin settings page
 */
function admin_notice__delay()
{
    $class = 'notice notice-warning';
    $message = 'Please wait 24 hours before repeating the process.';
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class) , esc_html($message));
}

/**
 * Display limit notice on plugin settings page
 */
function admin_notice__limit()
{
    $class = 'notice notice-warning';
    $message = 'You have reached your daily limit. Come back in 24 hours or <a href="https://pagespeed.today">Upgrade to Premium</a> for Unlimited Scans and Optimization.';
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class) , $message);
}

/**
 * Display license error on plugin settings page
 */
function admin_notice__license_error()
{
    $class = 'notice notice-error';
    $message = 'License verification error.';
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class) , esc_html($message));
}

/**
 * Display license success on plugin settings page
 */
function admin_notice__license_success()
{
    $class = 'notice notice-success';
    $message = 'License verified successfully!';
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class) , esc_html($message));
}

/**
 * Display settings check on plugin settings page
 */
function admin_notice__settings_check()
{
    $class = 'notice notice-warning';
    $message = 'Please enable at least one optimization parameter in "Settings" tab and try again.';
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class) , $message);
}

PageSpeed_today();