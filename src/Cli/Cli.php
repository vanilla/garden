<?php

namespace Garden\Cli;

/**
 * Push arguments parser
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @license MIT
 * @copyright 2010-2014 Vanilla Forums Inc.
 */
class Cli {
    /// Properties ///
    protected $commandSchemas;

    protected $currentSchema;


    /// Methods ///

    public function __construct($defaultSchema = []) {
        $this->commandSchemas = ['*' => []];

        if (!empty($defaultSchema)) {
            $this->commandSchemas['*'] = $defaultSchema;
        }

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
     * @return bool Returns true if the schema has a command.
     */
    public function hasCommand() {
        foreach ($this->commandSchemas as $pattern => $opts) {
            if (strpos($pattern, '*') === false) {
                return true;
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

    public function parse($argv = null, $exit = true) {
        $args = $this->parseRaw($argv);

//        print_r($args);
//        echo "\n";

        $hasCommand = $this->hasCommand();

        // If no command is given then write a list of commands.
        if ($hasCommand && !$args->command()) {
            $this->writeUsage($args);
            $this->writeCommands();
            $result = false;
        }
        // Write the help.
        elseif ($args->getOpt('help')) {
            $this->writeUsage($args);
            $this->writeHelp($this->getSchema($args->command()));
            $result = false;
        }
        // Validate the arguments against the schema.
        else {
            $validArgs = $this->validate($args);
            $result = $validArgs;
        }

        if ($result === false && $exit) {
            exit();
        }
        return $result;
    }

    /**
     * Parse an array of arguments
     *
     * If the first item in the array is in the form of a command (no preceeding
     * - or --), 'command' is filled with its value.
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
                if ($hasCommand) {
                    $parsed->command($argv[0]);
                    array_shift($argv);
                } else {
                    $parsed->addArg($argv[0]);
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
     * @return Args
     */
    public function validate(Args $args) {
        $valid = new Args($args->command());
        $schema = $this->getSchema($valid->command());
        unset($schema['__meta']);
        $opts = $args->opts();
        $missing = [];

        foreach ($schema as $key => $definition) {
            // No Parameter (default)
            $required = val('required', $definition, false);
            $value = null;

            // Check for --key.
            if (isset($opts[$key])) {
                $valid->setOpt($key, $opts[$key]);
                unset($opts[$key]);
            }
            // Check for -s.
            elseif (isset($definition['short']) && isset($opts[$definition['short']])) {
                $valid->setOpt($key, $args->getOpt($opts[$definition['short']]));
                unset($opts[$opts[$definition['short']]]);
            }
            // Check for --no-key.
            elseif (isset($opts['no-'.$key])) {
                $valid->setOpt($key, !(bool)$opts['no-'.$key]);
                unset($opts['no-'.$key]);
            }
            // The key was not supplied. Is it required?
            elseif ($definition['required']) {
                $missing[$key] = true;
            }
        }

        $isValid = true;

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
            return false;
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
     * Build schema help
     *
     * @param array $schema
     * @return string
     */
    public function help($schema) {
        $help = '';
        foreach ($schema as $key => $definition) {
            $longKey = "--{$key}";
            $shortKey = ($shortKey = val('short', $definition, false)) ? "-{$shortKey}" : null;
            $keys = $longKey;
            if (!is_null($shortKey))
                $keys = "{$shortKey}, {$keys}";
            $type = val('value', $definition, 'flag');
            if ($type == 'opt')
                $keys .= " <{$key}>";

            // Output argument
            $required = val('required', $definition, false);
            if ($required)
                $keys = Cli::bold($keys);
            $w = strlen($keys) + 7;
            $help .= sprintf("%{$w}s\n", $keys);

            // Output justified description
            $description = $definition[0];
            $l = strlen($description);
            $chunk = 77;
            $i = 0;
            do {
                $snippet = substr($description, $i, $chunk);
                $i += $chunk;
                $len = strlen($snippet);
                $w = $len + 11;
                $help .= sprintf("%{$w}s\n", $snippet);
            } while ($i < $l);
            $help .= "\n";
        }
        return rtrim($help);
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
     * @param string $short The short name of the opt.
     * @return Cli Returns this object for fluent calls.
     */
    public function opt($name, $description, $required = false, $short = '') {
        $this->currentSchema[$name] = [$description, 'required' => $required, 'short' => $short];
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
     * @param $pattern The schema pattern.
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
                    $keys = "-$shortKey, $keys";
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
        }
    }

    /**
     * Writes the basic usage information of the command.
     * @param Args $args The parsed args returned from {@link Cli::parse()}.
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

            if (count($schema)) {
                echo " <options>";
            }

            echo "\n\n";
        }
    }

}