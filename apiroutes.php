<?php

use UNSProjectApp\DatabaseService;
use UNSProjectApp\Helpers\ShortUrlGenerator;
use UNSProjectApp\Libraries\JWT;
use UNSProjectApp\SiteOptions;
use UNSProjectApp\UnsApp;
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
                        throw new Exception('Invalid site configuration. Credentials are empty.');
                    }
                    if ($siteOption->getValue('validationCode') === null) {
                        throw  new Exception('Missing validation code from config.');
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
            $phpInput = @json_decode(file_get_contents('php://input'), true);
            $siteOptions = new SiteOptions();
            try {
                $decoded = (array)JWT::decode($phpInput['data'], $siteOptions->getValue('gatekeeperPublicKey'), ['RS256']);
                if (isset($decoded['userID']) && isset($decoded['ticketID'])) {
                    $dataBaseService = new DatabaseService();
                    $dataBaseService->updateUserByTicketId($decoded['ticketID'], $decoded['userID']);
                }
            } catch (Exception $e) {
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

    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::REDIRECT, [
        'methods' => 'POST,GET,PUT,DELETE,HEAD,OPTIONS',
        'callback' => function ($request) {
            try {
                if (!isset($request['url'])) {
                    throw  new Exception('Missing url parameter from Request.');
                }
                $siteOptions = new SiteOptions();
                $shortUrlKey = urldecode($request['url']);
                if (
                    $siteOptions->getValue('private_key') === null
                    || $siteOptions->getValue('uniqueId') === null
                ) {
                    throw  new Exception('Wrong configuration for redirect.');
                }

                $shortUrl = new ShortUrlGenerator(
                    $siteOptions->getValue('private_key'),
                    $siteOptions->getValue('uniqueId')
                );
                $longUrl = $shortUrl->getLongURL($shortUrlKey);

                if ($longUrl === false || $longUrl == null) {
                    throw new Exception('Invalid URL.(' . ($longUrl === null ? 'null' : ($longUrl === false ? 'false' : '')));
                }

                @header('Content-Type: text/html; charset=UTF-8');
                header('Location: ' . $longUrl);
                header("HTTP/1.1 301 Moved Permanently");
                exit;

            } catch (\Exception $e) {
                @header('Content-Type: text/html; charset=UTF-8');
                return $e->getMessage();
            }
        },
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::VERIFY_TICKET, [
        'methods' => 'POST',
        'callback' => function ($request) {
            try {
                if (!isset($request['serviceTicket'])) {
                    throw new Exception('Missing validation parameter.', 1);
                }
                $siteOptions = new SiteOptions();
                if ($siteOptions->getValue('gatekeeperPublicKey') === null) {
                    throw new Exception('Login not initialized');
                }

                $serviceTicket = sanitize_text_field($_REQUEST['serviceTicket']);
                list($header, $payload, $signature) = explode('.', $serviceTicket);

                $payload = json_decode(base64_decode($payload), true);
                $decoded = (array)JWT::decode(
                    $payload['authenticationTicket'],
                    $siteOptions->getValue('gatekeeperPublicKey'),
                    ['RS256']
                );

                if (!isset($decoded['ticketID'])) {
                    throw new Exception('Invalid Service ticket.', 3);
                }
                $ticketId = $decoded['ticketID'];
                $databaseService = new DatabaseService();
                $ticket = $databaseService->getTicketById($ticketId);
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
                        'jwt' => JWT::encode([
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
                    throw new Exception("ticketID: " . $ticketId . ' : Waiting for authorization... ');
                }

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
                    throw new Exception('Invalid JWT payload.');
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
                    throw new Exception('Invalid JWT payload.');
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
                    throw  new Exception('User Already exists.');
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
                    throw new Exception('Unable to connect your UNS user with this WordPress User.');
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
                    throw new Exception('Invalid JWT payload.');
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

    //SRP routes
    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::SRP_REGISTER,
        [
            'methods' => 'POST',
            'callback' => function ($request) {
                try {
                    if (!isset($_REQUEST['JWT'])) {
                        throw  new Exception('Missing `JWT` parameter.');
                    }

                    $siteOptions = new SiteOptions();
                    $jwt = $_REQUEST['JWT'];
                    $payload = (array)JWT::decode(
                        $jwt,
                        base64_decode($siteOptions->getValue('public_key')),
                        ['RS256']
                    );

                    if ($payload === null) {
                        throw  new Exception('Something is wrong with your JWT payload.');
                    }
                    if(!isset($payload['user_id']) || !isset($payload['s']) || !isset($payload['v'])){
                        throw new Exception('JWT payload is malformed.');
                    }
                    $databaseService = new DatabaseService();

                    $I = $payload['user_id'];
                    $srpUserCredentials = $databaseService->getSrpCredentials($I);
                    if (!empty($srpUserCredentials)) {
                        throw new Exception('User already is registered.');
                    }
                    $s = $payload["s"];
                    $v = $payload["v"];

                    $databaseService->insertUserCredentials($I, $s, $v);
                    return json_encode([
                        "success" => true
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
                    return '';
                }

            },
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::SRP_LOGIN,
        [
            'methods' => 'POST',
            'callback' => function ($request) {
                try {
                    header('Content-type:application/json');
                    if (!isset($request['JWT'])) {
                        throw new Exception('Missing JWT.');
                    }
                    $siteOptions = new SiteOptions();
                    $payload = (array) JWT::decode(
                        $request['JWT'],
                        base64_decode($siteOptions->getValue('public_key')),
                        ['RS256']
                    );
                    if(!isset($payload['I'])){
                        throw new Exception('Wrong JWT. Missing I from payload.');
                    }
                    $srpHelper = new \UNSProjectApp\Helpers\SrpHelper($siteOptions);
                    $currentUser = $srpHelper->getUserByUsernameOrEmail($payload['I']);
                    if(empty($currentUser)){
                        throw new Exception('Wrong user provided or user does not exist.');
                    }
                    $databaseService = new DatabaseService();
                    $srpCredentials = $databaseService->getSrpCredentials($currentUser->ID);
                    if(empty($srpCredentials)){
                        throw new Exception('SRP credentials error');
                    }

                    if(!isset($payload['phase'])){
                        throw new Exception('Invalid JWT provided. No phase parameter.');
                    }
                    $srp = new Srp\Srp();
                    switch ((int)$payload['phase']) {
                        case 1:
                            $session = [];
                            $session["v"] = $srpCredentials['v'];
                            $session["A"] = $payload['A'];

                            $session["b"] = $srp->getRandomSeed();
                            $session["B"] = $srp->generateB($session["b"], $session["v"]);

                            return wp_send_json(
                                [
                                    'success' => true,
                                    'JWT' => $srpHelper->createJwt($session)
                                ]
                            );
                            break;
                        case 2:
                            $session = [];
                            $session["S"] = $srp->generateS_Server(
                                $payload["A"],
                                $payload["B"],
                                $payload["b"],
                                $srpCredentials["v"]
                            );
                            $M1_check = $srp->generateM1($payload["A"], $payload["B"], $session["S"]);

                            if($payload['M1'] !== $M1_check){
                                echo wp_send_json([
                                    "success" => false,
                                    'message' => 'M1 !== M1check'
                                ]);
                                exit;
                            }

                            echo wp_send_json([
                                "success" => true,
                                'JWT' => $srpHelper->createJwt(
                                    $session
                                )
                            ]);
                            exit;

                            break;
                    }

                } catch (\Exception $e) {
                    @header('Content-Type: application/json; charset=UTF-8');
                    wp_send_json_error([
                        'status' => 'error',
                        'message' => $e->getMessage(),
                        'errorCode' => $e->getCode()
                    ],
                        400
                    );
                    return '';
                }

            },
            'permission_callback' => '__return_true',
        ]
    );
});

