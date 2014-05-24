<?php

namespace Garden\Cli;

/**
 * A general purpose command line parser.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @license MIT
 * @copyright 2010-2014 Vanilla Forums Inc.
 */
class Cli {
    /// Properties ///
    /**
     * @var array All of the schemas, indexed by command pattern.
     */
    protected $commandSchemas;

    /**
     * @var array A pointer to the current schema.
     */
    protected $currentSchema;


    /// Methods ///

    /**
     * Creates a {@see Cli} instance representing a command line parser for a given schema.
     */
    public function __construct() {
        $this->commandSchemas = ['*' => []];

        // Select the current schema.
        $this->currentSchema =& $this->commandSchemas['*'];
    }


    /**
     * Breaks a cell into several lines according to a given width.
     * @param string $text The text of the cell.
     * @param int $width The width of the cell.
     * @param bool $addSpaces Whether or not to right-pad the cell with spaces.
     * @return array Returns an array of strings representing the lines in the cell.
     */
    public static function breakLines($text, $width, $addSpaces = true) {
        $rawLines = explode("\n", $text);
        $lines = [];

        foreach ($rawLines as $line) {
            // Check to see if the line needs to be broken.
            $sublines = static::breakString($line, $width, $addSpaces);
            $lines = array_merge($lines, $sublines);
        }

        return $lines;
    }

    /**
     * Breaks a line of text according to a given width.
     * @param string $line The text of the line.
     * @param int $width The width of the cell.
     * @param bool $addSpaces Whether or not to right pad the lines with spaces.
     * @return array Returns an array of lines, broken on word boundries.
     */
    protected static function breakString($line, $width, $addSpaces = true) {
        $words = explode(' ', $line);
        $result = [];

        $line = '';
        foreach ($words as $word) {
            $candidate = trim($line.' '.$word);

            // Check for a new line.
            if (strlen($candidate) > $width) {
                if ($line === '') {
                    // The word is longer than a line.
                    if ($addSpaces) {
                        $result[] = substr($candidate, 0, $width);
                    } else {
                        $result[] = $candidate;
                    }
                } else {
                    if ($addSpaces) {
                        $line .= str_repeat(' ', $width - strlen($line));
                    }

                    // Start a new line.
                    $result[] = $line;
                    $line = $word;
                }
            } else {
                $line = $candidate;
            }
        }

        // Add the remaining line.
        if ($line) {
            if ($addSpaces) {
                $line .= str_repeat(' ', $width - strlen($line));
            }

            // Start a new line.
            $result[] = $line;
        }

        return $result;
    }

    /**
     * Sets the description for the current schema.
     * @param string $str
     * @return Cli Returns this class for fluent calls.
     */
    public function description($str = null) {
        return $this->meta('description', $str);
    }

