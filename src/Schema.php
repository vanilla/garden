<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden;


use Garden\Exception\ValidationException;

class Schema {
    /// Properties ///
    protected $schema = [];

    protected static $types = [
//        '@' => 'file',
        'a' => 'array',
        'o' => 'object',
        '=' => 'base64',
        'i' => 'integer',
        's' => 'string',
        'f' => 'float',
        'b' => 'boolean',
        'ts' => 'timestamp',
        'dt' => 'datetime'
    ];

    /**
     * @var array An array of callbacks that will custom validate the schema.
     */
    protected $validators = [];

    /// Methods ///

    /**
     * Initialize an instance of a new {@link Schema} class.
     *
     * @param array $schema The array schema to validate against.
     */
    public function __construct($schema = []) {
        $this->schema = static::parse($schema);
    }

    /**
     * Create a new schema and return it.
     *
     * @param array $schema The schema array.
     * @return Schema Returns the newly created and parsed schema.
     */
    public static function create($schema = []) {
        $new = new Schema($schema);
        return $new;
    }

    /**
     * Parse a schema in short form into a full schema array.
     *
     * @param array $arr The array to parse into a schema.
     * @return array The full schema array.
     * @throws \InvalidArgumentException Throws an exception when an item in the schema is invalid.
     */
    public static function parse(array $arr) {
        $result = [];

        foreach ($arr as $key => $value) {
            if (is_int($key)) {
                if (is_string($value)) {
                    // This is a short param value.
                    $param = static::parseShortParam($value);
                    $name = $param['name'];
                    $result[$name] = $param;
                } else {
                    throw new \InvalidArgumentException("Schema at position $key is not a valid param.", 422);
                }
            } else {
                // The parameter is defined in the key.
                $param = static::parseShortParam($key, $value);
                $name = $param['name'];

                if (is_array($value)) {
                    // The value describes a bit more about the schema.
                    switch ($param['type']) {
                        case 'array':
                            if (isset($value['items'])) {
                                // The value includes array schema information.
                                $param = array_replace($param, $value);
                            } else {
                                // The value is a schema of items.
                                $param['items'] = $value;
                            }
                            break;
                        case 'object':
                            // The value is a schema of the object.
                            $param['properties'] = static::parse($value);
                            break;
                        default:
                            $param = array_replace($param, $value);
                            break;
                    }
                } elseif (is_string($value)) {
                    if ($param['type'] === 'array') {
                        // Check to see if the value is the item type in the array.
                        if (isset(self::$types[$value])) {
                            $arrType = self::$types[$value];
                        } elseif (($index = array_search($value, self::$types)) !== false) {
                            $arrType = self::$types[$value];
                        }

                        if (isset($arrType)) {
                            $param['items'] = ['type' => $arrType];
                        } else {
                            $param['description'] = $value;
                        }
                    } else {
                        // The value is the schema description.
                        $param['description'] = $value;
                    }
                }

                $result[$name] = $param;
            }
        }

        return $result;
    }

    /**
     * Parse a short parameter string into a full array parameter.
     *
     * @param string $str The short parameter string to parse.
     * @param array $other An array of other information that might help resolve ambiguity.
     * @return array Returns an array in the form [name, [param]].
     * @throws \InvalidArgumentException Throws an exception if the short param is not in the correct format.
     */
    public static function parseShortParam($str, $other = []) {
        // Is the parameter optional?
        if (str_ends($str, '?')) {
            $required = false;
            $str = substr($str, 0, -1);
        } else {
            $required = true;
        }

        // Check for a type.
        $parts = explode(':', $str);

        if (count($parts) === 1) {
            if (isset($other['type'])) {
                $type = $other['type'];
            } else {
                $type = 'string';
            }
            $name = $parts[0];
        } else {
            $name = $parts[1];

            if (isset(self::$types[$parts[0]])) {
                $type = self::$types[$parts[0]];
            } else {
                throw new \InvalidArgumentException("Invalid type {$parts[1]} for field $name.", 500);
            }
        }

        $result = ['name' => $name, 'type' => $type, 'required' => $required];

        return $result;
    }

    /**
     * Add a custom validator to to validate the schema.
     *
     * @param string $fieldname The name of the field to validate, if any.
     * @param callable $callback The callback to validate with.
     * @return Schema Returns `$this` for fluent calls.
     */
    public function addValidator($fieldname, callable $callback) {
        $this->validators[$fieldname][] = $callback;
        return $this;
    }


    /**
     * Require one of a given set of fields in the schema.
     *
     * @param array $fieldnames The field names to require.
     * @param int $count The count of required items.
     * @return Schema Returns `$this` for fluent calls.
     */
    public function requireOneOf(array $fieldnames, $count = 1) {
        return $this->addValidator('*', function ($data, Validation $validation) use ($fieldnames, $count) {
            $hasCount = 0;
            $flattened = [];

            foreach ($fieldnames as $name) {
                $flattened = array_merge($flattened, (array)$name);

                if (is_array($name)) {
                    // This is an array of required names. They all must match.
                    $hasCountInner = 0;
                    foreach ($name as $nameInner) {
                        if (isset($data[$nameInner]) && $data[$nameInner]) {
                            $hasCountInner++;
                        } else {
                            break;
                        }
                    }
                    if ($hasCountInner >= count($name)) {
                        $hasCount++;
                    }
                } elseif (isset($data[$name]) && $data[$name]) {
                    $hasCount++;
                }

                if ($hasCount >= $count) {
                    return true;
                }
            }

            $messageFields = array_map(function ($v) {
                if (is_array($v)) {
                    return '('.implode(', ', $v).')';
                }
                return $v;
            }, $fieldnames);

            if ($count === 1) {
                $message = sprintft('One of %s are required.', implode(', ', $messageFields));
            } else {
                $message = sprintft('%1$s of %2$s are required.', $count, implode(', ', $messageFields));
            }

            $validation->addError('missing_field', $flattened, [
                'message' => $message
            ]);
        });
    }

