<?php


namespace UNSProjectApp\Libraries;


use ArrayAccess;
use DateTime;

class JWT
{
    /**
     * When checking nbf, iat or expiration times,
     * we want to provide some extra leeway time to
     * account for clock skew.
     */
    public static $leeway = 0;
    /**
     * Allow the current timestamp to be specified.
     * Useful for fixing a value within unit testing.
     *
     * Will default to PHP time() value if null.
     */
    public static $timestamp = null;
    public static $supported_algs = [
        'HS256' => ['hash_hmac', 'SHA256'],
        'HS512' => ['hash_hmac', 'SHA512'],
        'HS384' => ['hash_hmac', 'SHA384'],
        'RS256' => ['openssl', 'SHA256'],
        'RS384' => ['openssl', 'SHA384'],
        'RS512' => ['openssl', 'SHA512'],
    ];

    /**
     * Decodes a JWT string into a PHP object.
     *
     * @param string $jwt The JWT
     * @param string|array $key The key, or map of keys.
     *                                      If the algorithm used is asymmetric, this is the public key
     * @param array $allowed_algs List of supported verification algorithms
     *                                      Supported algorithms are 'HS256', 'HS384', 'HS512' and 'RS256'
     *
     * @return object The JWT's payload as a PHP object
     *
     * @throws JWTException
     * @uses urlsafeB64Decode
     * @uses jsonDecode
     */
    public static function decode($jwt, $key, array $allowed_algs = [])
    {
        $timestamp = is_null(static::$timestamp) ? time() : static::$timestamp;
        if (empty($key)) {
            throw new JWTException(
                __('Key may not be empty', 'uns-project-app'),
                JWTException::ERR_EMPTY_KEY
            );
        }
        $tks = explode('.', $jwt);

        if (count($tks) != 3) {
            throw new JWTException(
                __('Wrong number of segments', 'uns-project-app'),
                JWTException::ERR_WRONG_NUMBER_OF_SEGMENTS
            );
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;
        if (null === ($header = static::jsonDecode(static::urlsafeB64Decode($headb64)))) {
            throw new JWTException(
                __('Invalid header encoding', 'uns-project-app'),
                JWTException::ERR_INVALID_HEADER_ENCODING
            );
        }
        if (null === $payload = static::jsonDecode(static::urlsafeB64Decode($bodyb64))) {
            throw new JWTException(
                __('Invalid claims encoding', 'uns-project-app'),
                JWTException::ERR_INVALID_CLAIMS_ENCODING
            );
        }
        if (false === ($sig = static::urlsafeB64Decode($cryptob64))) {
            throw new JWTException(
                __('Invalid signature encoding', 'uns-project-app'),
                JWTException::ERR_INVALID_SIGNATURE_ENCODING
            );
        }
        if (empty($header->alg)) {
            throw new JWTException(
                __('Empty algorithm', 'uns-project-app'),
                JWTException::ERR_EMPTY_ALGORITHM
            );
        }
        if (empty(static::$supported_algs[$header->alg])) {
            throw new JWTException(
                __('Algorithm not supported', 'uns-project-app'),
                JWTException::ERR_ALGORITHM_NOT_SUPPORTED
            );
        }
        if (!in_array($header->alg, $allowed_algs)) {
            throw new JWTException(
                __('Algorithm not allowed', 'uns-project-app'),
                JWTException::ERR_ALGORITHM_NOT_ALLOWED
            );
        }
        if (is_array($key) || $key instanceof ArrayAccess) {
            if (isset($header->kid)) {
                if (!isset($key[$header->kid])) {
                    throw new JWTException(
                        __('`kid` invalid, unable to lookup correct key', 'uns-project-app'),
                        JWTException::ERR_INVALID_KID
                    );
                }
                $key = $key[$header->kid];
            } else {
                throw new JWTException(
                    __('`kid` empty, unable to lookup correct key', 'uns-project-app'),
                    JWTException::ERR_EMPTY_KID
                );
            }
        }
        // Check the signature
        if (!static::verify("$headb64.$bodyb64", $sig, $key, $header->alg)) {
            throw new JWTException(
                __('Signature verification failed', 'uns-project-app'),
                JWTException::ERR_SIGNATURE_VERIFICATION_FAILED
            );
        }
        // Check if the nbf if it is defined. This is the time that the
        // token can actually be used. If it's not yet that time, abort.
        if (isset($payload->nbf) && $payload->nbf > ($timestamp + static::$leeway)) {
            throw new JWTException(
                sprintf(
                    __('Cannot handle token prior to %s', 'uns-project-app'),
                    date(DateTime::ISO8601, $payload->nbf)
                ),
                JWTException::ERR_TOKEN_NBF
            );
        }
        // Check that this token has been created before 'now'. This prevents
        // using tokens that have been created for later use (and haven't
        // correctly used the nbf claim).
        if (isset($payload->iat) && $payload->iat > ($timestamp + static::$leeway)) {
            throw new JWTException(
                sprintf(
                    __('Cannot handle token prior to %s', 'uns-project-app'),
                    date(DateTime::ISO8601, $payload->iat)
                ),
                JWTException::ERR_TOKEN_IAT
            );
        }
        // Check if this token has expired.
        if (isset($payload->exp) && ($timestamp - static::$leeway) >= $payload->exp) {
            throw new JWTException(
                __('Expired token', 'uns-project-app'),
                JWTException::ERR_TOKEN_EXPIRED
            );
        }

        return $payload;
    }

    /**
     * Converts and signs a PHP object or array into a JWT string.
     *
     * @param object|array $payload PHP object or array
     * @param string $key The secret key.
     *                                  If the algorithm used is asymmetric, this is the private key
     * @param string $alg The signing algorithm.
     *                                  Supported algorithms are 'HS256', 'HS384', 'HS512' and 'RS256'
     * @param mixed $keyId
     * @param array $head An array with header elements to attach
     *
     * @return string A signed JWT
     *
     * @throws JWTException
     * @uses urlsafeB64Encode
     * @uses jsonEncode
     */
    public static function encode($payload, $key, $alg = 'HS256', $keyId = null, $head = null)
    {
        $header = ['typ' => 'JWT', 'alg' => $alg];
        if ($keyId !== null) {
            $header['kid'] = $keyId;
        }
        if (isset($head) && is_array($head)) {
            $header = array_merge($head, $header);
        }
        $segments = [];
        $segments[] = static::urlsafeB64Encode(static::jsonEncode($header));
        $segments[] = static::urlsafeB64Encode(static::jsonEncode($payload));
        $signing_input = implode('.', $segments);
        $signature = static::sign($signing_input, $key, $alg);
        $segments[] = static::urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * Sign a string with a given key and algorithm.
     *
     * @param string $msg The message to sign
     * @param string|resource $key The secret key
     * @param string $alg The signing algorithm.
     *                                  Supported algorithms are 'HS256', 'HS384', 'HS512' and 'RS256'
     *
     * @return string An encrypted message
     *
     * @throws JWTException Unsupported algorithm was specified
     */
    public static function sign($msg, $key, $alg = 'HS256')
    {
        if (empty(static::$supported_algs[$alg])) {
            throw new JWTException(
                __('Algorithm not supported', 'uns-project-app'),
                JWTException::ERR_ALGORITHM_NOT_SUPPORTED_IN_SIGNATURE
            );
        }
        list($function, $algorithm) = static::$supported_algs[$alg];
        switch ($function) {
            case 'hash_hmac':
                return hash_hmac($algorithm, $msg, $key, true);
            case 'openssl':
                $signature = '';
                $success = @openssl_sign($msg, $signature, $key, $algorithm);
                if (!$success) {
                    throw new JWTException(
                        __("OpenSSL unable to sign data", 'uns-project-app'),
                        JWTException::ERR_OPENSSL_SIGN
                    );
                } else {
                    return $signature;
                }
        }

        throw new JWTException(
            __("Unsupported sign function", 'uns-project-app'),
            JWTException::ERR_UNSUPPORTED_SIGN_FUNCTION
        );
    }

    /**
     * Verify a signature with the message, key and method. Not all methods
     * are symmetric, so we must have a separate verify and sign method.
     *
     * @param string $msg The original message (header and body)
     * @param string $signature The original signature
     * @param string|resource $key For HS*, a string key works. for RS*, must be a resource of an openssl public key
     * @param string $alg The algorithm
     *
     * @return bool
     *
     * @throws JWTException Invalid Algorithm or OpenSSL failure
     */
    private static function verify($msg, $signature, $key, $alg)
    {
        if (empty(static::$supported_algs[$alg])) {
            throw new JWTException(
                __('Algorithm not supported', 'uns-project-app'),
                JWTException::ERR_ALGORITHM_NOT_SUPPORTED_VERIFY
            );
        }
        list($function, $algorithm) = static::$supported_algs[$alg];
        switch ($function) {
            case 'openssl':
                $success = @openssl_verify($msg, $signature, $key, $algorithm);
                if ($success === 1) {
                    return true;
                } elseif ($success === 0) {
                    return false;
                }
                // returns 1 on success, 0 on failure, -1 on error.
                throw new JWTException(
                    sprintf(
                        __('OpenSSL error: %s', 'uns-project-app'),
                        openssl_error_string()
                    ),
                    JWTException::ERR_OPEN_SSL_VERIFY
                );
            case 'hash_hmac':
            default:
                $hash = hash_hmac($algorithm, $msg, $key, true);
                if (function_exists('hash_equals')) {
                    return hash_equals($signature, $hash);
                }
                $len = min(static::safeStrlen($signature), static::safeStrlen($hash));
                $status = 0;
                for ($i = 0; $i < $len; $i++) {
                    $status |= (ord($signature[$i]) ^ ord($hash[$i]));
                }
                $status |= (static::safeStrlen($signature) ^ static::safeStrlen($hash));

                return ($status === 0);
        }
    }

    /**
     * Decode a JSON string into a PHP object.
     *
     * @param string $input JSON string
     *
     * @return object Object representation of JSON string
     *
     * @throws JWTException Provided string was invalid JSON
     */
    public static function jsonDecode($input)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>=') && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
            /** In PHP >=5.4.0, json_decode() accepts an options parameter, that allows you
             * to specify that large ints (like Steam Transaction IDs) should be treated as
             * strings, rather than the PHP default behaviour of converting them to floats.
             */
            $obj = json_decode($input, false, 512, JSON_BIGINT_AS_STRING);
        } else {
            /** Not all servers will support that, however, so for older versions we must
             * manually detect large ints in the JSON string and quote them (thus converting
             *them to strings) before decoding, hence the preg_replace() call.
             */
            $max_int_length = strlen((string)PHP_INT_MAX) - 1;
            $json_without_bigints = preg_replace('/:\s*(-?\d{' . $max_int_length . ',})/', ': "$1"', $input);
            $obj = json_decode($json_without_bigints);
        }
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            static::handleJsonError($errno);
        } elseif ($obj === null && $input !== 'null') {
            throw new JWTException(
                __('Null result with non-null input', 'uns-project-app'),
                JWTException::ERR_JSON_DECODE_NON_NULL_INPUT
            );
        }

