<?php
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

use UNSProjectApp\DatabaseService;
use UNSProjectApp\Helpers\View;
use UNSProjectApp\ServerCall;
use UNSProjectApp\SiteOptions;
use UNSProjectApp\UnsWordPressAPI;

$databaseService = new DatabaseService();
$currentUser = wp_get_current_user();
$siteOptions = new SiteOptions();
$srpCredentials = $databaseService->getSrpCredentials(get_current_user_id());

if ($srpCredentials !== null && isset($_REQUEST['srp_activate_action'])) {
    $activate = (int) isset($_REQUEST['activate_srp']);
    $siteOptions->setValue('activate_srp',$activate);
    $resetPasswordSrpValue = $activate && isset($_REQUEST['delete_password_srp'])
        ? (bool)$_REQUEST['delete_password_srp']
        : false;
    $siteOptions->setValue('delete_password_srp', $resetPasswordSrpValue);

    $siteOptions->save();
}
$deleteSrpPassword = (bool)$siteOptions->getValue('delete_password_srp');
if (
    $srpCredentials === null
    && isset($_REQUEST['password'])
    && isset($_REQUEST['confirm_password'])
    && isset($_REQUEST['current_password'])
) {
    $errorMessage = null;
    if (
        empty($_REQUEST['password'])
        || empty($_REQUEST['confirm_password'])
        || empty($_REQUEST['current_password'])
    ) {
        $errorMessage = 'Missing required fields (password, confirm password or current password';
    }

    $currentUser = wp_get_current_user();
    if (wp_check_password($_REQUEST['current_password'], $currentUser->get('user_pass')) === false) {
        $errorMessage = 'Invalid password provided.';
    } else if ($_REQUEST['password'] !== $_REQUEST['confirm_password']) {
        $errorMessage = 'Password and Confirm password does not match.';
    }

    if ($errorMessage !== null) {
        View::load('error.php', [
            'message' => $errorMessage
        ]);
    } else {
        $password = $_REQUEST['password'];
        $srp = new \Srp\Srp();
        $s = $srp->getRandomSeed();
        $x = $srp->generateX($s, $currentUser->get('user_email'), $password);
        $v = $srp->generateV($x);

        $jwt = UNSProjectApp\Libraries\JWT::encode(
            [
                'user_id' => $currentUser->ID,
                's' => $s,
                'v' => $v
            ],
            base64_decode($siteOptions->getValue('private_key')),
            'RS256'
        );

        ServerCall::post(
            site_url() . UnsWordPressAPI::getApiPath() . UnsWordPressAPI::SRP_REGISTER,
            [
                'body' => [
                    'JWT' => $jwt,
                ]
            ],
            $statusCode,
            $plainResult
        );
        $srpCredentials = $databaseService->getSrpCredentials(get_current_user_id());
    }
}

$srpEnabled = $srpCredentials !== null && (int)$siteOptions->getValue('activate_srp') === 1;
?>
<div id="unsproject-srp" class="container">
    <div class="row">
        <div class="col-md-6">
            <h1>Secure Remote Password (SRP) Settings</h1>
        </div>
        <div class="col-md-6">
            <a class="learn_more" href="http://srp.stanford.edu/" target="_blank">
                Learn more about SRP
            </a>
        </div>
    </div>

    <div class="srp-container">
        <?php
        if (!extension_loaded('bcmath')) {
            $class = 'notice notice-error';
            $message = __('SRP requires module `bcmath` to be installed. In order to install it, please contact your server administrator.', 'sample-text-domain');
            printf('<div class="row"><div class="col-md-12"><div class="%1$s"><p>%2$s</p></div></div></div>', esc_attr($class), esc_html($message));
            die();
        } else if ($srpCredentials === null) {
            ?>
            <div class="row">
                <div class="col-md-12">
                    <?php
                    include_once 'register_form.php';
                    ?>
                </div>
            </div>
            <?php
        } else {
            ?>

            <form method="POST">
                <input type="hidden" name="srp_activate_action" />
                <div class="custom-container top-round">
                    <b class="custom-title">Enable SRP</b>
                    <p class="custom-info">
                        Enabling Secure Remote Password (SRP) Authentication will replace the existing WordPress password authentication system on the login page of your site. SRP allows you to retain the convenience of a password based system while improving security by not storing any passwords on the server. You can use SRP in concert with UNS’ secure, private, passwordless based system
                    </p>
                    <div class="custom-control custom-switch">
                        <input name="activate_srp" value="1" type="checkbox" <?php echo $srpEnabled ? 'checked' : '' ?> class="custom-control-input" id="activate_srp">
                        <label class="custom-control-label" for="activate_srp">SRP ENABLED</label>
                    </div>
                </div>
                <div class="custom-container bottom-round no-top-border">
                    <b class="custom-title">Delete Password from User Table</b>
                    <p class="custom-info">
                        Enabling this option will delete the user’s password from the WordPress User Table. The deletion which improves security, will occur once the user resets their password via SRP. WARNING: If you opt to return to the default WordPress authentication system or another authentication system your passwords will need to be reset again.
                    </p>
                    <div class="custom-control custom-switch">
                        <input name="delete_password_srp" value="1" type="checkbox" <?php echo $deleteSrpPassword ? 'checked' : '' ?> class="custom-control-input" id="delete_password_srp">
                        <label class="custom-control-label" for="delete_password_srp">PASSWORD PROTECTION ENABLED</label>
                    </div>
                    <div class="save_changes_container">
                        <button type="submit" class="btn btn-primary">SAVE CHANGES</button>
                    </div>
                </div>
            </form>
            <?php
        }
        ?>
    </div>
</div>