    /**
     * Determines whether or not the schema has a command.
     * @param string $name Check for the specific command name.
     * @return bool Returns true if the schema has a command.
     */
    public function hasCommand($name = '') {
        if ($name) {
            return array_key_exists($name, $this->commandSchemas);
        } else {
            foreach ($this->commandSchemas as $pattern => $opts) {
                if (strpos($pattern, '*') === false) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Determins whether a command has options.
     * @param string $command The name of the command or an empty string for any command.
     */
    public function hasOptions($command = '') {
        if ($command) {
            $def = $this->getSchema($command);
            if (count($def) > 1 || (count($def) > 0 && !isset($def['__meta']))) {
                return true;
            } else {
                return false;
            }
        } else {
            foreach ($this->commandSchemas as $pattern => $def) {
                if (count($def) > 1 || (count($def) > 0 && !isset($def['__meta']))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Finds our whether a pattern is a command.
     * @param string $pattern The pattern being evaluated.
     * @return bool Returns `true` if `$pattern` is a command, `false` otherwise.
     */
    public static function isCommand($pattern) {
        return strpos($pattern, '*') === false;
    }

    /**
     * Parses and validates a set of command line arguments the schema.
     * @param array $argv The command line arguments a form compatible with the global `$argv` variable.
     *
     * Note that the `$argv` array must have at least one element and it must represent the path to the command that
     * invoked the command. This is used to write usage information.
     * @param bool $exit Whether to exit the application when there is an error or when writing help.
     * @return Args|null Returns an {@see Args} instance when a command should be executed or `null` when one shouldn't.
     */
    public function parse($argv = null, $exit = true) {
        $args = $this->parseRaw($argv);

        $hasCommand = $this->hasCommand();

        // If no command is given then write a list of commands.
        if ($hasCommand && !$args->command()) {
            $this->writeUsage($args);
            $this->writeCommands();
            $result = null;
        }
        // Write the help.
        elseif ($args->getOpt('help')) {
            $this->writeUsage($args);
            $this->writeHelp($this->getSchema($args->command()));
            $result = null;
        }
        // Validate the arguments against the schema.
        else {
            $validArgs = $this->validate($args);
            $result = $validArgs;
        }

        if ($result === null && $exit) {
            exit();
        }
        return $result;
    }

    /**
     * Parse an array of arguments
     *
     * If the first item in the array is in the form of a command (no preceeding - or --),
     * 'command' is filled with its value.
     *
     * @param array $argv An array of arguments passed in a form compatible with the global `$argv` variable.
     * @return Args Returns the raw parsed arguments.
     * @throws \Exception Throws an exception when {@see $argv} isn't an array.
     */
    public function parseRaw($argv = null) {
        if ($argv === null) {
            $argv = $GLOBALS['argv'];
        }

        if (!is_array($argv))
            throw new \Exception(__METHOD__ . " expects an array", 400);

        $path = array_shift($argv);
        $hasCommand = $this->hasCommand();

        $parsed = new Args();
        $parsed->setMeta('path', $path);
        $parsed->setMeta('filename', basename($path));

        if ($argc = count($argv)) {
            // Get possible command.
            if (substr($argv[0], 0, 1) != '-') {
                $arg0 = array_shift($argv);
                if ($hasCommand) {
                    $parsed->command($arg0);
                } else {
                    $parsed->addArg($arg0);
                }
            }

            // Parse opts.
            for ($i = 0; $i < count($argv); $i++) {
                $str = $argv[$i];

                // --
                if ($str === '--') {
                    $i++;
                    break;
                }
                // --foo
                elseif (strlen($str) > 2 && substr($str, 0, 2) == '--') {
                    $str = substr($str, 2);
                    $parts = explode('=', $str);
                    $key = $parts[0];

                    // Does not have an =, so choose the next arg as its value
                    if (count($parts) == 1 && isset($argv[$i + 1]) && preg_match('/^--?.+/', $argv[$i + 1]) == 0) {
                        $v = $argv[$i + 1];
                        $i++;
                    } elseif (count($parts) == 2) {// Has a =, so pick the second piece
                        $v = $parts[1];
                    } else {
                        $v = true;
                    }
                    $parsed->setOpt($key, $v);
                }
                // -a
                elseif (strlen($str) == 2 && $str[0] == '-') {
                    $key = $str[1];

                    if (isset($argv[$i + 1]) && preg_match('/^--?.+/', $argv[$i + 1]) == 0) {
                        $v = $argv[$i + 1];
                        $i++;
                    } else {
                        $v = true;
                    }

                    $parsed->setOpt($key, $v);
                }
                // -abcdef
                elseif (strlen($str) > 1 && $str[0] == '-') {
                    for ($j = 1; $j < strlen($str); $j++) {
                        $parsed->setOpt($str[$j], true);
                    }
                }
                // End of opts
                else {
                    break;
                }
            }

            // Grab the remaining args.
            for (; $i < count($argv); $i++) {
                $parsed->addArg($argv[$i]);
            }
        }

        return $parsed;
    }

    /**
     * Validates arguments against the schema.
     *
     * @param Args $args
     * @return Args|null
     */
    public function validate(Args $args) {
        $isValid = true;
        $command = $args->command();
        $valid = new Args($command);
        $schema = $this->getSchema($command);
        $meta = $schema['__meta'];
        unset($schema['__meta']);
        $opts = $args->opts();
        $missing = [];

        // Check to see if the command is correct.
        if ($command && !$this->hasCommand($command) && $this->hasCommand()) {
            echo Cli::red("Invalid command: $command.\n");
            $isValid = false;
        }

        // Add the args.
        $valid->args($args->args());

        foreach ($schema as $key => $definition) {
            // No Parameter (default)
            $required = val('required', $definition, false);
            $type = val('type', $definition, 'string');
            $value = null;

            // Check for --key.
            if (isset($opts[$key])) {
                $value = $opts[$key];
                if ($this->validateType($value, $type)) {
                    $valid->setOpt($key, $value);
                } else {
                    echo Cli::red("The value of --$key is not a valid $type.\n");
                    $isValid = false;
                }
                unset($opts[$key]);
            }
            // Check for -s.
            elseif (isset($definition['short']) && isset($opts[$definition['short']])) {
                $value = $opts[$definition['short']];
                if ($this->validateType($value, $type)) {
                    $valid->setOpt($key, $value);
                } else {
                    echo Cli::red("The value of --$key (-{$definition['short']}) is not a valid $type.\n");
                    $isValid = false;
                }
                unset($opts[$definition['short']]);
            }
            // Check for --no-key.
            elseif (isset($opts['no-'.$key])) {
                $value = $opts['no-'.$key];

                if ($type !== 'bool') {
                    Cli::red("Cannont apply the --no- prefix on the non boolean --$key.\n");
                    $isValid = false;
                } elseif ($this->validateType($value, $type)) {
                    $valid->setOpt($key, !$value);
                } else {
                    Cli::red("The value of --no-$key is not a valid $type.\n");
                    $isValid = false;
                }
                unset($opts['no-'.$key]);
            }
            // The key was not supplied. Is it required?
            elseif ($definition['required']) {
                $missing[$key] = true;
            }
            // The value os not required, but can maybe be coerced into a type.
            elseif ($type === 'bool') {
                $valid->setOpt($key, false);
            }
        }

        if (count($missing)) {
            $isValid = false;
            foreach ($missing as $key => $v) {
                echo Cli::red("Missing required option: $key\n");
            }
        }

        if (count($opts)) {
            $isValid = false;
            foreach ($opts as $key => $v) {
                echo Cli::red("Invalid option: $key\n");
            }
        }

        if ($isValid) {
            return $valid;
        } else {
            echo "\n";
            return null;
        }
    }

    /**
     * Gets the schema full cli schema.
     * @param string $command The name of the command.
     * @return array Returns the schema that matches the command.
     */
    public function getSchema($command = '') {
        $result = [];
        foreach ($this->commandSchemas as $pattern => $opts) {
            if (fnmatch($pattern, $command)) {
                $result = array_replace($result, $opts);
            }
        }
        return $result;
    }

    /**
     * Gets/sets the value for a current meta item.
     * @param string $name
     * @param mixed $value
     * @return mixed Returns the current value of the meta item.
     */
    public function meta($name, $value = null) {
        if ($value !== null) {
            $this->currentSchema['__meta'][$name] = $value;
            return $this;
        }
        if (!isset($this->currentSchema['__meta'][$name])) {
            return null;
        }
        return $this->currentSchema['__meta'][$name];
    }

    /**
     * Adds an option (opt) to the current schema.
     * @param string $name The long name of the parameter.
     * @param string $description A human-readable description for the column.
     * @param bool $required Whether or not the opt is required.
     * @param string $type The type of parameter.
     * This must be one of string, bool, int.
     * @param string $short The short name of the opt.
     * @return Cli Returns this object for fluent calls.
     */
    public function opt($name, $description, $required = false, $type = 'string', $short = '') {
        if (!in_array($type, ['string', 'bool', 'int'])) {
            throw new \Exception("Invalid type: $type. Must be one of string, bool, or int.", 400);
        }
        $this->currentSchema[$name] = [$description, 'required' => $required, 'type' => $type, 'short' => $short];
        return $this;
    }

    /**
     * Ask the user a question and retrieve response
     *
     * Only allow the entry of options specified in $options (case insensitive).
     *
     * @param string $message
     * @param string $prompt
     * @param array $options
     * @param mixed $default
     * @return string
     */
    public function question($message, $prompt, $options, $default = null) {
        foreach ($options as &$opt)
            $opt = strtolower($opt);

        $answered = false;
        do {
            self::prompt($prompt, $options, $default);
            $answer = trim(fgets(STDIN));
            if ($answer == '')
                $answer = $default;
            $answer = strtolower($answer);

            if (in_array($answer, $options))
                $answered = true;
        } while (!$answered);
        return $answer;
    }

    /**
     *
     * @param type $prompt
     * @param type $options
     * @param type $default
     */
    protected function prompt($prompt, $options, $default) {
        if (sizeof($options)) {
            $promptOpts = [];
            foreach ($options as $opt) {
                $promptOpts[] = (strtolower($opt) == strtolower($default)) ? strtoupper($opt) : strtolower($opt);
            }
        }
    }

    /**
     * Selects the current schema name.
     * @param string $pattern The schema pattern.
     * @return Cli Returns this object for fluent calls.
     */
    public function schema($pattern) {
        if (!isset($this->commandSchemas[$pattern])) {
            $this->commandSchemas[$pattern] = [];
        }
        $this->currentSchema =& $this->commandSchemas[$pattern];

        return $this;
    }

    /**
     * Get input from stdin
     *
     * @param string $message
     * @param string $prompt
     * @param string $default
     * @return string
     */
    public static function input($message, $prompt, $default = null) {
        self::prompt($prompt, array(), $default);
        $answer = trim(fgets(STDIN));
        if ($answer == '')
            $answer = $default;
        $answer = strtolower($answer);
        return $answer;
    }

    public static function bold($text) {
        return "\033[1m{$text}\033[0m";
    }

    public static function red($text) {
        return "\033[1;31m{$text}\033[0m";
    }

    public static function green($text) {
        return "\033[1;32m{$text}\033[0m";
    }

    public static function blue($text) {
        return "\033[1;34m{$text}\033[0m";
    }

    public static function purple($text) {
        return "\033[0;35m{$text}\033[0m";
    }

    /**
     * Validate the type of a value an coerce it into the proper type.
     * @param mixed $value The value to validate.
     * @param string $type One of: bool, int, string.
     * @return bool Returns `true` if the value is the correct type.
     * @throws \Exception Throws an exception when {@see $type} is not a known value.
     */
    protected function validateType(&$value, $type) {
        switch ($type) {
            case 'bool':
                if (is_bool($value)) {
                    return true;
                }
                // 0 doesn't work well with in_array() so check it seperately.
                elseif ($value === 0) {
                    $value = false;
                    return true;
                } elseif (in_array($value, [null, '', '0', 'false', 'no'])) {
                    $value = false;
                    return true;
                } elseif (in_array($value, [1, '1', 'true', 'yes'])) {
                    $value = true;
                    return true;
                } else {
                    return false;
                }
            break;
            case 'int':
                if (is_numeric($value)) {
                    $value = (int)$value;
                } else {
                    return false;
                }
            break;
            case 'string':
                $value = (string)$value;
                return true;
            break;
            default:
                throw new \Exception("Unknown type: $type.", 400);
        }
    }

    /**
     * Writes a lis of all of the commands.
     */
    protected function writeCommands() {
        echo static::bold("COMMANDS\n");

        $table = new Table();
        foreach ($this->commandSchemas as $pattern => $schema) {
            if (static::isCommand($pattern)) {
                $table
                    ->row()
                    ->cell($pattern)
                    ->cell(vvalr(['__meta', 'description'], $schema, ''));
            }
        }
        $table->write();
    }

    /**
     * Writes the help for a given schema.
     * @param array $schema A command line scheme returned from {@see Cli::getSchema()}.
     */
    protected function writeHelp($schema) {
        // Write the command description.
        $meta = val('__meta', $schema, []);
        $description = val('description', $meta);

        if ($description) {
            echo implode("\n", Cli::breakLines($description, 80, false))."\n\n";
        }

        unset($schema['__meta']);

        if (count($schema)) {
            echo Cli::bold('OPTIONS')."\n";

            ksort($schema);

            $table = new Table();

            foreach ($schema as $key => $definition) {
                $table->row();

                // Write the keys.
                $keys = "--{$key}";
                if ($shortKey = val('short', $definition, false)) {
                    $keys .= ", -$shortKey";
                }
                if (val('required', $definition)) {
                    $table->bold($keys);
                } else {
                    $table->cell($keys);
                }

                // Write the description.
                $table->cell(val(0, $definition));
            }

            $table->write();
            echo "\n";
        }
    }

    /**
     * Writes the basic usage information of the command.
     * @param Args $args The parsed args returned from {@link Cli::parseRaw()}.
     */
    protected function writeUsage(Args $args) {
        if ($filename = $args->getMeta('filename')) {
            $schema = $this->getSchema($args->command());
            unset($schema['__meta']);

            echo static::bold("usage: ").$filename;

            if ($this->hasCommand()) {
                if ($args->command() && isset($this->commandSchemas[$args->command()])) {
                    echo ' '.$args->command();

                } else {
                    echo ' <command>';
                }
            }

            if ($this->hasOptions($args->command())) {
                echo " [<options>]";
            }

            echo "\n\n";
        }
    }

}