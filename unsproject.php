<?php
/*
    Plugin Name: UNSProject
    Plugin URI: https://www.unsproject.com/
    Description: UNS Project offers secure private, passwordless, authentication for your WordPress site. The plugin also supports Secure Remote Password (SRP) Authentication. Protect passwords by not storing them on the server.
    Author: UNS Project
    Author URI: https://www.unsproject.com/
    Text Domain: uns-project
    Domain Path: /i18n
    Version: 3.0.0
*/

use UNSProjectApp\DatabaseService;
use UNSProjectApp\FrontEnd;
use UNSProjectApp\Helpers\View;
use UNSProjectApp\SiteOptions;
use UNSProjectApp\UnsApp;
use UNSProjectApp\UnsAppException;
use UNSProjectApp\UnsWordPressAPI;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

include_once "autoload.php";

function unsproject_create_menu_entry()
{

    $icon = plugins_url('/images/16x16.png', __FILE__);

    // adding the main menu entry
    add_menu_page(
        'UNS PROJECT',
        'UNS Project',
        'manage_options',
        'main-page-unsproject',
        'unsproject_plugin_show_main_page',
        $icon
    );

    add_submenu_page(
        'main-page-unsproject',
        'Secure Remote Password',
        'Secure Remote Password',
        'manage_options',
        'uns_project_srp',
        function () {
            include_once 'src/Views/Srp/home.php';

            $pluginData = get_plugin_data(__FILE__);
            $pluginVersion = isset($pluginData['Version'])
                ? $pluginData['Version']
                : false;
            load_unsproject_backend_scripts_and_styles($pluginVersion);
        }
    );
}

add_action('admin_menu', 'unsproject_create_menu_entry');

