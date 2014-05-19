<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Password;


/**
 * Implements the password hashing algorithm of punBB.
 *
 * In order to use this class with passwords from a punbb database concatenate the password hashes and salts.
 *
 * ```php
 * // php
 * $hash = $password.'$'.$salt;
 * ```
 *
 * ```sql
 * -- mysql
 * select concat(u.password, '$', u.salt) as password_hash
 * from punbb_users u;
 * ```
 */
class PunbbPassword implements IPassword {

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
        $calc_hash = sha1($salt.sha1($password));

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
        list($stored_hash, $stored_salt) = $this->splitHash($hash);

        if (md5($password) == $stored_hash) {
            $result = true;
        } elseif (sha1($password) == $stored_hash) {
            $result = true;
        } elseif (sha1($stored_salt.sha1($password)) == $stored_hash) {
            $result = true;
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * Split the hash into its calculated hash and salt.
     *
     * @param string $hash The hash to split.
     * @return array An array in the form [$hash, $salt].
     */
    protected function splitHash($hash) {
        if (strpos($hash, '$') === false) {
            return [$hash, false];
        } else {
            $parts = explode('$', $hash, 2);
            return $parts;
        }
    }
}