    /**
     * Validate data against the schema.
     *
     * @param array &$data The data to validate.
     * @param Validation $validation This argument will be filled with the validation result.
     * @return bool Returns true if the data is valid, false otherwise.
     * @throws ValidationException Throws an exception when the data does not validate against the schema.
     */
    public function validate(array &$data, Validation &$validation = null) {
        if (!$this->isValid($data, $validation)) {
            throw new ValidationException($validation);
        }
        return $this;
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param array &$data The data to validate.
     * @param Validation &$validation This argument will be filled with the validation result.
     * @return bool Returns true if the data is valid. False otherwise.
     */
    public function isValid(array &$data, Validation &$validation = null) {
        return $this->isValidInternal($data, $this->schema, $validation);
    }

    /**
     * Validate data against the schema and return the result.
     *
     * @param array &$data The data to validate.
     * @param array $schema The schema array to validate against.
     * @param Validation &$validation This argument will be filled with the validation result.
     * @return bool Returns true if the data is valid. False otherwise.
     */
    protected function isValidInternal(array &$data, array $schema, Validation &$validation = null) {
        if ($validation === null) {
            $validation = new Validation();
        }

        // Loop through the schema fields and validate each one.
        foreach ($schema as $field => $params) {
            if (isset($data[$field])) {
                $this->validateField($data[$field], $params, $validation);
            } elseif (val('required', $params)) {
                $validation->addError('missing_field', $field);
            }
        }

        // Validate the global validators.
        if (isset($this->validators['*'])) {
            foreach ($this->validators['*'] as $callback) {
                call_user_func($callback, $data, $validation);
            }
        }

        return $validation->isValid();
    }

    /**
     * Validate a field.
     *
     * @param mixed &$value The value to validate.
     * @param array $field Parameters on the field.
     * @param Validation $validation A validation object to add errors to.
     * @throws \InvalidArgumentException Throws an exception when there is something wrong in the {@link $params}.
     * @internal param string $fieldname The name of the field to validate.
     * @return bool Returns true if the field is valid, false otherwise.
     */
    protected function validateField(&$value, $field, Validation $validation) {
        $fieldname = $field['name'];
        $type = $field['type'];
        $required = val('required', $field, false);
        $valid = true;

        // Check required first.
        if ($value === '' || $value === null) {
            if (!$required) {
                if (!($type === 'boolean' && $value === false)) {
                    $value = null;
                }
                return true;
            }

            switch ($type) {
                case 'boolean':
                    $value = false;
                    return true;
                case 'string':
                    if (val('minLength', $field, 1) == 0) {
                        $value = '';
                        return true;
                    }
            }
            $validation->addError('missing_field', $fieldname);
            return false;
        }

        // Validate the field's type.
        $validType = true;
        switch ($type) {
            case 'boolean':
                if (is_bool($value)) {
                    $validType = true;
                } else {
                    $bools = ['0' => false, 'false' => false, '1' => true, 'true' => true];
                    if (isset($bools[$value])) {
                        $value = $bools[$value];
                        $validType = true;
                    } else {
                        $validType = false;
                    }
                }
                break;
            case 'integer':
                if (is_int($value)) {
                    $validType = true;
                } elseif (is_numeric($value)) {
                    $value = (int)$value;
                    $validType = true;
                } else {
                    $validType = false;
                }
                break;
            case 'float':
                if (is_float($value)) {
                    $validType = true;
                } elseif (is_numeric($value)) {
                    $value = (float)$value;
                    $validType = true;
                } else {
                    $validType = false;
                }
                break;
            case 'string':
                if (is_string($value)) {
                    $validType = true;
                } elseif (is_numeric($value)) {
                    $value = (string)$value;
                    $validType = true;
                } else {
                    $validType = false;
                }
                break;
            case 'timestamp':
                if (is_numeric($value)) {
                    $value = (int)$value;
                    $validType = true;
                } elseif (is_string($value) && $ts = strtotime($value)) {
                    $value = $ts;
                } else {
                    $validType = false;
                }
                break;
            case 'datetime':
                $dt = date_create($value);
                if ($dt) {
                    $value = $dt;
                } else {
                    $validType = false;
                }
                break;
            case 'base64':
                if (!is_string($value)
                    || !preg_match('`^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$`', $value)) {

                    $validType = false;
                }
                break;
            case 'array':
                if (!is_array($value) || !isset($value[0])) {
                    $validType = false;
                }
                break;
            case 'object':
                if (!is_array($value) || isset($value[0])) {
                    $validType = false;
                }
                break;
            default:
                throw new \InvalidArgumentException("Unrecognized type $type.", 500);
                break;
        }
        if (!$validType) {
            $valid = false;
            $validation->addError(
                'invalid_type',
                $fieldname,
                [
                    'type' => $type,
                    'message' => sprintft('%1$s is not a valid %2$s.', $fieldname, $type),
                    'status' => 422
                ]
            );
        }

        // Validate a custom field validator.
        if (isset($this->validators[$fieldname])) {
            foreach ($this->validators[$fieldname] as $callback) {
                call_user_func_array($callback, [&$value, $field, $validation]);
            }
        }

        return $valid;
    }
}
