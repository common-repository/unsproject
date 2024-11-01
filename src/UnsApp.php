<?php

namespace UNSProjectApp;

use BrowserDetector\Browser;
use BrowserDetector\Device;
use BrowserDetector\Os;
use UNSProjectApp\Helpers\SessionHelper;
use UNSProjectApp\Libraries\JWT;

class UnsApp
{
    const CLEAR_PLUGIN_DATA_CODE = '0MaDJpMDQ5MTJrb3BxcFva3Itd2Uwb3ItMDI';
    const PRIVATE_KEY_BITS = 4096;
    const API_URL = 'https://gatekeeper.universalnameservice.com';
    const GUARDIAN_API_URL = 'https://guardian.universalnameservice.com';
    const DEFAULT_ATTESTATION_TYPE = 'email-verified';

    /**
     * @param string $serviceTicket
     * @return string
     */
    public static function generateQRCodeLink($serviceTicket)
    {
        return  $serviceTicket;
    }

    public static function checkPluginRequirements(){
        if (!extension_loaded('openssl')) {
            die('Openssl extension is missing.');
        }

        if(!extension_loaded('curl')){
            die('Curl extension is missing.');
        }

        if (substr(site_url(), 0, strlen('https')) !== 'https') {
            die('This plugin works only on https://.');
        }
    }

    /**
     * @param SiteOptions $siteOption
     *
     * @return boolean
     * @throws Libraries\JWTException|UnsAppException
     */
    public static function deleteService($siteOption)
    {
        if(empty($siteOption->getValue('private_key'))){
            throw new UnsAppException('Missing plugin config. Please contact UNSproject support team to help you.');
        }

        $parameters = [
            'body'  => json_encode([
                    'serviceNamespace' => $siteOption->getValue('serviceId')
            ]),
            'headers' => [
                'authorization' =>'Basic' . ' ' . JWT::encode(
                    [
                        'delete' => $siteOption->getValue('serviceId')
                    ],
                    base64_decode($siteOption->getValue('private_key')),
                    'RS256'
                ),
                'Content-Type' => 'application/json',
            ]
        ];

        $url = self::API_URL. '/api/services';
        ServerCall::delete($url, $parameters, $statusCode, $result);

        if(!in_array($statusCode, [200, 201])){
             throw new UnsAppException(
                 sprintf(
                     'Unable to delete the service. Status code: %s. Message: %s',
                     $statusCode,
                     strip_tags($result)
                 )
             );
         }
    }
    
    /**
     * @param SiteOptions $siteOption
     * @return SiteOptions
     * @throws \Exception
     */
    public static function initializeRegisterProcess($siteOption)
    {
        list($privateKey, $publicKey) = self::generateNewPrivatePublicKeys();

        $siteOption->setValue('public_key', base64_encode($publicKey));
        $siteOption->setValue('private_key', base64_encode($privateKey));
        $siteOption->setValue('site_url', site_url());
        $siteOption->setValue('uniqueId', str_replace(['https://','http://','/'],'', site_url()));

        $params = [
            'contactName' => $siteOption->getValue('contactName'),
            'email'       => $siteOption->getValue('email'),
            'phoneNumber' => $siteOption->getValue('phoneNumber'),
            'domain' => $siteOption->getValue('site_url'),
            'callbackPath' => UnsWordPressAPI::getCallBackPath(),
            'validationPath' => UnsWordPressAPI::getValidationPath(),
            'publicKey' => base64_decode($siteOption->getValue('public_key')),
        ];



        $url = self::API_URL . '/api/services';
        $requestParams =  [
            'body' => json_encode($params),
            'headers'=> [
                'Content-Type: application/json'
            ]
        ];
        $response = ServerCall::post($url,$requestParams , $statusCode, $plainResult);

        if (!isset($response['validationCode'])) {
            throw new \Exception('Wrong website configuration. Unable to get the validation code.' . $plainResult);
        }
        $siteOption->setValue('validationCode', $response['validationCode']);
        if(isset($response['gatekeeperPublicKey'])){
            $siteOption->setValue('gatekeeperPublicKey', base64_encode($response['gatekeeperPublicKey']));
        }
        if(isset($response['serviceId'])){
            $siteOption->setValue('serviceId', $response['serviceId']);
        }
        $siteOption->setValue('registeredSite', true);
        $siteOption->save();

        return $siteOption;
    }

