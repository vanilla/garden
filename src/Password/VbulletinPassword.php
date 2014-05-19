<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Garden\Password;

/**
 * Implements the vBulletin password hash algorithms.
 *
 * VBulletin itself stores password salts in a separate database column. In order to use a vBulletin password with this
 * class you must concatenate the salt to the end of the password hash. The vBulletin password hashing algorithm is a
 * poor choice for your passwords because it uses md5 which is very insecure.
 *
 * We recommend using this class to validate existing vBulletin passwords, but then rehashing them with a different
 * algorithm.
 */
class VbulletinPassword implements IPassword {

    /**
     * Hashes a plaintext password.
     *
     * @param string $password The password to hash.
     * @return string Returns the hashed password.
     */
    public function hash($password) {
        $salt = base64_encode(openssl_random_pseudo_bytes(12));
        return $this->hashRaw($password, $salt).$salt;
    }

    protected function hashRaw($password, $salt) {
        $hash = md5(md5($password).$salt);
        return $hash;
    }

    /**
     * Checks if a given password hash needs to be re-hashed to to a stronger algorithm.
     *
     * @param string $hash The hash to check.
     * @return bool Returns `true`
     */
    public function needsRehash($hash) {
        return strlen($hash) < 37;
    }

    /**
     * Check to make sure a password matches its stored hash.
     *
     * @param string $password The password to verify.
     * @param string $hash The stored password hash.
     * @return bool Returns `true` if the password matches the stored hash.
     */
    public function verify($password, $hash) {
        list($stored_hash, $salt) = $this->splitSalt($hash);
        $calculated_hash = $this->hashRaw($password, $salt);
        $result = $calculated_hash === $stored_hash;
        return $result;
    }

    /**
     * Split the salt and hash from a single hash.
     *
     * @param string $hash The hash to split.
     * @return array An array in the form `[$hash, $salt]`.
     */
    public function splitSalt($hash) {
        // The hash is in the form: <32 char hash><salt>.
        $salt_length = strlen($hash) - 32;
        $salt = trim(substr($hash, -$salt_length, $salt_length));
        $stored_hash = substr($hash, 0, strlen($hash) - $salt_length);

        return [$stored_hash, $salt];
    }
}