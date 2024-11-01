<?php


namespace UNSProjectApp;


class UnsWordPressAPI
{
    const API_NAMESPACE = 'unsproject/v1';

    const API_VALIDATION_ROUTE = '/validate';
    const API_CALLBACK_ROUTE = '/callback';
    const VERIFY_TICKET = '/verifyTicket';
    const AUTOLOGIN = '/verifyTicket/autologin';
    const REGISTER_USER = '/verifyTicket/register';
    const DISCONNECT_USER = '/disconnect';
    const SRP_REGISTER = '/srp/register';
    const SRP_LOGIN = '/srp/login';
    const DEFAULT_JWT_EXPIRATION = 10; //minutes
    const DEFAULT_AUTHORIZATION_INTERVAL = 1000; //milliseconds
    const CREATE_TICKET = '/ticket';

    /**
     * @return string
     */
    public static function getValidationPath(){
         return self::getApiPath() . self::API_VALIDATION_ROUTE;
    }

    /**
     * @return string
     */
    public static function getCallBackPath(){
        return self::getApiPath() . self::API_CALLBACK_ROUTE;
    }

    /**
     * @return string
     */
    public static  function getApiPath(){
        return '/?rest_route=/' . self::API_NAMESPACE;
    }

    /**
     * @return string
     */
    public static function getVerifyTicketUrl(){
        return site_url(). self::getApiPath().self::VERIFY_TICKET;
    }

    /**
     * @return string
     */
    public static function getDisconnectUrl()
    {
        return site_url().self::getApiPath().self::DISCONNECT_USER;
    }

    public static function getCreateTicketApiUrl()
    {
        return site_url().self::getApiPath().self::CREATE_TICKET;
    }

}
