<?php
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * @var \UNSProjectApp\SiteOptions $siteOption
 * @var int $page
 * @var bool $showOr
 * @var bool $useInFrontend
 */

use UNSProjectApp\Helpers\SessionHelper;
use UNSProjectApp\Libraries\JWT;
use UNSProjectApp\UnsWordPressAPI;

?>
<div id="unsproject_qr_code_conatainer">
    <?php
    if (isset($useInFrontend) && $useInFrontend === true) {
        ?>
        <div id="orContainer">
            <div class="left-part"></div>
            <div class="center-part">or</div>
            <div class="right-part"></div>
        </div>
        <?php
    }
    ?>
    <div class="uns-login-container">
        <a onclick="createUnsTicket();"
           target="_blank"
           class="button button-primary button-large uns-login-button">
            Connect with UNS
        </a>
    </div>
    <div id="unsproject-status" class="unsproject-loader"></div>
</div>
<?php
$sessionId = SessionHelper::getSessionId();
?>
<script type="text/javascript">
    <?php
    ob_start();
    $checkUrl = UnsWordPressAPI::getVerifyTicketUrl();

    $requestParams = [];
    try {

    $requestParams['jwt'] = JWT::encode(
        [
            'useInFrontend' => (int) $useInFrontend,
            'userId' => get_current_user_id()
        ],
        base64_decode($siteOption->getValue('private_key')),
        'RS256'
    );
    ?>

    function createUnsTicket() {
        openLoadingWindow();
        jQuery.ajax({
            type: "POST",
            url: "<?php echo UnsWordPressAPI::getCreateTicketApiUrl();?>",
            data: <?php echo json_encode($requestParams);?>,
            success: function (response) {
                var UnsResponseJson = JSON.parse(response);
                openUNSWindow(UnsResponseJson.gatekeeperUrl);
                checkConnection("<?php echo $checkUrl ?>", UnsResponseJson.nonce, '<?php echo $sessionId;?>', '<?php echo $page;?>', '<?php echo $siteOption->getValue('uniqueId');?>', <?php echo $siteOption->getValue('authorization_interval') ? $siteOption->getValue('authorization_interval') : UnsWordPressAPI::DEFAULT_AUTHORIZATION_INTERVAL;?>, UnsResponseJson.guardianLink);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.error("Status: " + textStatus);
            }
        });
    }


    <?php
    }catch (\Exception $e) {
    ?>
    console.log("<?=$e->getMessage();?>");
    jQuery('.uns-login-container a').remove();
    <?php
    }

    echo $jsScript = ob_get_clean();
    wp_add_inline_script('unsproject-script', $jsScript, 'after');
    ?>

</script>
