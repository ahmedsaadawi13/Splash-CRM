<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;

class ValidationService
{
    /**
     * Validate request data
     *
     * @param array $data The data to validate
     * @param array $rules Validation rules
     * @return array Array of errors (empty if valid)
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleArray = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;

            foreach ($ruleArray as $rule) {
                $error = $this->validateField($field, $data[$field] ?? null, $rule, $data);

                if ($error) {
                    if (!isset($errors[$field])) {
                        $errors[$field] = [];
                    }
                    $errors[$field][] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a single field
     */
    private function validateField(string $field, $value, string $rule, array $allData): ?string
    {
        // Parse rule and parameters
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $parameters = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    return ucfirst(str_replace('_', ' ', $field)) . ' is required';
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return 'Invalid email format';
                }
                break;

            case 'min':
                $min = $parameters[0];
                if (!empty($value) && strlen($value) < $min) {
                    return ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters";
                }
                break;

            case 'max':
                $max = $parameters[0];
                if (!empty($value) && strlen($value) > $max) {
                    return ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$max} characters";
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    return ucfirst(str_replace('_', ' ', $field)) . ' must be a number';
                }
                break;

            case 'in':
                if (!empty($value) && !in_array($value, $parameters)) {
                    return ucfirst(str_replace('_', ' ', $field)) . ' must be one of: ' . implode(', ', $parameters);
                }
                break;

            case 'unique':
                if (!empty($value)) {
                    $table = $parameters[0];
                    $column = $parameters[1] ?? $field;
                    $exceptId = $parameters[2] ?? null;

                    $query = DB::table($table)->where($column, $value);

                    if ($exceptId) {
                        $query->where('id', '!=', $exceptId);
                    }

                    if ($query->exists()) {
                        return ucfirst(str_replace('_', ' ', $field)) . ' already exists';
                    }
                }
                break;

            case 'exists':
                if (!empty($value)) {
                    $table = $parameters[0];
                    $column = $parameters[1] ?? 'id';

                    if (!DB::table($table)->where($column, $value)->exists()) {
                        return ucfirst(str_replace('_', ' ', $field)) . ' does not exist';
                    }
                }
                break;

            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    return ucfirst(str_replace('_', ' ', $field)) . ' must be a valid date';
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return 'Invalid URL format';
                }
                break;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if ($value !== ($allData[$confirmField] ?? null)) {
                    return ucfirst(str_replace('_', ' ', $field)) . ' confirmation does not match';
                }
                break;
        }

        return null;
    }

    /**
     * Check if validation passed
     */
    public function passes(array $errors): bool
    {
        return empty($errors);
    }

    /**
     * Format errors for JSON response
     */
    public function formatErrors(array $errors): array
    {
        $formatted = [];

        foreach ($errors as $field => $fieldErrors) {
            $formatted[$field] = is_array($fieldErrors) ? $fieldErrors[0] : $fieldErrors;
        }

        return $formatted;
    }
}
