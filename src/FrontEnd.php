<?php


namespace UNSProjectApp;


use UNSProjectApp\Helpers\View;
use UNSProjectApp\Libraries\JWT;

class FrontEnd
{
    const PAGE_LOGIN = 0;
    const PAGE_USER_PROFILE = 1;

    public function generateQRCodeViewWithTicketID($page, $useInFrontend = true){
        ob_start();
        try {
            $pluginOptions = new SiteOptions();
            if (
                $pluginOptions->getValue('validationCode') === null
                || $pluginOptions->getValue('uniqueId') === null
                || $pluginOptions->getValue('gatekeeperPublicKey') === null
                || $pluginOptions->getValue('siteValidated') === null
            ) {
                return '';
            }

            View::load('qrcode.php',[
                'siteOption'   =>  $pluginOptions,
                'page' => $page,
                'showOr' => $useInFrontend,
                'useInFrontend' => $useInFrontend,
            ]);
        }catch (\Exception $e){
            View::load('error.php',[
                'message'=> $e->getMessage()
            ]);
        }

        return ob_get_clean();
    }

    /**
     * @param $currentUserId
     * @param $encryptionKey
     * @return false|string
     */
    public function generateStatusPage($currentUserId, $encryptionKey){
        $apiEndpoint = UnsWordPressAPI::getDisconnectUrl();
        try {
            $jwt = JWT::encode([
                'wp_user_id' => $currentUserId,
                'exp' => time() + 60 * UnsWordPressAPI::DEFAULT_JWT_EXPIRATION
            ],
                base64_decode($encryptionKey),
                'RS256'
            );
        }catch (\Exception $e){
            return 'There was an error while trying to get the status. Please check `UNSproject` plugin configuration. '.$e->getMessage();
        }
        ob_start();
        ?>
        <div class="uns-status-connected">Status: <span>Connected</span></div>
        <div class="uns-button blue-button" onclick="unsDisconnectUser('<?php echo $apiEndpoint;?>', '<?php echo $jwt;?>');">DISCONNECT</div>
        <?php

        return ob_get_clean();
    }

}
