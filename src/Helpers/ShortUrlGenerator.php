<?php


namespace UNSProjectApp\Helpers;


use UNSProjectApp\DatabaseService;
use UNSProjectApp\UnsWordPressAPI;

class ShortUrlGenerator
{
    /**
     * @var Crypto
     */
    private $crypto;

    /**
     * ShortUrlGenerator constructor.
     * @param string $privateKey
     * @param string $uniqueId
     */
    public function __construct($privateKey, $uniqueId)
    {
        $this->crypto = new Crypto($privateKey, $uniqueId);
    }

    /**
     * @param string $shortUrlKey
     * @return null|false|string
     */
    public function getLongURL($shortUrlKey)
    {
        $decrypt = $this->crypto->decrypt($shortUrlKey);
        $databaseService = new DatabaseService();
        return $databaseService->getUrlById($decrypt);
    }

    /**
     * @param $string
     * @return false|string Encrypted String for new URL
     */
    private function saveUrl($string)
    {
        $databaseService = new DatabaseService();
        $insertID = $databaseService->saveUrl($string);
        return $this->crypto->encrypt($insertID);
    }

    /**
     * @param string $longUrl
     * @return string
     */
    public function generateShortUrl($longUrl)
    {
        $url = $this->saveUrl($longUrl);
        return UnsWordPressAPI::getShortUrl($url);
    }
}