function unsproject_plugin_show_main_page()
{
    $pluginData = get_plugin_data(__FILE__);
    $pluginVersion = isset($pluginData['Version'])
        ? $pluginData['Version']
        : false;
    load_unsproject_common_scripts_and_styles($pluginVersion);
    load_unsproject_backend_scripts_and_styles($pluginVersion);
    $siteOptions = new SiteOptions();

    View::load('reset_plugin.php');

    if (isset($_REQUEST['clear'])) {
        try {
            UnsApp::deleteService($siteOptions);
            delete_site_option(SiteOptions::OPTION_NAME_CREDENTIALS);
            $databaseService = new DatabaseService();
            $databaseService->truncateTable(DatabaseService::TABLE_USERS);
            $databaseService->truncateTable(DatabaseService::TABLE_TICKETS);
            $siteOptions->resetAll();
        } catch (Exception $e) {
            View::load('error.php', [
                'message' => $e->getMessage() . $e->getFile() . ':' . $e->getLine()
            ]);
        }
    }

    if (isset($_REQUEST['action'])) {
        switch ($_REQUEST['action']) {
            case "re-validate":
                try {
                    UnsApp::initializeSiteValidation($siteOptions);
                    $siteOptions->setValue('siteValidated', true);
                    $siteOptions->save();
                } catch (Exception $e) {
                    View::load('error.php', [
                        'message' => $e->getMessage(),
                    ]);
                }
                break;
            case "update_attestation":
                $siteOptions->setValue(
                    'default_attestation_type',
                    isset($_REQUEST['default_attestation_type'])
                        ? sanitize_text_field($_REQUEST['default_attestation_type'])
                        : ''
                );

                $siteOptions->setValue('authorization_interval',
                    isset($_REQUEST['authorization_interval'])
                        ? (int)sanitize_text_field($_REQUEST['authorization_interval'])
                        : UnsWordPressAPI::DEFAULT_AUTHORIZATION_INTERVAL
                );

                $siteOptions->save();
                break;
            case "register":

                $name = isset($_REQUEST['name'])
                    ? sanitize_text_field($_REQUEST['name'])
                    : null;
                $email = isset($_REQUEST['email'])
                    ? sanitize_email($_REQUEST['email'])
                    : null;
                $phone = isset($_REQUEST['phone'])
                    ? sanitize_text_field($_REQUEST['phone'])
                    : null;
                $agreeWithTerms = isset($_REQUEST['terms'])
                    ? sanitize_text_field($_REQUEST['terms'])
                    : null;
                $hasError = false;
                if (empty($email) || empty($phone) || empty($name) || empty($agreeWithTerms)) {
                    $hasError = true;
                    View::load('error.php', [
                        'message' => 'Required fields are missing. Please fill all inputs.'
                    ]);
                }

                if (is_email($email) === false) {
                    $hasError = true;
                    View::load('error.php', [
                        'message' => 'Email is not valid.'
                    ]);
                }

                if ($hasError === false) {
                    $siteOptions->setValue('email', $email);
                    $siteOptions->setValue('phoneNumber', $phone);
                    $siteOptions->setValue('contactName', $name);
                    $siteOptions->setValue('agreeWithTerms', true);

                    $siteOptions->save();
                    try {
                        $siteOptions = UnsApp::initializeRegisterProcess($siteOptions);
                        UnsApp::initializeSiteValidation($siteOptions);
                        $siteOptions->setValue('siteValidated', true);
                        $siteOptions->save();
                    } catch (Exception $e) {

                        View::load('error.php', [
                            'message' => $e->getMessage()
                        ]);
                        if ($e->getCode() == UnsAppException::VALIDATION_ERROR) {
                            View::load('re_validation.php');
                            return;
                        }
                    }
                }
                break;
        }
    }

    if (empty($siteOptions->getValue('registeredSite'))) {
        $currentUser = wp_get_current_user();
        View::load('register.php', [
            'currentUser' => $currentUser,
            'email' => isset($_REQUEST['email'])
                ? sanitize_email($_REQUEST['email'])
                : sanitize_email($currentUser->get('user_email')),
            'phone' => isset($_REQUEST['phone'])
                ? sanitize_text_field($_REQUEST['phone'])
                : '',
            'name' => isset($_REQUEST['name'])
                ? sanitize_text_field($_REQUEST['name'])
                : $currentUser->first_name . ' ' . $currentUser->last_name
        ]);

    } else if ($siteOptions->getValue('validationCode') !== null && $siteOptions->getValue('siteValidated') === null) {
        echo "<p>There was an error with your website validation. Please try again to validate your website.<br />";
        View::load('re_validation.php');

    } else if ($siteOptions->getValue('validationCode') !== null && $siteOptions->getValue('siteValidated') !== null) {
        View::load('connection_status.php', [
            'siteOption' => $siteOptions->getAll(),
            'attestationTypes' => UnsApp::getAttestationTypes()
        ]);
    } else {
        View::load('error.php', [
            'message' => 'Something is wrong with your configuration. Please start over. <a href="' . site_url() . '/admin.php?page=main-page-unsproject&action=clear">clear</a>'
        ]);
    }
}

/**
 * @param bool|string $pluginVersion
 */
function load_unsproject_backend_scripts_and_styles($pluginVersion = false)
{
    wp_enqueue_script(
        'unsproject-backend-sript',
        plugin_dir_url(__FILE__) . 'js/backend.js',
        ['jquery'],
        $pluginVersion
    );
    wp_enqueue_style(
        'unsproject-backend-style',
        plugin_dir_url(__FILE__) . 'css/backend.css',
        ['uns-project-bootstrap'],
        $pluginVersion
    );
    wp_enqueue_style(
        'uns-project-bootstrap',
        plugin_dir_url(__FILE__) . 'css/bootstrap.min.css',
        [],
        $pluginVersion
    );
}

/**
 * @param bool|string $pluginVersion
 */
