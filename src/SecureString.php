<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden;


/**
 * A class that provides functionality for encrypting and signing strings and then decoding those secure strings.
 */
class SecureString {
    const SEP = '.';

    protected static $supported = ['aes128', 'hsha1', 'hsha256'];

    protected $timestampExpiry;

    /**
     * Initialize an instance of the {@link SecureString} class.
     */
    public function __construct() {
        $this->timestampExpiry = strtotime('1 day', 0);
    }

    /**
     * Get or set the timestamp expiry in seconds.
     *
     * @param int $value Set a new expiry value in seconds.
     * @return int Returns the current timestamp expiry.
     */
    public function timestampExpiry($value = 0) {
        if ($value) {
            $this->timestampExpiry = $value;
        }
        return $this->timestampExpiry;
    }

    /**
     * Encode a string using a secure specification.
     *
     * @param mixed $data The data to encode. This must be data that supports json serialization.
     * @param array $spec An array specifying how the string should be encoded.
     * The array should be in the form ['spec1' => 'password', 'spec2' => 'password'].
     * @param bool $throw Whether or not to throw an exception on error.
     * @return string|null Returns the encoded string or null if there is an error.
     */
    public function encode($data, array $spec, $throw = false) {
        $str = json_encode($data, JSON_UNESCAPED_SLASHES);

        $first = true;
        foreach ($spec as $name => $password) {
            $supported = $this->supportedInfo($name, $throw);
            if ($supported === null) {
                return null;
            }

            list($encode, $decode, $method, $encodeFirst) = $supported;

            if ($encodeFirst && $first) {
                $str = static::base64urlEncode($str);
            }

            switch ($encode) {
                case 'encrypt':
                    $str = $this->encrypt($str, $method, $password, '', $throw);
                    break;
                case 'hmac':
                    $str = $this->hmac($str, $method, $password, 0, $throw);
                    break;
                default:
                    return $this->exception($throw, "Invalid method $encode.", 500);
            }
            // Return on error.
            if (!$str) {
                return null;
            }

            $this->pushString($str, $name);

            $first = false;
        }

        return $str;
    }

    /**
     * Decode a string that was encoded using {@link SecureString::encode()}.
     *
     * @param string $str The encoded string.
     * @param array $spec An array specifying how the string should be encoded.
     * @param bool $throw Whether or not to throw an exception on error.
     * @return string|null Returns the decoded string or null of there was a problem.
     */
    public function decode($str, array $spec, $throw = false) {
        $decodeFirst = false;
        $last = true;

        while ($token = $this->popString($str)) {
            if ($str === '') {
                if ($last) {
                    // The string has not been secured in any way.
                    return $this->exception($throw, "The string is not secure.", 403);
                } elseif ($decodeFirst) {
                    $str = static::base64urlDecode($token);
                } else {
                    $str = $token;
                }
                break;
            }

            $supported = $this->supportedInfo($token, $throw);
            if ($supported === null) {
                return null;
            }

            if (!isset($spec[$token])) {
                return $this->exception($throw, "You did not provide a password for $token.", 403);
            }
            $password = $spec[$token];

            list($encode, $decode, $method, $decodeFirst) = $supported;

            switch ($decode) {
                case 'decrypt':
                    $str = $this->decrypt($str, $method, $password, $throw);
                    break;
                case 'verifyHmac':
                    $str = $this->verifyHmac($str, $method, $password, $throw);
                    break;
                default:
                    return $this->exception($throw, "Invalid method $decode.", 500);
            }

            if ($str === null) {
                return null;
            }
            $last = false;
        }

        $data = json_decode($str, true);
        if ($data === null) {
            return $this->exception($throw, 'The final string is not valid json.', 400);
        }

        return $data;
    }

    /**
     * Generate a random string suitable for use as an encryption or signature key.
     *
     * @param int $len The number of characters in the key.
     * @return string Returns a base64url encoded string representing the random key.
     */
    public static function generateRandomKey($len = 32) {
        $bytes = ceil($len * 3 / 4);

        return substr(self::base64urlEncode(openssl_random_pseudo_bytes($bytes)), 0, $len);
    }

