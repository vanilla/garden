<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2005 phpBB Group (Original source code)
 * @copyright 2009-2014 Vanilla Forums Inc. (Source code changes)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 * This class adapts functions from phpBB 3 (/includes/functions.php). Any source code that was taken from the
 * phpBB project is copyright 2005 phpBB Group. Any source code changes are copyright 2009-2014 Vanilla Forums Inc.
 *
 */

namespace Garden\Password;

/**
 * Implements phpBB's password hashing algorithm.
 */
class PhpbbPassword implements IPassword {
    const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * Check for correct password
     *
     * @param string $password The password in plain text
     * @param string $hash The stored password hash
     *
     * @return bool Returns true if the password is correct, false if not.
     */
    public function verify($password, $hash) {
        $itoa64 = PhpbbPassword::ITOA64;
        if (strlen($hash) == 34) {

            return ($this->cryptPrivate($password, $hash, $itoa64) === $hash) ? true : false;
        }

        return (md5($password) === $hash) ? true : false;
    }

    /**
     * The crypt function/replacement.
     */
    private function cryptPrivate($password, $setting, $itoa64 = PhpbbPassword::ITOA64) {
        $output = '*';

        // Check for correct hash
        if (substr($setting, 0, 3) != '$H$') {
            return $output;
        }

        $count_log2 = strpos($itoa64, $setting[3]);

        if ($count_log2 < 7 || $count_log2 > 30) {
            return $output;
        }

        $count = 1 << $count_log2;
        $salt = substr($setting, 4, 8);

        if (strlen($salt) != 8) {
            return $output;
        }

        /**
         * We're kind of forced to use MD5 here since it's the only
         * cryptographic primitive available in all versions of PHP
         * currently in use.  To implement our own low-level crypto
         * in PHP would result in much worse performance and
         * consequently in lower iteration counts and hashes that are
         * quicker to crack (by non-PHP code).
         */
        if (PHP_VERSION >= 5) {
            $hash = md5($salt.$password, true);
            do {
                $hash = md5($hash.$password, true);
            } while (--$count);
        } else {
            $hash = pack('H*', md5($salt.$password));
            do {
                $hash = pack('H*', md5($hash.$password));
            } while (--$count);
        }

        $output = substr($setting, 0, 12);
        $output .= $this->encode64($hash, 16, $itoa64);

        return $output;
    }

    /**
     * Encode hash.
     *
     * @param string $input The input to encode.
     * @param int $count The number of characters to encode.
     * @param string $itoa64 The base 64 character space.
     * @return string The encoded string.
     */
    protected function encode64($input, $count, $itoa64 = PhpbbPassword::ITOA64) {
        $output = '';
        $i = 0;

        do {
            $value = ord($input[$i++]);
            $output .= $itoa64[$value & 0x3f];

            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }

            $output .= $itoa64[($value >> 6) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }

            $output .= $itoa64[($value >> 12) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            $output .= $itoa64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }

    /**
     * Hashes a plaintext password.
     *
     * @param string $password The password to hash.
     * @return string Returns the hashed password.
     */
    public function hash($password) {
        $random = openssl_random_pseudo_bytes(6);

        $hash = $this->cryptPrivate($password, $this->gensaltPrivate($random));
        if (strlen($hash) == 34) {
            return $hash;
        }

        return null;
    }

    /**
     * Generate a password salt based on the given input string.
     *
     * @param string $input The input string to generate the salt from.
     * @return string Returns the password salt prefixed with `$P$`.
     */
    private function gensaltPrivate($input) {
        $itoa64 = PhpbbPassword::ITOA64;

        $output = '$H$';
        $output .= $itoa64[min(8 + ((PHP_VERSION >= '5') ? 5 : 3), 30)];
        $output .= $this->encode64($input, 6);

        return $output;
    }

    /**
     * Checks if a given password hash needs to be re-hashed to to a stronger algorithm.
     *
     * @param string $hash The hash to check.
     * @return bool Returns `true`
     */
    public function needsRehash($hash) {
        return $hash === '*';
    }
}