    /**
     * @param SiteOptions $siteOption
     * @return bool
     * @throws \Exception
     */
    public static function initializeSiteValidation($siteOption){
        $params = [
            'serviceId' => $siteOption->getValue('serviceId'),
            'validationCode' => $siteOption->getValue('validationCode'),
        ];

        $result  = ServerCall::post(
            self::API_URL . '/api/services/validate',
            ['body' => json_encode($params)] ,
            $statusCode,
            $plainResult
        );
        if(isset($result['success']) && $result['success'] == 'true') {
            return true;
        }

        throw  new UnsAppException('Error while validating the website. '.ucfirst(strip_tags($plainResult)), UnsAppException::VALIDATION_ERROR);
    }

    /**
     * @param SiteOptions $pluginOption
     * @param bool $usedInFrontend
     * @param $userID
     * @return string|mixed
     * @throws Libraries\JWTException
     */
    public static function requestTicket($pluginOption, $usedInFrontend = false, $userID= 0)
    {
        $defaultAttestationType = $pluginOption->getValue('default_attestation_type') !== null
            ? $pluginOption->getValue('default_attestation_type')
            : self::DEFAULT_ATTESTATION_TYPE;

        //https://github.com/sinergi/php-browser-detector
        $browser = new Browser($_SERVER['HTTP_USER_AGENT']);
        $device = new Device($_SERVER['HTTP_USER_AGENT']);
        $os = new Os($_SERVER['HTTP_USER_AGENT']);

        $sessionId = SessionHelper::getSessionId();
        //Ticket request
        $params = [
            //'serviceNamespace' => $pluginOption->getValue('uniqueId'),
            'attestationTypeRequired' =>  $defaultAttestationType,
            'serviceId' => $pluginOption->getValue('serviceId'),
            'sessionId' => $sessionId,
            'browserName' => $browser->getName(),
            'osName' =>  $os->getName(),
        ];

        $url = self::API_URL . '/api/tickets';
        $key = base64_decode($pluginOption->getValue('private_key'));
        $jwt = JWT::encode($params, $key, 'RS256');

        $requestParameters = [
            'body' => $jwt,
            'headers' => [
                'Content-type' => 'application/jwt'
            ]
        ];
        $response = ServerCall::post($url,$requestParameters, $statusCode, $plainTextResult);

        try{
            $key = base64_decode($pluginOption->getValue('gatekeeperPublicKey'));
            $key = str_replace('\n', "\n", $key);
            $response = (array) JWT::decode($plainTextResult, $key, ['RS256']);

        }catch (\Exception $e){
            $statusCode = 400;
            $plainTextResult = 'Invalid Response.';
        }
        if ($statusCode !== 200) {
            throw new \Exception('Unable to generate ticket.'. strip_tags($plainTextResult));
        }
        $ticket = $response;
        $ticketId = $ticket['nonce'];

        $databaseService = new DatabaseService();
        if($usedInFrontend === true ){
            do_action('simple-logs', 'call made from FE: insertID: '. $userID, 'test');
            $databaseService->saveIntoTickets($ticketId,0, $sessionId);
        }else {
            $usersID = $databaseService->saveIntoUsersTable($userID,$defaultAttestationType, null);
            do_action('simple-logs', 'insertID: '. $userID, 'test');
            $databaseService->saveIntoTickets($ticketId, $usersID, $sessionId);
        }

        return  $ticket;
    }

    /**
     * @return array [$privateKey, $publicKey]
     */
    private static function generateNewPrivatePublicKeys()
    {
        $keyGenerator = openssl_pkey_new(array(
            "digest_alg" => "sha512",
            'private_key_bits' => self::PRIVATE_KEY_BITS,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ));

        openssl_pkey_export($keyGenerator, $privateKey);
        $details = openssl_pkey_get_details($keyGenerator);
        $publicKey = $details['key'];
        openssl_pkey_free($keyGenerator);

        return [$privateKey, $publicKey];
    }

    /**
     * @return string[]
     */
    public static function getAttestationTypes(){
        return [
            'none' => 'None',
            'email-not-verified' => 'Email not Verified',
            'email-verified' => 'Email verified',
            'pinned-with-pki' => 'Pinned with PKI',
            'pki' => 'PKI'
        ];
    }

    /**
     * @param string $attestationKeyToSearch
     * @return array
     */
    public static function getLowerAttestations($attestationKeyToSearch)
    {
        $allAttestations = array_keys(self::getAttestationTypes());

        $index = array_search($attestationKeyToSearch, $allAttestations);
        if($index === false){
            return [];
        }

        return array_splice($allAttestations,$index);
    }

}
