<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Password;

/**
 * Implements the default Vanilla password algorithm.
 */
class VanillaPassword extends PhpassPassword {
    /**
     * Initialize an instance of the {@link VanillaPassword} class.
     */
    public function __construct() {
        parent::__construct(PhpassPassword::HASH_BEST);
    }

    /**
     * {@inheritdoc}
     */
    public function hash($password) {
        if ($this->hashMethod === static::HASH_BEST && function_exists('password_hash')) {
            return password_hash($password, PASSWORD_DEFAULT);
        } else {
            return parent::hash($password);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash($hash) {
        if ($this->hashMethod === static::HASH_BEST &&  function_exists('password_needs_rehash')) {
            return password_needs_rehash($hash, PASSWORD_DEFAULT);
        } elseif (($this->hashMethod & static::HASH_BLOWFISH) && CRYPT_BLOWFISH === 1) {
            return !(preg_match('`^\$(2[axy]|[56])\$`', $hash) && strlen($hash) === 60);
        } elseif (($this->hashMethod & static::HASH_EXTDES) && CRYPT_EXT_DES === 1) {
            return !(preg_match('`^_[./0-9A-Za-z]{8}`', $hash) && strlen($hash) === 20);
        } else {
            return !(preg_match('`^\$([PH])\$`', $hash) && strlen($hash) === 34);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verify($password, $hash) {
        if ($this->hashMethod === static::HASH_BEST &&
            function_exists('password_verify') &&
            password_verify((string)$password, (string)$hash)) {

            return true;
        }

        if (!$hash) {
            return false;
        }

        // Check against a php pass style password.
        if (in_array(substr($hash, 0, 1), ['_', '$'])) {
            return parent::verify($password, $hash);
        } elseif (md5($password) === $hash) {
            return true;
        } elseif ($password === $hash) {
            return true;
        }
        return false;

    }
}
