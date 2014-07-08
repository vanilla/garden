<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Exception;

/**
 * This exception is thrown from within a dispatched method to tell the application
 * to move on and try matching the rest of the routes.
 */
class Pass extends \Exception {
}