        return $obj;
    }

    /**
     * Encode a PHP object into a JSON string.
     *
     * @param object|array $input A PHP object or array
     *
     * @return string JSON representation of the PHP object or array
     *
     * @throws JWTException
     */
    public static function jsonEncode($input)
    {
        $json = json_encode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            static::handleJsonError($errno);
        } elseif ($json === 'null' && $input !== null) {
            throw new JWTException(
                __('Null result with non-null input', 'uns-project-app'),
                JWTException::ERR_JSON_ENCODE_NON_NULL_INPUT
            );
        }

        return $json;
    }

    /**
     * Decode a string with URL-safe Base64.
     *
     * @param string $input A Base64 encoded string
     *
     * @return string A decoded string
     */
    public static function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Encode a string with URL-safe Base64.
     *
     * @param string $input The string you want encoded
     *
     * @return string The base64 encode of what you passed in
     */
    public static function urlsafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * Helper method to create a JSON error.
     *
     * @param int $errno An error number from json_last_error()
     *
     * @return void
     * @throws JWTException
     */
    private static function handleJsonError($errno)
    {
        $messages = [
            JSON_ERROR_DEPTH => __('Maximum stack depth exceeded', 'uns-project-app'),
            JSON_ERROR_STATE_MISMATCH => __('Invalid or malformed JSON', 'uns-project-app'),
            JSON_ERROR_CTRL_CHAR => __('Unexpected control character found', 'uns-project-app'),
            JSON_ERROR_SYNTAX => __('Syntax error, malformed JSON', 'uns-project-app'),
            JSON_ERROR_UTF8 => __('Malformed UTF-8 characters', 'uns-project-app') //PHP >= 5.3.3
        ];
        throw new JWTException(
            isset($messages[$errno])
                ? $messages[$errno]
                : sprintf(__('Unknown JSON error: %s', 'uns-project-app'), $errno),
            JWTException::ERR_UNKNOWN_ERROR
        );
    }

    /**
     * Get the number of bytes in cryptographic strings.
     *
     * @param string
     *
     * @return int
     */
    private static function safeStrlen($str)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, '8bit');
        }

        return strlen($str);
    }
}