function load_unsproject_common_scripts_and_styles($pluginVersion = false)
{
    wp_enqueue_script(
        'unsproject-qrcode-script',
        plugin_dir_url(__FILE__) . 'js/vendor/qrcode.min.js',
        ['jquery'],
        $pluginVersion
    );

    wp_enqueue_script(
        'unsproject-script',
        plugin_dir_url(__FILE__) . 'js/common.js',
        ['unsproject-qrcode-script'],
        $pluginVersion
    );

    wp_enqueue_style(
        'unsproject-style',
        plugin_dir_url(__FILE__) . 'css/common.css',
        [],
        $pluginVersion
    );
}

//GENERATE Keys on plugin install
function unsproject_plugin_activate()
{
    UnsApp::checkPluginRequirements();
    ob_start();
    $database = new DatabaseService();
    $database->createUsersTable();
    $database->createTableTickets();
    $database->createSRPTable();
    ob_get_clean();
}

register_activation_hook(__FILE__, 'unsproject_plugin_activate');

/**
 * Delete options on plugin uninstall
 */
function unsproject_plugin_uninstall()
{
    $siteOptions = new SiteOptions();
    try {
        UnsApp::deleteService($siteOptions);
    }catch ( \Exception $e){
    }
    delete_option(SiteOptions::OPTION_NAME_CREDENTIALS);

    $databaseService = new DatabaseService();
    $databaseService->deleteTable(DatabaseService::TABLE_USERS);
    $databaseService->deleteTable(DatabaseService::TABLE_TICKETS);
    $databaseService->deleteTable(DatabaseService::TABLE_SRP);
}

register_uninstall_hook(__FILE__, 'unsproject_plugin_uninstall');

function unsproject_login_screen_hook($text)
{
    if(isset($_REQUEST['loggedout'])){
        header('Location:'  . get_admin_url());
        exit();
    }
    load_unsproject_common_scripts_and_styles();
    $fe = new FrontEnd();
    echo '<div class="login-form-uns-qr-code-container">';
    echo $fe->generateQRCodeViewWithTicketID(FrontEnd::PAGE_LOGIN, true);
    echo "</div>";
}

add_action('login_footer', 'unsproject_login_screen_hook');

/**
 * @param WP_User $userProfile
 * @return string
 */
function unsproject_profile_page_hook($userProfile)
{
    $pluginData = get_plugin_data(__FILE__);
    $pluginVersion = isset($pluginData['Version'])
        ? $pluginData['Version']
        : false;
    load_unsproject_common_scripts_and_styles($pluginVersion);
    load_unsproject_backend_scripts_and_styles($pluginVersion);

    $databaseService = new DatabaseService();
    $currentUserId = get_current_user_id();
    $pluginOptions = new SiteOptions();
    if (
        empty($pluginOptions->getAll())
        || $pluginOptions->getValue('private_key') === null
        || $pluginOptions->getValue('gatekeeperPublicKey') === null
    ) {
        return '';
    }

    echo "<h2>UNS Project</h2>";
    $numberOfConnection = $databaseService->getNumberOfAccountsConnections($currentUserId, $pluginOptions);
    $fe = new FrontEnd();
    if ($numberOfConnection > 0) {
        try {
            echo $fe->generateStatusPage($currentUserId, $pluginOptions->getValue('private_key'));
        } catch (Exception $e) {
            echo "There was an error with your UNS Configuration. " . $e->getMessage() . "<br />";
        }

    } else {
        echo $fe->generateQRCodeViewWithTicketID(FrontEnd::PAGE_USER_PROFILE, false);
    }
}

add_action('show_user_profile', 'unsproject_profile_page_hook');

/**
 * @param array $links
 * @return mixed
 */
function unsproject_add_plugin_action_links($links)
{

    $links['settings'] = sprintf(
        '<a href="%1$s">%2$s</a>',
        admin_url() . 'admin.php?page=main-page-unsproject',
        'Settings'
    );

    return $links;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'unsproject_add_plugin_action_links');

include_once 'routes_api.php';

//SRP
include_once 'routes_srp.php';
include_once 'srpactions.php';