    /**
     * Base64 Encode a string, but make it suitable to be passed in a url.
     *
     * @param string $str The string to encode.
     * @return string The encoded string.
     */
    protected static function base64urlEncode($str) {
        return trim(strtr(base64_encode($str), '+/', '-_'), '=');
    }

    /**
     * Decode a string that was encoded using base64UrlEncode().
     *
     * @param string $str The encoded string.
     * @return string The decoded string.
     */
    protected static function base64urlDecode($str) {
        return base64_decode(strtr($str, '-_', '+/'));
    }

    /**
     * Encrypt a string with {@link openssl_encrypt()}.
     *
     * @param string $str The string to encrypt.
     * @param string $method The encryption cipher.
     * @param string $password The encryption password.
     * @param string $iv The input vector.
     * @param bool $throw Whether or not to throw an exception on error.
     * @return string Returns the encrypted string.
     */
    protected function encrypt($str, $method, $password, $iv = '', $throw = false) {
        if ($iv === '') {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        }
        // Encrypt the string.
        $encrypted = openssl_encrypt($str, $method, $password, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            return $this->exception($throw, "Error encrypting the string.", 400);
        }

        $str = static::base64urlEncode($encrypted);
        $this->pushString($str, static::base64urlEncode($iv));

        return $str;
    }

    /**
     * Decrypt a string with {@link openssl_decrypt()}.
     *
     * @param string $str The base64 url encoded encrypted string.
     * @param string $method The encryption cipher.
     * @param string $password The password to decyrpt the string.
     * @param bool $throw Whether or not to decode the string on exception.
     * @return string|null Returns the decrypted string or null on error.
     */
    protected function decrypt($str, $method, $password, $throw = false) {
        $iv = static::base64urlDecode($this->popString($str));
        $encrypted = static::base64urlDecode($this->popString($str));

        $decrypted = openssl_decrypt($encrypted, $method, $password, true, $iv);

        if ($decrypted === false) {
            return $this->exception($throw, "Error decrypting the string.", 403);
        }

        return $decrypted;
    }

    /**
     * Get information about a supported spec name.
     *
     * @param string $name The name of the spec.
     * @param bool $throw Whether or not throw an exception on error.
     * @return array|null Returns an array in the form [encode, decode, method, encodeFirst].
     * Returns null on error.
     */
    protected function supportedInfo($name, $throw = false) {
        switch ($name) {
            case 'aes128':
            case 'aes256':
                $cipher = 'aes-'.substr($name, 3).'-cbc';
                return ['encrypt', 'decrypt', $cipher, false];
            case 'hsha1':
            case 'hsha256':
                $hash = substr($name, 1);
                return ['hmac', 'verifyHmac', $hash, true];
        }
        return $this->exception($throw, "Spec $name not supported.", 400);
    }

    /**
     * Sign a string with hmac and a hash method.
     *
     * @param string $str The string to sign.
     * @param string $method The hash method used to sign the string.
     * @param string $password The password used to hmac hash the string with.
     * @param int $timestamp The timestamp used to sign the string with or 0 to use the current time.
     * @param bool $throw Whether or not the throw an exception on error.
     * @return string Returns the string with signing information or null on error.
     */
    protected function hmac($str, $method, $password, $timestamp = 0, $throw = false) {
        if ($timestamp === 0) {
            $timestamp = time();
        }
        // Add the timestamp to the string.
        static::pushString($str, $timestamp);

        // Sign the string.
        $signature = hash_hmac($method, $str, $password, true);
        if ($signature === false) {
            return $this->exception($throw, "Invalid hash method $method.", 400);
        }

        // Add the signature to the string.
        static::pushString($str, static::base64urlEncode($signature));

        return $str;
    }

