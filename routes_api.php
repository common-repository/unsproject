<?php

use UNSProjectApp\DatabaseService;
use UNSProjectApp\Libraries\JWT;
use UNSProjectApp\SiteOptions;
use UNSProjectApp\UnsApp;
use UNSProjectApp\UnsAppException;
use UNSProjectApp\UnsWordPressAPI;

add_action('rest_api_init', function () {
    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::API_VALIDATION_ROUTE, [
            'methods' => 'GET',
            'callback' => function ($request) {
                /***
                 * @var $request WP_REST_Request
                 */
                try {
                    $siteOption = new SiteOptions();
                    if (empty($siteOption->getAll())) {
                        throw new Exception('Invalid site configuration. Credentials are empty.', UnsAppException::EMPTY_CREDENTIALS);
                    }
                    if ($siteOption->getValue('validationCode') === null) {
                        throw  new Exception('Missing validation code from config.', UnsAppException::MISSING_VALIDATION_CODE);
                    }

                    return $siteOption->getValue('validationCode');
                } catch (Exception $e) {
                    @header('Content-Type: application/json; charset=UTF-8');
                    wp_send_json_error([
                        'message' => $e->getMessage(),
                        'errorCode' => $e->getCode()
                    ],
                        400
                    );

                    return false;
                }
            },
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::API_CALLBACK_ROUTE, [
        'methods' => 'POST,GET,PUT,DELETE,HEAD,OPTIONS',
        'callback' => function ($request) {
            /***
             * @var $request WP_REST_Request
             */
            $php_i = file_get_contents('php://input');
            $phpInput = @json_decode($php_i, true);
            $siteOptions = new SiteOptions();
            do_action('simple-logs', "phpinput: " . print_r($phpInput,true),'phpinput');
            try {
                $key = base64_decode($siteOptions->getValue('gatekeeperPublicKey'));
                $key = str_replace('\n',"\n", $key);
                $decoded = (array) JWT::decode(
                    $phpInput['data'],
                    $key,
                    ['RS256']
                );
                do_action('simple-logs', print_r($decoded,true), 'test');
                if (isset($decoded['nonce'])
                    && isset($decoded['serviceUserId'])
                    && isset($decoded['sessionId'])
                ) {
                    $dataBaseService = new DatabaseService();
                    $dataBaseService->updateUserByTicketId(
                        $decoded['nonce'],
                        $decoded['serviceUserId'],
                        $decoded['sessionId']
                    );
                } else if (isset($decoded['nonce'])
                    && isset($decoded['guardianUrl'])
                ){
                    $dataBaseService = new DatabaseService();
                    $dataBaseService->updateGuardianUrlByTicketId(
                        $decoded['nonce'],
                        $decoded['guardianUrl']
                    );
                }
            } catch (Exception $e) {
                do_action('simple-logs', 'Error on callback : ' . $e->getMessage(), 'test');
                @header('Content-Type: application/json; charset=UTF-8');
                wp_send_json_error([
                    'message' => $e->getMessage(),
                    'errorCode' => $e->getCode()
                ],
                    400
                );
            }
        },
        'permission_callback' => '__return_true',
    ]);


    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::CREATE_TICKET, [
            'methods' => 'POST',
            'callback' => function ($request) {
                /***
                 * @var $request WP_REST_Request
                 */
                do_action('simple-logs', print_r($_REQUEST, true), 'log' );
                try {
                    $pluginOptions = new SiteOptions();
                    if (empty($pluginOptions->getAll())
                        || $pluginOptions->getValue('validationCode') === null
                        || $pluginOptions->getValue('uniqueId') === null
                        || $pluginOptions->getValue('gatekeeperPublicKey') === null
                        || $pluginOptions->getValue('siteValidated') === null
                    ) {
                        do_action('simple-logs', 'Error-invalid site config', 'error');
                        throw new Exception('Invalid site configuration. Credentials are empty.', UnsAppException::EMPTY_CREDENTIALS);
                    }

                    $useInFrontend = true;
                    $userId = 0;
                    if(isset($_REQUEST['jwt'])){
                        $payload = (array)JWT::decode(
                            $_REQUEST['jwt'],
                            base64_decode($pluginOptions->getValue('public_key')),
                            ['RS256']
                        );
                        do_action('simple-logs', 'Payload ' . ($payload['useInFrontend'] === 0 ? 'true':'false'). print_r($payload,true), 'debug');
                        if(isset($payload['useInFrontend'])){
                            $useInFrontend = (bool) $payload['useInFrontend'];
                        }
                        if(isset($payload['userId'])){
                            $userId = (int) $payload['userId'];
                        }

                    }
                    $serviceTicket = UnsApp::requestTicket($pluginOptions, $useInFrontend, $userId);
                    return json_encode($serviceTicket);
                } catch (Exception $e) {
                    @header('Content-Type: application/json; charset=UTF-8');
                    do_action('simple-logs', $e->getMessage(), 'error');
                    wp_send_json_error([
                        'message' => $e->getMessage(),
                        'errorCode' => $e->getCode()
                    ],
                        400
                    );

                    return false;
                }
            },
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::VERIFY_TICKET, [
        'methods' => 'POST',
        'callback' => function ($request) {
            try {
                $php_i = file_get_contents('php://input');
                if (!isset($request['serviceTicket']) || !isset($request['sessionId'])) {
                    throw new Exception('Missing ticketID or Session ID parameter.', UnsAppException::MISSING_TICKET_OR_SESSION);
                }
                $siteOptions = new SiteOptions();
                if ($siteOptions->getValue('gatekeeperPublicKey') === null) {
                    throw new Exception('Login not initialized', UnsAppException::LOGIN_NOT_INITIALIZED);
                }

                $ticketId = sanitize_text_field($_REQUEST['serviceTicket']);
                $sessionId = sanitize_text_field($_REQUEST['sessionId']);

                $databaseService = new DatabaseService();
                $ticket = $databaseService->getTicketById($ticketId, $sessionId);
                $wordPressUSerId = $databaseService->getWordPresSUserIDByTicketID($ticketId);

                if (
                    !empty(get_option('users_can_register'))
                    && $wordPressUSerId === 0
                    && isset($ticket['uns_user_id'])
                    && !empty($ticket['uns_user_id'])
                    && empty($ticket['users_id'])
                ) {
                    return json_encode([
                        'message' => 'Need registration.',
                        'wp_user_id' => $wordPressUSerId,
                        'action' => 'register',
                        'jwt' => JWT::encode(
                            [
                                'ticket_id' => $ticketId,
                                'wp_user_id' => $wordPressUSerId,
                                'action' => 'register',
                                'exp' => time() + 60 * UnsWordPressAPI::DEFAULT_JWT_EXPIRATION
                            ],
                            base64_decode($siteOptions->getValue('private_key')),
                            'RS256'
                        ),
                        'status' => 'success',
                    ]);
                } else if ($wordPressUSerId === 0) {
                    if(!empty($ticket['callback']) && isset($ticket['guardian_url'])){
                        @header('Content-Type: application/json; charset=UTF-8');
                        return json_encode([
                            'message' => 'Confirm Guardian URL.',
                            'guardianUrl' => $ticket['guardian_url'],
                            'callback' => $ticket['callback'],
                            'action' => 'login-guardian',
                            'status' => 'success',
                        ]);
                    }
                    throw new Exception(
                        "ticketID: " . $ticketId . ' : Waiting for authorization... ',
                        UnsAppException::WAITING_FOR_AUTHORIZATION
                    );
                }
                @header('Content-Type: application/json; charset=UTF-8');
                return json_encode([
                    'message' => 'Authenticated.',
                    'wp_user_id' => $wordPressUSerId,
                    'action' => 'login',
                    'jwt' => JWT::encode([
                        'wp_user_id' => $wordPressUSerId,
                        'action' => 'login',
                        'exp' => time() + 60 * UnsWordPressAPI::DEFAULT_JWT_EXPIRATION
                    ],
                        base64_decode($siteOptions->getValue('private_key')),
                        'RS256'
                    ),
                    'status' => 'success',
                ]);
            } catch (Exception $e) {
                @header('Content-Type: application/json; charset=UTF-8');
                wp_send_json_error([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errorCode' => $e->getCode()
                ],
                    400
                );
            }
        },
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::AUTOLOGIN, [
        'methods' => 'GET,POST',
        'callback' => function ($request) {
            try {
                if (!isset($_REQUEST['jwt'])) {
                    throw  new Exception('Missing `jwt` parameter.');
                }
                $siteOptions = new SiteOptions();

                $payload = (array)JWT::decode(
                    sanitize_text_field($_REQUEST['jwt']),
                    base64_decode($siteOptions->getValue('public_key')),
                    ['RS256']
                );
                if (!isset($payload['wp_user_id'])) {
                    throw new Exception('Invalid JWT payload.', UnsAppException::INVALID_JWT_PAYLOAD);
                }
                $user = get_userdata((int)$payload['wp_user_id']);
                if (empty($user)) {
                    throw  new Exception('WordPress user ' . $payload['wp_user_id'] . ' was not found.');
                }
                //Auth user
                wp_set_current_user($user->get('id'));
                wp_set_auth_cookie($user->get('id'));

                do_action('wp_login', $user->get('user_login'), $user);

                wp_redirect(admin_url(), 301);
                exit();
            } catch (\Exception $e) {
                die($e->getMessage() . '. We can not auto login into WordPress. Please contact the site administrator.');
            }
        },
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::REGISTER_USER, [
        'methods' => 'GET,POST',
        'callback' => function ($request) {
            try {
                if (!isset($_REQUEST['jwt'])) {
                    throw  new Exception('The `jwt` parameter is missing.');
                }
                if (!isset($_REQUEST['email'])) {
                    throw  new Exception('The `email` parameter is missing.');
                } else if (is_email($_REQUEST['email']) === false) {
                    throw  new Exception('The `email` parameter is not a valid email address.');
                }
                if (!isset($_REQUEST['username'])) {
                    throw  new Exception('The `username` parameter is missing.');
                }

                $siteOptions = new SiteOptions();

                $payload = (array)JWT::decode(
                    sanitize_text_field($_REQUEST['jwt']),
                    base64_decode($siteOptions->getValue('public_key')),
                    ['RS256']
                );
                if (!isset($payload['ticket_id'])) {
                    throw new Exception('Invalid JWT payload.', UnsAppException::NO_TICKET_ID);
                }

                if (empty(get_option('users_can_register'))) {
                    throw  new Exception('Register is disabled on this website.');
                }

                $chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
                $randomPass = substr(str_shuffle($chars), 0, 10);

                $result = wp_insert_user([
                    'user_pass' => $randomPass,
                    'user_login' => sanitize_text_field($_REQUEST['username']),
                    'user_email' => sanitize_email($_REQUEST['email']),
                ]);

                if (!is_int($result)) {
                    throw  new Exception('User Already exists.', UnsAppException::USER_ALREADY_EXISTS);
                }

                $user = new \WP_User($result);
                $user->set_role('subscriber');

                $defaultAttestationType = $siteOptions->getValue('default_attestation_type') !== null
                    ? $siteOptions->getValue('default_attestation_type')
                    : UnsApp::DEFAULT_ATTESTATION_TYPE;

                $databaseService = new DatabaseService();
                $linked = $databaseService->linkTicketUnsUserIdWithWordPressRegisteredUser(
                    $user->get('id'),
                    $payload['ticket_id'],
                    $defaultAttestationType
                );

                if ($linked === false) {
                    throw new Exception(
                        'Unable to connect your UNS user with this WordPress User.',
                        UnsAppException::UNABLE_TO_CONNECT_WITH_WORDPRESS_USER
                    );
                }

                wp_set_current_user($user->get('id'));
                wp_set_auth_cookie($user->get('id'));
                do_action('wp_login', $user->get('user_login'), $user);

                wp_redirect(admin_url(), 301);
                exit();
            } catch (\Exception $e) {
                die('Unable to register in WordPress. ' . $e->getMessage());
            }
        },
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::DISCONNECT_USER, [
        'methods' => 'POST',
        'callback' => function ($request) {
            try {
                if (!isset($_REQUEST['jwt'])) {
                    throw  new Exception('Missing `jwt` parameter.');
                }
                $siteOptions = new SiteOptions();

                $payload = (array)JWT::decode(
                    sanitize_text_field($_REQUEST['jwt']),
                    base64_decode($siteOptions->getValue('public_key')),
                    ['RS256']
                );
                if (!isset($payload['wp_user_id'])) {
                    throw new Exception('Invalid JWT payload.', UnsAppException::MISSING_WP_USER_ID);
                }
                $databaseService = new DatabaseService();
                $databaseService->deleteUserConnections($payload['wp_user_id']);
                return json_encode([
                    'success' => true,
                    'wp_user_id' => $payload['wp_user_id'],
                    'message' => 'User successfully disconnected.'
                ]);
            } catch (\Exception $e) {
                @header('Content-Type: application/json; charset=UTF-8');
                wp_send_json_error([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errorCode' => $e->getCode()
                ],
                    400
                );

            }
            return '';
        },
        'permission_callback' => '__return_true',
    ]);
});

