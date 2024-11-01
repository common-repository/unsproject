<?php


namespace UNSProjectApp;


use Exception;
use Srp\Srp;
use UNSProjectApp\Helpers\SrpHelper;
use WP_User;

class SrpService
{
    public static $updateAction = null;
    /**
     * @var SiteOptions
     */
    private $siteOptions;

    /**
     * @var false|WP_User
     */
    private $user = false;

    /**
     * @var SrpHelper
     */
    private $srpHelper;

    /**
     * @var array|null
     */
    private $srpCredentials;
    /**
     * @var Srp
     */
    private $srp;
    /**
     * @var DatabaseService
     */
    private $databaseService;

    /**
     * SrpService constructor.
     * @param SiteOptions $siteOptions
     */
    public function __construct(SiteOptions  $siteOptions){
        $this->siteOptions = $siteOptions;
        $this->srpHelper = new SrpHelper($this->siteOptions);
        $this->srp = new Srp();
        $this->databaseService = new DatabaseService();
    }

    /**
     * @return bool
     */
    public function checkIfEnabled(){
        return (int)$this->siteOptions->getValue('activate_srp') === 1;
    }

    /**
     * @param string $username
     */
    private function initUserByUserName($username){
        $this->user = $this->srpHelper->getUserByUsernameOrEmail($username);
    }

    /**
     * @param false|WP_User $user
     */
    private function initUserByWordPressUser($user){
        $this->user = $user;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getCurrentUserEmail(){
        if($this->user === false || $this->user instanceof WP_User === false){
            throw new Exception('Wrong user credentials',UnsAppException::SRP_WRONG_USER_CREDENTIALS);
        }
        return $this->user->get('user_email');
    }

    public function getCurrentUser(){
        return $this->user;
    }

    /**
     * @param WP_User|string $user
     */
    public function init($user){
        if(!$this->checkIfEnabled()){
            return;
        }

        if(is_string($user)){
            $this->initUserByUserName($user);
        }else if($user instanceof WP_User){
            $this->initUserByWordPressUser($user);
        }

        $this->srpCredentials = $this->databaseService->getSrpCredentials($this->user->ID);
    }

    /**
     * @param $password
     * @param bool $passwordCheck
     * @throws Exception
     */
    public function doRegisterUserIfNeeded($password, $passwordCheck = true){
        $forceReset = false;
        if (empty($this->srpCredentials)) {
            if (
                $passwordCheck
                && wp_check_password($password, $this->user->get('user_pass')) === false
            ) {
                throw new Exception('Wrong user credentials(2).', UnsAppException::SRP_WRONG_USER_CREDENTIALS_2);
            }

            $this->srpHelper->initClientPhase0($this->getCurrentUserEmail(), $password);
            $this->databaseService->insertUserCredentials(
                $this->user->ID,
                $this->srpHelper->getSessionValue('s'),
                $this->srpHelper->getSessionValue('v')
            );
            $forceReset = true;
        } else {
            $this->srpHelper->setSessionValue('s', $this->srpCredentials['s']);
            $this->srpHelper->setSessionValue('v', $this->srpCredentials['v']);
        }

        if($forceReset) {
            $this->deletePasswordFromUserTable();
        }
    }

    /**
     * @param string $newPassword
     * @throws Exception
     */
    public function resetPassword($newPassword){
        if(self::$updateAction !== null){
            return;
        }
        self::$updateAction = true;
        $this->srpHelper->resetSrp();
        $this->srpHelper->initClientPhase0($this->getCurrentUserEmail(), $newPassword);
        if(empty($this->srpCredentials)) {
            $this->databaseService->insertUserCredentials(
                $this->user->ID,
                $this->srpHelper->getSessionValue('s'),
                $this->srpHelper->getSessionValue('v')
            );
        }else {
            $this->databaseService->updateUserCredentials(
                $this->user->ID,
                $this->srpHelper->getSessionValue('s'),
                $this->srpHelper->getSessionValue('v')
            );
        }
    }

    public function deletePasswordFromUserTable()
    {
        if(!empty($this->siteOptions->getValue('delete_password_srp'))){
            $user_data = wp_update_user([
                'ID' => $this->user->ID,
                'user_pass' => uniqid('RAND').time()
                    .str_shuffle('-=+1234567890abcdefghijklmnopqrstuvwxyz!@#$%&*))*&^%$#@!')
                    .md5(time().uniqid(rand(0,999)))
            ]);
        }
    }

    /**
     * @return SrpHelper
     */
    public function getSrpHelper(){
        return $this->srpHelper;
    }

}