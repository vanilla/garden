<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Password;

/**
 * Implements the portable PHP password hashing framework.
 *
 * The code in this class is copied from the phppass library version 0.3 located at http://www.openwall.com/phpass/.
 * Any code copied from the phppass library is copyright the original owner.
 */
class PhpPassPassword implements IPassword {
    protected $itoa64;
    protected $iteration_count_log2;
    protected $portable;
    protected $random_state;

    /**
     * Initializes an instance of the of the {@link PhpPass} class.
     *
     * @param bool $portable Whether or not the passwords should be portable to older versions of php.
     * @param int $iteration_count_log2 The number of times to iterate when generating the passwords.
     */
    public function __construct($portable = true, $iteration_count_log2 = 8) {
        $this->itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        if ($iteration_count_log2 < 4 || $iteration_count_log2 > 31) {
            $iteration_count_log2 = 8;
        }
        $this->iteration_count_log2 = $iteration_count_log2;

        $this->portable = $portable;

        $this->random_state = microtime();
        if (function_exists('getmypid')) {
            $this->random_state .= getmypid();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash($hash) {
        $id = substr($hash, 0, 3);
        $portable = ($id != '$P$' && $id != '$H$');

        return $portable == $this->portable;
    }

    /**
     * {@inheritdoc}
     */
    public function hash($password) {
        $random = '';

        if (CRYPT_BLOWFISH == 1 && !$this->portable) {
            $random = $this->getRandomBytes(16);
            $hash =
                crypt($password, $this->gensaltBlowfish($random));
            if (strlen($hash) == 60) {
                return $hash;
            }
        }

        if (CRYPT_EXT_DES == 1 && !$this->portable) {
            if (strlen($random) < 3) {
                $random = $this->getRandomBytes(3);
            }
            $hash =
                crypt($password, $this->gensaltExtended($random));
            if (strlen($hash) == 20) {
                return $hash;
            }
        }

        if (strlen($random) < 6) {
            $random = $this->getRandomBytes(6);
        }
        $hash =
            $this->cryptPrivate($password,
                $this->gensaltPrivate($random));
        if (strlen($hash) == 34) {
            return $hash;
        }

        # Returning '*' on error is safe here, but would _not_ be safe
        # in a crypt(3)-like function used _both_ for generating new
        # hashes and for validating passwords against existing hashes.
        return '*';
    }

    /**
     * Get a string of random bytes.
     *
     * @param int $count The number of bytes to get.
     * @return string Returns a string of the generated random bytes.
     */
    protected function getRandomBytes($count) {
        $output = '';
        if (is_readable('/dev/urandom') &&
            ($fh = @fopen('/dev/urandom', 'rb'))
        ) {
            $output = fread($fh, $count);
            fclose($fh);
        }

        if (strlen($output) < $count) {
            $output = '';
            for ($i = 0; $i < $count; $i += 16) {
                $this->random_state =
                    md5(microtime().$this->random_state);
                $output .=
                    pack('H*', md5($this->random_state));
            }
            $output = substr($output, 0, $count);
        }

        return $output;
    }

    /**
     *
     * @param string $input
     * @return string The generated salt.
     */
    protected function gensaltBlowfish($input) {
        # This one needs to use a different order of characters and a
        # different encoding scheme from the one in encode64() above.
        # We care because the last character in our encoded string will
        # only represent 2 bits.  While two known implementations of
        # bcrypt will happily accept and correct a salt string which
        # has the 4 unused bits set to non-zero, we do not want to take
        # chances and we also do not want to waste an additional byte
        # of entropy.
        $itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        $output = '$2a$';
        $output .= chr(ord('0') + $this->iteration_count_log2 / 10);
        $output .= chr(ord('0') + $this->iteration_count_log2 % 10);
        $output .= '$';

        $i = 0;
        do {
            $c1 = ord($input[$i++]);
            $output .= $itoa64[$c1 >> 2];
            $c1 = ($c1 & 0x03) << 4;
            if ($i >= 16) {
                $output .= $itoa64[$c1];
                break;
            }

            $c2 = ord($input[$i++]);
            $c1 |= $c2 >> 4;
            $output .= $itoa64[$c1];
            $c1 = ($c2 & 0x0f) << 2;

            $c2 = ord($input[$i++]);
            $c1 |= $c2 >> 6;
            $output .= $itoa64[$c1];
            $output .= $itoa64[$c2 & 0x3f];
        } while (1);

        return $output;
    }

    /**
     * Generate a password salt based on the input.
     *
     * @param string $input The string to generate the salt from.
     * @return string The generated salt.
     */
    private function gensaltExtended($input) {
        $count_log2 = min($this->iteration_count_log2 + 8, 24);
        # This should be odd to not reveal weak DES keys, and the
        # maximum valid value is (2**24 - 1) which is odd anyway.
        $count = (1 << $count_log2) - 1;

        $output = '_';
        $output .= $this->itoa64[$count & 0x3f];
        $output .= $this->itoa64[($count >> 6) & 0x3f];
        $output .= $this->itoa64[($count >> 12) & 0x3f];
        $output .= $this->itoa64[($count >> 18) & 0x3f];

        $output .= $this->encode64($input, 3);

        return $output;
    }

    /**
     * A custom base64 encoding function.
     *
     * @param string $input The string to encode.
     * @param int $count The number of characters to encode.
     * @return string Returns the encoded string.
     */
    protected function encode64($input, $count) {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= $this->itoa64[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= $this->itoa64[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= $this->itoa64[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $output .= $this->itoa64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }

    /**
     * A portable version of a crypt-like algorithm.
     *
     * @param string $password The plaintext password to encrypt.
     * @param string $setting The hash prefix that defines what kind of algorithm to use.
     * @return string Returns the encrypted string.
     */
    private function cryptPrivate($password, $setting) {
        $output = '*0';
        if (substr($setting, 0, 2) == $output) {
            $output = '*1';
        }

        $id = substr($setting, 0, 3);
        # We use "$P$", phpBB3 uses "$H$" for the same thing
        if ($id != '$P$' && $id != '$H$') {
            return $output;
        }

        $count_log2 = strpos($this->itoa64, $setting[3]);
        if ($count_log2 < 7 || $count_log2 > 30) {
            return $output;
        }

        $count = 1 << $count_log2;

        $salt = substr($setting, 4, 8);
        if (strlen($salt) != 8) {
            return $output;
        }

        # We're kind of forced to use MD5 here since it's the only
        # cryptographic primitive available in all versions of PHP
        # currently in use.  To implement our own low-level crypto
        # in PHP would result in much worse performance and
        # consequently in lower iteration counts and hashes that are
        # quicker to crack (by non-PHP code).
        if (PHP_VERSION >= '5') {
            $hash = md5($salt.$password, true);
            do {
                $hash = md5($hash.$password, false);
            } while (--$count);
        } else {
            $hash = pack('H*', md5($salt.$password));
            do {
                $hash = pack('H*', md5($hash.$password));
            } while (--$count);
        }

        $output = substr($setting, 0, 12);
        $output .= $this->encode64($hash, 16);

        return $output;
    }

    /**
     * Generate a password salt based on the given input string.
     *
     * @param string $input The input string to generate the salt from.
     * @return string Returns the password salt prefixed with `$P$`.
     */
    private function gensaltPrivate($input) {
        $output = '$P$';
        $output .= $this->itoa64[min($this->iteration_count_log2 + ((PHP_VERSION >= '5') ? 5 : 3), 30)];
        $output .= $this->encode64($input, 6);

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function verify($password, $hash) {
        $calc_hash = $this->cryptPrivate($password, $hash);
        if ($calc_hash[0] === '*') {
            $calc_hash = crypt($password, $hash);
        }

        return $calc_hash === $hash;
    }
}
