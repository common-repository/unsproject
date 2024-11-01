<?php


namespace UNSProjectApp\Helpers;


use Srp\Srp;
use UNSProjectApp\DatabaseService;
use UNSProjectApp\Libraries\JWT;
use UNSProjectApp\SiteOptions;

class SrpHelper
{
    /**
     * @var SiteOptions
     */
    private $siteOptions;
    /**
     * @var Srp
     */
    private $srp;

    private $session = [];

    public function __construct(SiteOptions $siteOptions)
    {
        $this->siteOptions = $siteOptions;
        $this->srp = new Srp();
    }

    /**
     * @param $username
     * @return false|\WP_User
     */
    public function getUserByUsernameOrEmail($username)
    {
        return is_email($username)
            ? get_user_by('email', $username)
            : get_user_by('login', $username);
    }

    /**
     * @param array $payload
     * @return string
     * @throws \UNSProjectApp\Libraries\JWTException
     */
    public function createJwt($payload)
    {
        return JWT::encode(
            $payload,
            base64_decode($this->siteOptions->getValue('private_key')),
            'RS256'
        );
    }

    /**
     * @param string $jwt
     * @return array
     * @throws \UNSProjectApp\Libraries\JWTException
     */
    public function decodeJWTAsArray($jwt){
        return (array) JWT::decode(
            $jwt,
            base64_decode($this->siteOptions->getValue('public_key')),
            ['RS256']
        );
    }


    /**
     * @return array
     */
    public function getSession(){
        return $this->session;
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function setSessionValue($key, $value){
        $this->session[$key] = $value;
    }

    /**
     * @param string $username
     * @param string $password
     */
    public function initClientPhase0($username, $password){
        $this->session['s'] = $this->srp->getRandomSeed();
        $this->session['x'] = $this->srp->generateX($this->session['s'], $username, $password);
        $this->session['v'] = $this->srp->generateV($this->session['x']);
    }

    public function initClientPhase1(){
        $this->session['a'] = $this->srp->getRandomSeed();
        $this->session['A'] = $this->srp->generateA($this->session['a']);
    }

    public function resetSrp(){
        $this->srp = new Srp();
        $this->session = [];
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $B
     */
    public function initClientPhase2($username, $password, $B){
        $this->session['x'] = $this->srp->generateX($this->getSessionValue('s'), $username, $password);
        $this->session['S'] = $this->srp->generateS_Client($this->session['A'], $B, $this->session['a'], $this->session['x']);
        $this->session['M1'] = $this->srp->generateM1($this->session['A'], $B, $this->session['S']);
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getSessionValue($key){
        return isset($this->session[$key])
            ? $this->session[$key]
            : null;
    }

}