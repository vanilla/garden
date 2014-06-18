<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Garden;


use Garden\Exception\ValidationException;

class Validation {
    /// Properties ///

    protected $errors = [];

    protected $mainMessage;

    protected $status;

    /// Methods ///

    /**
     * @param array $errors
     * @param string $mainMessage
     * @param int $status
     */
    public function __construct($errors = [], $mainMessage = '', $status = 0) {
        $this->errors = $errors;
        $this->mainMessage = $mainMessage;
        $this->status = $status;
    }

    public static function errorMessage($error) {
        if (isset($error['message'])) {
            return $error['message'];
        } else {
            $field = val('field', $error, '*');
            if (is_array($field)) {
                $field = implode(', ', $field);
            }
            return sprintft($error['code'].': %s.', $field);
        }
    }

    public function addError($messageCode, $field = '*', $options = []) {
        $error = [];
        if (substr($messageCode, 0, 1) === '@') {
            $error['message'] = substr($messageCode, 1);
        } else {
            $error['code'] = $messageCode;
        }
        if (is_array($field)) {
            $fieldKey = '*';
            $error['field'] = $field;
        } else {
            $fieldKey = $field;
            if ($field !== '*') {
                $error['field'] = $field;
            }
        }

        $error += $options;

        $this->errors[$fieldKey][] = $error;

        return $this;
    }

    public function mainMessage($value = null) {
        if ($value !== null) {
            $this->mainMessage = $value;
            return $this;
        }

        return $this->mainMessage;
    }

    public function status($value = null) {
        if ($value !== null) {
            $this->status = $value;
            return $this;
        }
        if ($this->status) {
            return $this->status;
        }

        // There was no status so loop through the errors and look for the highest one.
        $maxStatus = 400;
        foreach ($this->errors as $field => $errors) {
            foreach ($errors as $error) {
                if (isset($error['status']) && $error['status'] > $maxStatus) {
                    $maxStatus = $error['status'];
                }
            }
        }
        return $maxStatus;
    }

    /**
     * Get the message for this exception.
     *
     * @return string Returns the exception message.
     */
    public function getMessage() {
        if ($this->mainMessage) {
            return $this->mainMessage;
        }

        // Generate the message by concatenating all of the errors together.
        $messages = [];
        foreach ($this->errors as $errors) {
            foreach ($errors as $error) {
                $field = val('field', $error, '*');
                if (is_array($field)) {
                    $field = implode(', ', $field);
                }

                if (isset($error['message'])) {
                    $message = $error['message'];
                } else {
                    $message = sprintft($error['code'].': %s.', $field);
                }

                $messages[] = $message;
            }
        }
        return implode(' ', $messages);
    }

    public function getErrorsFlat() {
        $result = [];
        foreach ($this->errors as $errors) {
            foreach ($errors as $error) {
                $result[] = $error;
            }
        }
        return $result;
    }

    /**
     * Check whether or not the validation is free of errors.
     *
     * @return bool Returns true if there are no errors, false otherwise.
     */
    public function isValid() {
        return count($this->errors) === 0;
    }

    /**
     * Check whether or not a particular field is has errors.
     *
     * @param $field The name of the field to check for validity.
     * @return bool Returns true if the field has no errors, false otherwise.
     */
    public function fieldValid($field) {
        $result = !isset($this->errors[$field]) || count($this->errors[$field]) === 0;
        return $result;
    }
}
