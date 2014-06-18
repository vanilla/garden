<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Exception;

/**
 * Represents an error caused by something the user did.
 *
 * Throw this exception whenever you encounter an error that
 * a) resulted from user input, and
 * b) can be considered expected behavior from the user.
 *
 * Most exceptions will generate a stack trace and other debugging information when in debug mode.
 * The {@link UserException} will always display just its message since it represents expected behavior.
 */
class UserException extends ClientException {
}
