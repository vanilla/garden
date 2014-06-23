<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Password;


/**
 * Implements the password hashing algorithm of Xenforo.
 *
 */
class XenforoPassword implements IPassword {

    /**
     * @var string The name of the hashing function to use.
     */
    protected $hashFunction;

    /**
     * Initialize an instance of this class.
     *
     * @param string $hashFunction The name of the hash function to use.
     * This is an function name that can be passed to {@link hash()}.
     * @see hash()
     */
    public function __construct($hashFunction = '') {
        if (!$hashFunction) {
            $hashFunction = 'sha256';
        }
        $this->hashFunction = $hashFunction;
    }

    /**
     * {@inheritdoc}
     */
    public function hash($password) {
        $salt = base64_encode(openssl_random_pseudo_bytes(12));
        $result = [
            'hashFunc' => $this->hashFunction,
            'hash' => $this->hashRaw($password, $salt, $this->hashFunction),
            'salt' => $salt
        ];

        return serialize($result);
    }

    /**
     * Hashes a password with a given salt.
     *
     * @param string $password The password to hash.
     * @param string $salt The password salt.
     * @param string $function The hashing function to use.
     * @return string Returns the password hash.
     */
    protected function hashRaw($password, $salt, $function = null) {
        if ($function === null) {
            $function = $this->hashFunction;
        }

        $calc_hash = hash($function, hash($function, $password).$salt);

        return $calc_hash;
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash($hash) {
        list($stored_hash, $stored_salt) = $this->splitHash($hash);

        // Unsalted hashes should be rehashed.
        if ($stored_hash === false || $stored_salt === false) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function verify($password, $hash) {
        list($stored_hash, $function, $stored_salt) = $this->splitHash($hash);

        $calc_hash = $this->hashRaw($password, $stored_salt, $function);
        $result = $calc_hash === $stored_hash;

        return $result;
    }

    /**
     * Split the hash into its calculated hash and salt.
     *
     * @param string $hash The hash to split.
     * @return array An array in the form [$hash, $hashFunc, $salt].
     */
    protected function splitHash($hash) {
        $parts = @unserialize($hash);

        if (!is_array($parts)) {
            $result = ['', '', ''];
        } else {
            $parts = array_merge(['hash' => '', 'hashFunc' => '', 'salt' => ''], $parts);

            if (!$parts['hashFunc']) {
                switch (strlen($parts['hash'])) {
                    case 32:
                        $parts['hashFunc'] = 'md5';
                        break;
                    case 40:
                        $parts['hashFunc'] = 'sha1';
                        break;
                }
            }

            $result = [$parts['hash'], $parts['hashFunc'], $parts['salt']];
        }
        return $result;
    }
}
