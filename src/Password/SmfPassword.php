<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Password;

/**
 * Implements the password hash from Simple Machines Forums (SMF).
 *
 * Note that smf salts passwords with usernames. In order to use a smf password with this class you must concatenate
 * the username to the end of the password hash in the following way:
 *
 * ```php
 * // Password hash in php.
 * $passwordHash = $sha1PasswordHash.'$'.$username;
 * ```
 *
 * ```sql
 * -- Password hash in mysql.
 * select concat(memberName, '$', password) as passwordHash
 * from smf_members
 * ```
 *
 * The smf password hash is not a good choice for a default password hashing algorithm for several reasons.
 *
 * 1. It uses the username as a salt which could change thus invalidating the hash.
 * 2. The username is not a secure field and is thus a bad choice for a password salt.
 * 3. It relies on sha1 which is susceptible to a rainbow table attack.
 */
class SmfPassword implements IPassword {

    /**
     * Hashes a plaintext password.
     *
     * Although smf uses the username as salt, this hashing algorithm generates a random salt.
     *
     * @param string $password The password to hash.
     * @return string Returns the hashed password.
     */
    public function hash($password) {
        $salt = base64_encode(openssl_random_pseudo_bytes(12));
        $hash = sha1(strtolower($salt).$password);

        return $hash.'$'.$salt;
    }

    /**
     * Checks if a given password hash needs to be re-hashed to to a stronger algorithm.
     *
     * @param string $hash The hash to check.
     * @return bool Returns `true`
     */
    public function needsRehash($hash) {
        return false;
    }

    /**
     * Check to make sure a password matches its stored hash.
     *
     * @param string $password The password to verify.
     * @param string $hash The stored password hash.
     * @return bool Returns `true` if the password matches the stored hash.
     */
    public function verify($password, $hash) {
        list($storedHash, $username) = $this->splitHash($hash);
        $result = (sha1(strtolower($username).$password) == $storedHash);
        return $result;
    }

    /**
     * Split a password hash and salt into its separate components.
     *
     * @param string $hash The full password hash.
     * @return array An array in the form of [$hash, $username].
     */
    protected function splitHash($hash) {
        if (strpos($hash, '$') === false) {
            return [false, false];
        } else {
            return explode('$', $hash, 2);
        }
    }
}
