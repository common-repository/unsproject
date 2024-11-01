<?php

use UNSProjectApp\DatabaseService;
use UNSProjectApp\Helpers\SrpHelper;
use UNSProjectApp\Libraries\JWT;
use UNSProjectApp\SiteOptions;
use UNSProjectApp\UnsAppException;
use UNSProjectApp\UnsWordPressAPI;

add_action('rest_api_init', function () {
    register_rest_route(rtrim(UnsWordPressAPI::API_NAMESPACE, '/\\'), UnsWordPressAPI::SRP_REGISTER,
        [
            'methods' => 'POST',
            'callback' => function ($request) {
                try {
                    if (!isset($_REQUEST['JWT'])) {
                        throw  new Exception('Missing `JWT` parameter.', UnsAppException::SRP_ROUTES_MISSING_JWT);
                    }

                    $siteOptions = new SiteOptions();
                    $jwt = $_REQUEST['JWT'];
                    $payload = (array)JWT::decode(
                        $jwt,
                        base64_decode($siteOptions->getValue('public_key')),
                        ['RS256']
                    );

                    if ($payload === null) {
                        throw  new Exception('Something is wrong with your JWT payload.',UnsAppException::SRP_JWT_PAYLOAD);
                    }
                    if (!isset($payload['user_id']) || !isset($payload['s']) || !isset($payload['v'])) {
                        throw new Exception('JWT payload is malformed.',UnsAppException::SRP_JWT_PAYLOAD_MALFORMED);
                    }
                    $databaseService = new DatabaseService();

                    $I = $payload['user_id'];
                    $srpUserCredentials = $databaseService->getSrpCredentials($I);
                    if (!empty($srpUserCredentials)) {
                        throw new Exception('User already is registered.', UnsAppException::SRP_USER_ALREADY_REGISTERED);
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
                        throw new Exception('Missing JWT.', UnsAppException::SRP_MISSING_JWT);
                    }
                    $siteOptions = new SiteOptions();
                    $payload = (array)JWT::decode(
                        $request['JWT'],
                        base64_decode($siteOptions->getValue('public_key')),
                        ['RS256']
                    );
                    if (!isset($payload['I'])) {
                        throw new Exception('Wrong JWT. Missing I from payload.', UnsAppException::SRP_MISSING_I);
                    }
                    $srpHelper = new SrpHelper($siteOptions);
                    $currentUser = $srpHelper->getUserByUsernameOrEmail($payload['I']);
                    if (empty($currentUser)) {
                        throw new Exception('Wrong user provided or user does not exist.', UnsAppException::SRP_WRONG_USER_PROVIDED);
                    }
                    $databaseService = new DatabaseService();
                    $srpCredentials = $databaseService->getSrpCredentials($currentUser->ID);
                    if (empty($srpCredentials)) {
                        throw new Exception('SRP credentials error.', UnsAppException::SRP_CREDENTIALS_ERROR);
                    }

                    if (!isset($payload['phase'])) {
                        throw new Exception('Invalid JWT provided. No phase parameter.', UnsAppException::SRP_NO_PHASE);
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

                            if ($payload['M1'] !== $M1_check) {
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
