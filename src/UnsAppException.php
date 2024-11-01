<?php


namespace UNSProjectApp;


class UnsAppException extends \Exception
{
    const SRP_WRONG_USER_CREDENTIALS = 1;
    const SRP_INVALID_USER = 2;
    const SRP_MISSING_JWT = 3;
    const SRP_WRONG_USER_CREDENTIALS_2 = 4;
    const SRP_ERROR = 5;
    const SRP_M2_ERROR = 6;
    const SRP_ROUTES_MISSING_JWT = 7;
    const SRP_JWT_PAYLOAD = 8;
    const SRP_JWT_PAYLOAD_MALFORMED = 9;
    const SRP_USER_ALREADY_REGISTERED = 10;
    const SRP_MISSING_I = 11;
    const SRP_WRONG_USER_PROVIDED = 12;
    const SRP_CREDENTIALS_ERROR = 13;
    const SRP_NO_PHASE = 14;

    const VALIDATION_ERROR = 1;
    const EMPTY_CREDENTIALS = 2;
    const MISSING_VALIDATION_CODE = 3;
    const MISSING_TICKET_OR_SESSION = 1;
    const LOGIN_NOT_INITIALIZED = 4;
    const WAITING_FOR_AUTHORIZATION = 5;
    const INVALID_JWT_PAYLOAD = 6;
    const NO_TICKET_ID = 7;
    const USER_ALREADY_EXISTS = 8;
    const UNABLE_TO_CONNECT_WITH_WORDPRESS_USER = 9;
    const MISSING_WP_USER_ID = 10;

}