    /**
     * Verify the signature on a secure string.
     *
     * @param string $str The string to verify.
     * @param string $method The hashing algorithm that the string was signed with.
     * @param string $password The password used to sign the string.
     * @param bool $throw Whether or not to throw an exeptio on error.
     * @return bool Returns the string without the signing information or null on error.
     */
    protected function verifyHmac($str, $method, $password, $throw = false) {
        // Grab the signature from the string.
        $signature = $this->popString($str);
        if (!$signature) {
            return $this->exception($throw, "The signature is missing.", 403);
        }
        $signature = static::base64urlDecode($signature);

        // Recalculate the signature to compare.
        $calcSignature = hash_hmac($method, $str, $password, true);

        if (strlen($signature) !== strlen($calcSignature)) {
            return $this->exception($throw, "The signature is invalid.", 403);
        }

        // Do a double hmac comparison to prevent timing attacks.
        // https://www.isecpartners.com/blog/2011/february/double-hmac-verification.aspx
        $dblSignature = hash_hmac($method, $signature, $password, true);
        $dblCalcSignature = hash_hmac($method, $calcSignature, $password, true);

        if ($dblSignature !== $dblCalcSignature) {
            return $this->exception($throw, "The signature is invalid.", 403);
        }

        // Grab the timestamp and verify it.
        $timestamp = $this->popString($str);
        if (!$this->verifyTimestamp($timestamp, $throw)) {
            return false;
        }

        return $str;
    }

    /**
     * Verify that a timestamp hasn't expired.
     *
     * @param string $timestamp The unix timestamp to verify.
     * @param bool $throw Whether or not to throw an exception on error.
     * @return bool Returns true if the timestamp is valid or false otherwise.
     */
    protected function verifyTimestamp($timestamp, $throw = false) {
        if (!is_numeric($timestamp)) {
            return (bool)$this->exception($throw, "Invalid timestamp.", 403);
        }
        $intTimestamp = (int)$timestamp;
        $now = time();
        if ($intTimestamp + $this->timestampExpiry <= $now) {
            return (bool)$this->exception($throw, "The timestamp has expired.", 403);
        }

        return true;
    }

    /**
     * Pop a string off of the end of an encoded secure string.
     *
     * @param string &$str The main string to pop.
     * @return string|null Returns the popped string or null if {@link $str} is empty.
     */
    protected function popString(&$str) {
        if ($str === '') {
            return null;
        }

        $pos = strrpos($str, '.');
        if ($pos !== false) {
            $result = substr($str, $pos + 1);
            $str = substr($str, 0, $pos);
        } else {
            $result = $str;
            $str = '';
        }
        return $result;
    }

    /**
     * Pushes a string on to the end of an encoded secure string.
     *
     * @param string &$str The main string to push to.
     * @param string|array $item The string or array of strings to push on to the end of {@link $str}.
     */
    protected function pushString(&$str, $item) {
        if ($str) {
            $str .= static::SEP;
        }
        $str .= implode(static::SEP, (array)$item);
    }

    /**
     * Throw an exception or return false.
     *
     * @param bool $throw Whether or not to throw an exception.
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @return bool Returns false if {@link $throw} is false.
     * @throws \Exception Throws an exception with {@link $message} and {@link $code}.
     */
    protected function exception($throw, $message, $code) {
        if ($throw) {
            throw new \Exception($message, $code);
        }
        return null;
    }

    /**
     * Twiddle a value in an encoded secure string to another value.
     *
     * This method is mainly for testing so that an invalid string can be created.
     *
     * @param string $string A valid cookie to twiddle.
     * @param int $index The index of the new value.
     * @param string $value The new value. This will be base64url encoded.
     * @param bool $encode Whether or not to base64 url encode the value.
     * @return string Returns the new encoded cookie.
     */
    public function twiddle($string, $index, $value, $encode = false) {
        $parts = explode(static::SEP, $string);

        if ($encode) {
            $value = static::base64urlEncode($value);
        }
        $parts[$index] = $value;

        return implode(static::SEP, $parts);
    }
}
