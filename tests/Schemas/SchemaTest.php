<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

/**
 * Base class for schema tests.
 */
class SchemaTest extends \PHPUnit_Framework_TestCase {
    /**
     * Provides all of the schema types.
     *
     * @return array Returns an array of types suitable to pass to a test method.
     */
    public function provideTypes() {
        $result = [
            'array' => ['a', 'array'],
            'object' => ['o', 'object'],
            'base64' => ['=', 'base64'],
            'integer' => ['i', 'integer'],
            'string' => ['s', 'string'],
            'float' => ['f', 'float'],
            'boolean' => ['b', 'boolean'],
            'timestamp' => ['ts', 'timestamp'],
            'datetime' => ['dt', 'datetime']
        ];
        return $result;
    }
}
