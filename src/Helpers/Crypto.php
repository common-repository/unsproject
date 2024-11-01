<?php


namespace UNSProjectApp\Helpers;


class Crypto
{
    const METHOD = 'aes256';
    /**
     * @var false|string
     */
    private $iv;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * Crypto constructor.
     * @param string $secretKey
     * @param string $iv
     */
    public function __construct($secretKey, $iv)
    {
        $this->iv = substr($iv, 0, 16);
        $this->secretKey = $secretKey;
    }

    /**
     * @param string $message
     * @return false|string
     */
    public function encrypt($message)
    {
        $result = @openssl_encrypt($message, self::METHOD, $this->secretKey, 0, $this->iv);

        return base64_encode($result);
    }

    /**
     * @param string $encryptedMessage
     * @return false|string
     */
    public function decrypt($encryptedMessage)
    {
        $encryptedMessage = base64_decode($encryptedMessage);
        return openssl_decrypt($encryptedMessage, self::METHOD, $this->secretKey, 0, $this->iv);
    }

}
