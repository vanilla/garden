<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

if (!function_exists('url')) {
    /**
     * Construct a url on the current site.
     *
     * @param string $path The path of the url.
     * @param mixed $domain Whether or not to include the domain. This can be one of the following.
     * - false: The domain will not be included.
     * - true: The domain will be included.
     * - //: A schemeless domain will be included.
     * - /: Just the path will be returned.
     * @return string Returns the url.
     */
    function url($path, $domain = false) {
        if (is_url($path)) {
            return $path;
        }

        return Garden\Request::current()->makeUrl($path, $domain);
    }
}

if (!function_exists('is_id')) {
    /**
     * Finds whether the type given variable is a database id.
     *
     * @param mixed $val The variable being evaluated.
     * @param bool $allow_slugs Whether or not slugs are allowed in the url.
     * @return bool Returns `true` if the variable is a database id or `false` if it isn't.
     */
    function is_id($val, $allow_slugs = false) {
        return is_numeric($val);
    }
}