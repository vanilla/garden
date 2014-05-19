<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Password;


/**
 * Implements the password hashing algorithm of Invision Power Board (ipb).
 */
class IpbPassword implements IPassword {

    /**
     * {@inheritdoc}
     */
    public function hash($password) {
        $salt = base64_encode(openssl_random_pseudo_bytes(12));
        return $this->hashRaw($password, $salt).'$'.$salt;
    }

    /**
     * Hashes a password with a given salt.
     *
     * @param string $password The password to hash.
     * @param string $salt The password salt.
     * @return string Returns the password hash.
     */
    protected function hashRaw($password, $salt) {
        $calc_hash = md5(md5($salt).md5($password));

        return $calc_hash;
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash($hash) {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function verify($password, $hash) {
        list($stored_hash, $salt) = $this->splitHash($hash);
        $calc_hash = $this->hashRaw($password, $salt);
        return $calc_hash === $stored_hash;
    }

    /**
     * Split the hash into its calculated hash and salt.
     *
     * @param string $hash The hash to split.
     * @return array An array in the form [$hash, $salt].
     */
    protected function splitHash($hash) {
        if (strpos($hash, '$') === false) {
            return [false, false];
        } else {
            $parts = explode('$', $hash, 2);
            return $parts;
        }
    }
}
