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
     * @var string The hash method to use when hashing passwords.
     */
    public $hashMethod;

    /**
     * Initiailize an instance of the {@link DjangoPassword} class.
     *
     * @param string $hashMethod The hasm method used to hash the passwords.
     */
    public function __construct($hashMethod = 'crypt') {
        $this->hashMethod = $hashMethod;
    }

    /**
     * Hashes a plaintext password.
     *
     * @param string $password The password to hash.
     * @return string Returns the hashed password.
     * @throws \Exception Throws an exception when the hash method is invalid.
     */
    public function hash($password) {
        if ($this->hashMethod === 'crypt') {
            $salt = base64_encode(openssl_random_pseudo_bytes(12));
            $hash = crypt($password, $salt);
        } elseif (in_array($this->hashMethod, hash_algos())) {
            $salt = base64_encode(openssl_random_pseudo_bytes(12));
            $hash = hash($this->hashMethod, $salt.$password);
        } else {
            throw new \Exception("The {$this->hashMethod} hash method is invalid.", 500);
        }

        $result = $this->hashMethod.'$'.$salt.'$'.$hash;
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

            if ($method === 'crypt') {
                return crypt($password, $salt) === $rawHash;
            } elseif (in_array($method, hash_algos())) {
                return hash($method, $salt.$password) === $rawHash;
            } else {
                return false;
            }
        }
    }
}
