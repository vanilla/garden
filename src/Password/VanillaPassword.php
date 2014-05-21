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
        parent::__construct(false);
    }

    public function hash($password) {
        if (function_exists('password_hash')) {
            return password_hash($password, PASSWORD_DEFAULT);
        } else {
            return parent::hash($password);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash($hash) {
        if (function_exists('password_needs_rehash')) {
            return password_needs_rehash($hash, PASSWORD_DEFAULT);
        } elseif (CRYPT_BLOWFISH === 1) {
            return !(preg_match('`^\$(2[axy]|[56])\$`', $hash) && strlen($hash) === 60);
        } elseif (CRYPT_EXT_DES === 1) {
            return !(preg_match('`^_[./0-9A-Za-z]{8}`', $hash) && strlen($hash) === 20);
        } else {
            return !(preg_match('`^\$([PH])\$`', $hash) && strlen($hash) === 34);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verify($password, $hash) {
        if (function_exists('password_verify')) {
            return password_verify($password, $hash);
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
