<?php

use UNSProjectApp\ServerCall;
use UNSProjectApp\SiteOptions;
use UNSProjectApp\SrpService;
use UNSProjectApp\UnsAppException;
use UNSProjectApp\UnsWordPressAPI;

function uns_project_verify_username_password($user, $username, $password)
{
    if (isset($_REQUEST['log']) && isset($_REQUEST['pwd'])) {
        $siteOptions = new SiteOptions();
        $srpService = new SrpService($siteOptions);
        $srpService->init($username);

        if ($srpService->checkIfEnabled()) {
            try {
                if(empty($srpService->getCurrentUser())){
                    throw new Exception('Invalid user.', UnsAppException::SRP_INVALID_USER);
                }
                $srpService->doRegisterUserIfNeeded($password,true);

                $srpService->getSrpHelper()->initClientPhase1();
                $srp = new \Srp\Srp();

                //Server phase 1:
                $session = [];
                $session["A"] = $srpService->getSrpHelper()->getSessionValue('A');
                $result1 = ServerCall::post(
                    site_url() . UnsWordPressAPI::getApiPath() . UnsWordPressAPI::SRP_LOGIN,
                    [
                        'body' => [
                            'JWT' => $srpService->getSrpHelper()->createJwt([
                                'I' => $srpService->getCurrentUserEmail(),
                                'A' => $session['A'],
                                'phase' => 1
                            ])
                        ],
                    ],
                    $statusCode,
                    $callResult1
                );

                if (!isset($result1['JWT'])) {
                    throw new Exception('Missing JWT from for Step1', UnsAppException::SRP_MISSING_JWT);
                }
                $result1Array = $srpService->getSrpHelper()->decodeJWTAsArray($result1['JWT']);
                $session = array_merge($session, $result1Array);

                //Login phase 2:
                $srpService->getSrpHelper()->initClientPhase2(
                    $srpService->getCurrentUserEmail(),
                    $password,
                    $session['B']
                );

                //Server phase 2:
                $result2 = ServerCall::post(
                    site_url() . UnsWordPressAPI::getApiPath() . UnsWordPressAPI::SRP_LOGIN,
                    [
                        'body' => [
                            'JWT' => $srpService->getSrpHelper()->createJwt([
                                'I' => $srpService->getCurrentUserEmail(),
                                'A' => $session['A'],
                                'B' => $session['B'],
                                'b' => $session['b'],
                                'M1' => $srpService->getSrpHelper()->getSessionValue('M1'),
                                'phase' => 2
                            ])
                        ],
                    ],
                    $statusCode,
                    $callResult2
                );
                if(!isset($result2['JWT'])){
                    $message = isset($result2['message'])
                        ? $result2['message']
                        : 'Missing JWT from step2.';

                    throw new Exception("SRP error. ". $message, UnsAppException::SRP_ERROR);
                }
                $session = array_merge(
                    $session,
                    $srpService->getSrpHelper()->decodeJWTAsArray($result2['JWT'])
                );

                $M2 = $srp->generateM2(
                    $session["A"],
                    $srpService->getSrpHelper()->getSessionValue('M1'),
                    $session["S"]
                );

                //Final validation:
                $M2_check = $srp->generateM2(
                    $srpService->getSrpHelper()->getSessionValue('A'),
                    $srpService->getSrpHelper()->getSessionValue('M1'),
                    $srpService->getSrpHelper()->getSessionValue('S')
                );

                if ($M2 === null || $M2 !== $M2_check) {
                    throw new Exception('M2 is different than M2_check.', UnsAppException::SRP_M2_ERROR);
                }

            } catch (Exception $e) {
                add_filter('login_errors', function () use ($e) {
                    return "SRP Error : " . $e->getMessage();
                });
                return false;
            }

            wp_set_current_user($srpService->getCurrentUser()->ID);
            wp_set_auth_cookie($srpService->getCurrentUser()->ID);

            do_action('wp_login', $srpService->getCurrentUser()->get('user_login'), $srpService->getCurrentUser());
            wp_redirect(admin_url());

            die();
        }
    }
}
add_filter( 'authenticate', 'uns_project_verify_username_password', 1, 3);

add_action('password_reset', function($user, $new_pass){
    if(empty($user)){
        return;
    }
    $siteOptions= new SiteOptions();
    $srpService = new SrpService($siteOptions);
    $srpService->init($user);

    if($srpService->checkIfEnabled() === false){
        return;
    }
    try {
        $srpService->resetPassword($new_pass);
        $srpService->deletePasswordFromUserTable();
    }catch (\Exception $e){
        die($e->getMessage());
    }

},10,2);

add_action( 'show_user_profile', 'my_profile_update');

function my_profile_update( $userProfile ) {
    ?>
    <input type="hidden" name="uns_srp_reset_password" value="1" />
    <?php
}

function uns_project_my_profile_update( $user_id ) {
    if(
        isset($_REQUEST['action'])
        && $_REQUEST['action'] == 'update'
        && isset($_REQUEST['pass1'])
        && isset($_REQUEST['pass2'])
        && $_REQUEST['pass1'] === $_REQUEST['pass2']
        && $_REQUEST['from'] === 'profile'
        && $_POST['uns_srp_reset_password']
    ){
        $siteOptions= new SiteOptions();
        $srpService = new SrpService($siteOptions);
        if($srpService::$updateAction !== null){
            return;
        }
        $user = get_user_by('ID', $user_id);
        $srpService->init($user);

        try {
            $srpService->resetPassword($_REQUEST['pass1']);
            $srpService->deletePasswordFromUserTable();
        }catch (\Exception $e){
            die($e->getMessage());
        }
    }
}

add_action( 'profile_update', 'uns_project_my_profile_update' ,10, 1);
