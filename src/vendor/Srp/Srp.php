<?php

namespace Srp;

class Srp
{

    protected $n_base64 = "dadfccb918e5f651d7a1b851efab43f2c17068c69013e37033347e8da75ca8d8370c26c4fbf1a4aaa4afd9b5ab32343749ee4fbf6fa279856fd7c3ade30ecf2b";
    protected $g = "2";
    protected $hash_alg = "sha256";
    protected $k = "3";
    protected $rand_length = 128;


    public function __construct()
    {
        $this->k = $this->hash($this->n_base64 . $this->g);
    }

    /**
     * Client function generates the Private Key
     *
     * @param string $s
     * @param string $username
     * @param string $password
     * @return string
     */
    public function generateX($s, $username, $password)
    {
        $s = $this->base2dec($s);

        return $this->hash($s . $this->hash($username . ":" . $password));
    }

    /**
     * Client Function generates the Password verifier
     *
     * @param string $x
     * @return string
     */
    public function generateV($x)
    {
        $g = $this->g;
        $n = $this->base2dec($this->n_base64);
        $x = $this->base2dec($x);

        return $this->dec2base(bcpowmod($g, $x, $n));

    }

    /**
     * Client function generates the Public ephemeral values
     *
     * @param string $a
     * @return string
     */
    public function generateA($a)
    {
        $n = $this->base2dec($this->n_base64);
        $a = $this->base2dec($a);

        return $this->dec2base(bcpowmod($this->g, $a, $n));
    }

    /**
     * Client function generates the Session Key
     *
     * @param string $A
     * @param string $B
     * @param string $a
     * @param string $x
     * @return string
     */
    public function generateS_Client($A, $B, $a, $x)
    {
        $u = $this->base2dec($this->generateU($A, $B));
        $B = $this->base2dec($B);
        $a = $this->base2dec($a);
        $k = $this->base2dec($this->k);
        $g = $this->g;
        $n = $this->base2dec($this->n_base64);
        $x = $this->base2dec($x);

        return $this->dec2base(bcpowmod(bcsub($B, bcmul($k, bcpowmod($g, $x, $n))), bcadd($a, bcmul($u, $x)), $n));
    }

    /**
     * Server function thatgenerates the Public ephemeral values
     * B = kv + g^b
     * @param string $b
     * @param string $v
     * @return string
     */
    public function generateB($b, $v)
    {
        $n = $this->base2dec($this->n_base64);
        $v = $this->base2dec($v);
        $b = $this->base2dec($b);
        $k = $this->base2dec($this->k);

        return $this->dec2base(bcadd(bcmul($k, $v), bcpowmod($this->g, $b, $n)));
    }

    /**
     * Server function that generates the Session key
     *
     * @param string $A
     * @param string $B
     * @param string $b
     * @param string $v
     * @return string
     */
    public function generateS_Server($A, $B, $b, $v)
    {
        $u = $this->base2dec($this->generateU($A, $B));
        $n = $this->base2dec($this->n_base64);
        $A = $this->base2dec($A);
        $v = $this->base2dec($v);
        $b = $this->base2dec($b);

        return $this->dec2base(bcpowmod(bcmul($A, bcpowmod($v, $u, $n)), $b, $n));
    }


    /**
     * shared function that generates the random seed and Secret ephemeral values
     *
     * @param int $length
     * @return string
     */
    public function getRandomSeed($length = 0)
    {
        $length = $length ?: $this->rand_length;

        srand((double)microtime() * 1000000);
        $result = "";

        while (strlen($result) < $length) {
            $result = $result . $this->dec2base(rand());
        }

        return substr($result, 0, $length);
    }

    /**
     * shared function that generates the Random scrambling parameter
     *
     * @param string $A
     * @param string $B
     * @return string
     */
    protected function generateU($A, $B)
    {
        return $this->hash($A . $B);
    }

    /**
     * @param string $A
     * @param string $B
     * @param string $S
     * @return string
     */
    public function generateM1($A, $B, $S)
    {
        return $this->hash($A . $B . $S);
    }

    /**
     * @param string $A
     * @param string $M1
     * @param string $S
     * @return string
     */
    public function generateM2($A, $M1, $S)
    {
        return $this->hash($A . $M1 . $S);
    }

    /**
     * @param string $S
     * @return string
     */
    public function generateK($S)
    {
        return $this->hash($S);
    }


    /**
     * @param string $value
     * @return string
     */
    protected function hash($value)
    {
        return hash($this->hash_alg, hash($this->hash_alg, $value));
    }

    /**
     * @param int $dec
     * @param int $base
     * @param false $digits
     * @return string
     */
    protected function dec2base($dec, $base = 16, $digits = FALSE)
    {
        if ($base < 2 or $base > 256) {
            die("Invalid Base: " . $base);
        }

        $value = "";
        if (!$digits) {
            $digits = $this->digits($base);
        }

        while ($dec > $base - 1) {
            $rest = bcmod($dec, $base);
            $dec = bcdiv($dec, $base);
            $value = $digits[$rest] . $value;
        }

        $value = $digits[intval($dec)] . $value;

        return (string)$value;
    }

    /**
     * Convert another base value to its decimal value
     * @param string $value
     * @param int $base
     * @param false $digits
     * @return string
     */
    protected function base2dec($value, $base = 16, $digits = FALSE)
    {
        if ($base < 2 or $base > 256) {
            die("Invalid Base: " . $base);
        }

        bcscale(0);
        if ($base < 37) {
            $value = strtolower($value);
        }

        if (!$digits) {
            $digits = $this->digits($base);
        }

        $size = strlen($value);
        $dec = "0";
        for ($loop = 0; $loop < $size; $loop++) {
            $element = strpos($digits, $value[$loop]);
            $power = bcpow($base, $size - $loop - 1);
            $dec = bcadd($dec, bcmul($element, $power));
        }

        return (string)$dec;
    }

    /**
     * @param int $base
     * @return string
     */
    protected function digits($base)
    {
        if ($base > 64) {
            $digits = "";
            for ($loop = 0; $loop < 256; $loop++) {
                $digits .= chr($loop);
            }
        } else {
            $digits = "0123456789abcdefghijklmnopqrstuvwxyz";
            $digits .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ-_";
        }

        $digits = substr($digits, 0, $base);

        return (string)$digits;
    }

}
