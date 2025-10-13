<?php

/**
 * Utility functions for Game Configuration API
 */

/**
 * Determine data type of value
 * @param mixed $value Value to check
 * @return string Data type
 */
function determineDataType($value)
{
    if (is_bool($value)) {
        return 'boolean';
    } elseif (is_numeric($value)) {
        return is_float($value) ? 'float' : 'number';
    } elseif (is_array($value)) {
        return 'array';
    } elseif (is_object($value)) {
        return 'object';
    } else {
        return 'string';
    }
}

/**
 * Process configuration value based on data type
 * @param string $value Value to process
 * @param string $dataType Target data type
 * @return mixed Processed value
 */
function processConfigValue($value, $dataType)
{
    switch ($dataType) {
        case 'boolean':
            return $value === 'true' || $value === '1';
        case 'number':
            return is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : 0;
        case 'array':
        case 'object':
            return json_decode($value, true) ?: [];
        default:
            return (string)$value;
    }
}
