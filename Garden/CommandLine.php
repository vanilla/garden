<?php

namespace Garden;

class CommandLine {

    /// Constants ///

    const CALL = 'call';
    const OPTS = 'opts';
    const ARGS = 'args';

    const COMMAND = 'command';
    const FILENAME = 'filename';
    const BASENAME = 'basename';
    const PATH = 'path';

    /// Properties ///
    /// Methods ///

    /**
     * Parse the command line array and return an array of parsed elements.
     * @param array $argv A command line array as returned from the global $argv.
     * @param bool $command If the arguments represent a command with a subcommand such as git then pass true to this function.
     * @return array Returns an array of the parsed command.
     *
     * The returned array has the following form.
     *
     * ```
     * array(
     *     'call' => array(
     *         'filename' => '', // the filename of the call
     *         'basename' => '', // the basename of the call
     *         'command' => '', // name fo the command
     *         'path' => '', // the full path of the call
     *     ),
     *     'opts' => array(
     *         'key1' => 'value1',
     *         'key2' => 'value2', // command line options here.
     *     ),
     *     'args' => array (
     *         'arg1',
     *         'arg2', // any arguments here
     *     ),
     * )
     * ```
     *
     *
     */
    public static function parse($argv = null, $command = false) {
        if ($argv === null)
            $argv = $_SERVER['argv'];

        $result = array(
            self::CALL => array(),
            self::OPTS => array(),
            self::ARGS => array(),
        );

        // The first argument is always the script name.
        $path = array_shift($argv);
        $path = str_replace('\\', '/', $path);
        $filename = basename($path);
        if (($pos = strrpos($filename, '.')) !== false) {
            $basename = substr($filename, 0, $pos);
        } else {
            $basename = $filename;
        }
        $result[self::CALL] = array(
            self::BASENAME => $basename,
            self::COMMAND => null,
            self::PATH => $path,
            self::FILENAME => $filename,
        );

        $key = null;
        $after_opts = false;
        $command = (int) $command;

        // Loop through the args and grab them.
        foreach ($argv as $i => $arg) {
            if ($after_opts) {
                // After the options each arg is added to the args key.
                $result[self::ARGS][] = $arg;
            } elseif ($arg === '--') {
                // This is the end of options marker.
                $after_opts = true;
            } elseif (substr($arg, 0, 2) === '--') {
                // This is a long argument.
                $arg = substr($arg, 2);

                $eq_pos = strpos($arg, '=');
                if ($eq_pos !== false) {
                    // --key=value
                    $key = substr($arg, 0, $eq_pos);
                    $value = substr($arg, $eq_pos + 1);
                    $opts[$key] = $value;
                    $key = null;
                } else {
                    // -- key
                    $key = $arg;
                    $opts[$key] = true;
                }
            } elseif (substr($arg, 0, 1) === '-') {
                // This is a short argument.
                $arg = substr($arg, 1);

                if (strlen($arg) > 1) {
                    // -kvalue
                    $key = substr($arg, 0, 1);
                    $value = substr($arg, 1);
                    $opts[$key] = $value;
                    $key = null;
                } else {
                    // -k
                    $key = $arg;
                    $opts[$key] = true;
                }
            } else {
                // This is a regular value or command.

                if ($i <= $command) {
                    // This is a command.
                    if ($command > 1) {
                        // There are more than one command so make it an array.
                        $result[self::CALL][self::COMMAND][] = $arg;
                    } else {
                        $result[self::CALL][self::COMMAND] = $arg;
                    }
                } elseif ($key !== null) {
                    // The key was the previous arg so add it.
                    $opts[$key] = $arg;
                    $key = null;
                } else {
                    // This argument was not associated with any key so the options are over.
                    $after_opts = true;
                    $result[self::ARGS][] = $arg;
                }
            }
        }
        $result[self::OPTS] = $opts;

        return $result;
    }

}
