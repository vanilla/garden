<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Password;

/**
 * Implements tha password hashing algorithm from the Django framework.
 */
class DjangoPassword implements IPassword {

    /**
     * Hashes a plaintext password.
     *
     * @param string $password The password to hash.
     * @return string Returns the hashed password.
     */
    public function hash($password) {
        $salt = base64_encode(openssl_random_pseudo_bytes(12));
        $hash = crypt($password, $salt);
        $result = 'crypt$'.$salt.'$'.$hash;
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash($hash) {
        if (strpos($hash, '$') === false) {
            return true;
        } else {
            list($method,,) = explode('$', $hash, 3);
            switch (strtolower($method)) {
                case 'crypt':
                case 'sha256':
                    return false;
                default:
                    return true;
            }
        }
    }

    /**
     * Check to make sure a password matches its stored hash.
     *
     * @param string $password The password to verify.
     * @param string $hash The stored password hash.
     * @return bool Returns `true` if the password matches the stored hash.
     */
    public function verify($password, $hash) {
        if (strpos($hash, '$') === false) {
            return md5($password) == $hash;
        } else {
            list($method, $salt, $rawHash) = explode('$', $hash);
            switch (strtolower($method)) {
                case 'crypt':
                    return crypt($password, $salt) == $rawHash;
                case 'md5':
                    return md5($salt.$password) == $rawHash;
                case 'sha256':
                    return hash('sha256', $salt.$password) == $rawHash;
                case 'sha1':
                default:
                    return sha1($salt.$password) == $rawHash;
            }
        }
    }
}
