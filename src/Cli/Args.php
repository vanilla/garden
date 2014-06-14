<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli;

/**
 * This class represents the parsed and validated argument list.
 */
class Args implements \JsonSerializable {
    protected $command;
    protected $opts;
    protected $args;
    protected $meta;

    /**
     * Initialize the {@link Args} instance.
     *
     * @param string $command The name of the command.
     * @param array $opts An array of command line options.
     * @param array $args A numeric array of command line args.
     */
    public function __construct($command = '', $opts = [], $args = []) {
        $this->command = $command;
        $this->opts = $opts;
        $this->args = $args;
        $this->meta = [];
    }

    /**
     * Add an argument to the args array.
     *
     * @param string $value The argument to add.
     */
    public function addArg($value) {
        $this->args[] = $value;
    }

    public function args($value = null) {
        if ($value !== null) {
            $this->args = $value;
        }
        return $this->args;
    }

    public function command($value = null) {
        if ($value !== null) {
            $this->command = $value;
        }
        return $this->command;
    }

    public function getMeta($name, $default = null) {
        return val($name, $this->meta, $default);
    }

    public function setMeta($name, $value) {
        $this->meta[$name] = $value;
    }

    public function opts($value = null) {
        if ($value !== null) {
            $this->opts = $value;
        }
        return $this->opts;
    }

    /**
     * Get the value of a passed option
     *
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function getOpt($option, $default = null) {
        return val($option, $this->opts, $default);
    }

    public function setOpt($option, $value) {
        $this->opts[$option] = $value;
    }

    public function jsonSerialize() {
        return [
            'command' => $this->command,
            'opts' => $this->opts,
            'args' => $this->args,
            'meta' => $this->meta
        ];
    }
}