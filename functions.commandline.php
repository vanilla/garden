<?php

define('CMDLINE_REQUIRED', 1);
define('CMDLINE_NOVALUE', 2);
//define('CMDLINE_OPTIONALVALUE', 4);

define('CMDLINE_FLAGS', 'flags');
define('CMDLINE_SHORT', 'short');
define('CMDLINE_DEFAULT', 'default');
define('CMDLINE_VALID', 'valid');
define('CMDLINE_OPTIONS', 'options');

/*
 * Here's the basic format of the help string.
 * 
 *     key => array('Help string.')
 * 
 * You would call this command with the following command line:
 * 
 *     command key=value
 * 
 * You can also add some additional items to the options array:
 * 
 * - flags: A combination of one or more of the CMDLINE_* flags.
 * - short: The short code for the option. This must be a single letter or a number.
 * - default: The default value if one isn't specified.
 * - valid: An array of valid values for the option.
 * - options: An array of valid options in the following form:  
 *   key => array(): The option will be checked against the key.
 *   If the command specifies this value then this array specifies a new set of options in the same form as the options above.
 * 
 */

/**
 * 
 * @param type $options The options to get.
 * @param type $sections Whether or not to split the options into sections.
 * @return array
 */
function getAllCommandLineOptions($options, $sections = false) {
   $result = array();
   
   if (!isset($options['help']))
      $options['help'] = array('Display this help and exit.', 'short' => '?', 'flags' => CMDLINE_NOVALUE);
   
   if ($sections)
      $result['Global Options'] =& $options;
   else
      $result = $options;
   
   foreach ($options as $key => &$row) {
      if (!isset($row['options']))
         continue;

      $optionvalues = $row['options'];
      $row['valid'] = array_keys($optionvalues);
      
      foreach ($optionvalues as $value => $suboptions) {
         if ($sections) {
            $result["$key=$value"] = $suboptions;
         } else {
            // We need to add the types to each command line option for validation purposes.
            foreach ($suboptions as $longcode => $row) {
               $row['childof'] = array($key, $value);
               $result[$longcode] = $row;
            }
         }
      }
   }
   
   return $result;
}

/**
 * Used internally to get short codes and long codes suitable for getopt().
 * 
 * @param array $options
 * @return array An array of (string short codes, array long codes).
 */
function _getOptCodes($options) {
   $shortCodes = '';
   $longCodes = array();
   
   foreach ($options as $longCode => $row) {
      $flags = val('flags', $row, 0);
      
      $sx = '::';
      if ($flags & CMDLINE_NOVALUE)
         $sx = '';
      elseif ($flags & CMDLINE_REQUIRED)
         $sx = ':';
      
      $short = val(CMDLINE_SHORT, $row);
      if ($short)
         $shortCodes .= $short.$sx;
      $longCodes[] = $longCode.$sx;
   }
   
   return array($shortCodes, $longCodes);
}

/**
 * Parse the command line and return an array of the parsed options.
 * 
 * @param array $options An array defining the command line options.
 * @param type $files
 * @return type
 */
function parseCommandLine($command, $options = null, $files = null) {
   global $argv, $argc;
   
   $optOptions = getAllCommandLineOptions($options, false);
   list($shortCodes, $longCodes) = _getOptCodes($optOptions);
   
   $opts = getopt($shortCodes, $longCodes);
   
   if (isset($opts['help']) || $argc <= 1) {
      writeCommandLineHelp($command, $options, $files);
      die();
   }
   
   $opts = _validateCommandLine($opts, $command, $optOptions, $files);
   if ($opts === false)
      die();
   
   return $opts;
}

/**
 * Validate command line options. Used internally by parseCommandLine().
 * 
 * @param array $values
 * @param string $command
 * @param array $options
 * @param array $files
 * @return array|false
 */
function _validateCommandLine($values, $command, $options, $files = array()) {
   global $argv;
   $errors = array();
   $result = array();
   
   // Validate the files.
   if (!empty($files)) {
      $argv2 = $argv;
      
      foreach ($files as $file) {
         $arg = array_pop($argv2);
         if (!$arg || substr($arg, 0, 1) == '-')
            $errors[] = "Missing required parameter: $file";
         else
            $result[$file] = $arg;
      }
   }
   
//   $Type = V('type', $Values, V('t', $Values));
   
   foreach ($options as $longcode => $row) {
      $flags = val(CMDLINE_FLAGS, $row, 0);
      $short = val(CMDLINE_SHORT, $row);
      
      if (isset($values[$longcode])) {
         // The long code was specified.
         $value = $values[$longcode];
         if (!$value)
            $value = true;
      } elseif ($short && isset($values[$short])) {
         // The short code was specified.
         $value = $values[$short];
         if (!$value)
            $value = true;
      } elseif (isset($row[CMDLINE_DEFAULT])) {
         // No value was specified, but there is a default.
         $value = $row[CMDLINE_DEFAULT];
      } else {
         // There is no value.
         $value = null;
      }
      
      if (!$value) {
         // There is no value.
         $default = val(CMDLINE_DEFAULT, $row, null);
         if ($default === null) {
            if ($flags & CMDLINE_REQUIRED) {
               $errors[] = "Missing required parameter: $longcode";
            }
            
            continue;
         } else {
            $value = $default;
         }
      }
      
      // Check the value against valid types.
      if ($valid = val(CMDLINE_VALID, $row)) {
         if (!in_array($value, $valid)) {
            $errors[] = "Invalid value for parameter: $longcode. Must be one of: ".implode(', ', $valid);
            continue;
         }
      }
      
      $result[$longcode] = $value;
   }
   
   if (count($errors)) {
      writeCommandLineHelp($command, $options, $files, true);
      echo "\n".implode("\n", $errors)."\n";
      return false;
   }
   
   return $result;
}

function writeCommandLineHelp($command, $options, $files = array(), $onlyusage = false) {
   // Write the usage.
   echo "Usage: $command [OPTIONS]";
   if (!empty($files))
      echo ' '.implode(' ', $files);
   echo "\n";
   
   if ($onlyusage)
      return;
   
   $sections = getAllCommandLineOptions($options, true);
   
   foreach ($sections as $name => $options) {
      foreach ($options as $k => &$row) {
         $row['longname'] = $k;
      }
      uasort($options, function($a, $b) {
            $aval = val(CMDLINE_SHORT, $a, $a['longname']);
            $bval = val(CMDLINE_SHORT, $b, $b['longname']);
            
            return strcasecmp($aval, $bval);
         });
      
      
      echo "$name\n\n";
      foreach ($options as $longname => $options) {
         $flags = val(CMDLINE_FLAGS, $options);
         
         echo "  ";

         if (isset($options[CMDLINE_SHORT]))
            echo '-'.$options[CMDLINE_SHORT].', ';

         echo "--$longname";

         if (($flags & CMDLINE_REQUIRED) == 0) {
            $default = val(CMDLINE_DEFAULT, $options);
            if ($default)
               echo " (default $default)";
            else
               echo ' (optional)';
         }

         echo "\n    {$options[0]}\n";

         if ($valid = val('valid', $options)) {
            echo '    Valid Values: '.implode(', ', $valid)."\n";
         }

         echo "\n";
      }
   }
}
?>