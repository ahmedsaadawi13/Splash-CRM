<?php

if (!function_exists('env')) {
    /**
     * Get environment variable value
     */
    function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        // Convert string boolean to actual boolean
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config($key, $default = null)
    {
        static $config = [];

        if (empty($config)) {
            $configPath = __DIR__ . '/../config';
            foreach (glob($configPath . '/*.php') as $file) {
                $name = basename($file, '.php');
                $config[$name] = require $file;
            }
        }

        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}

if (!function_exists('base_path')) {
    /**
     * Get base path
     */
    function base_path($path = '')
    {
        return __DIR__ . '/../' . ($path ? '/' . $path : '');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get storage path
     */
    function storage_path($path = '')
    {
        return base_path('storage') . ($path ? '/' . $path : '');
    }
}

if (!function_exists('public_path')) {
    /**
     * Get public path
     */
    function public_path($path = '')
    {
        return base_path('public') . ($path ? '/' . $path : '');
    }
}

if (!function_exists('now')) {
    /**
     * Get current datetime
     */
    function now()
    {
        return new DateTime();
    }
}

if (!function_exists('uuid')) {
    /**
     * Generate UUID
     */
    function uuid()
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
}

if (!function_exists('response_json')) {
    /**
     * Create JSON response
     */
    function response_json($data, $status = 200)
    {
        return [
            'data' => $data,
            'status' => $status,
        ];
    }
}

if (!function_exists('response_error')) {
    /**
     * Create error response
     */
    function response_error($message, $status = 400, $errors = [])
    {
        return [
            'error' => [
                'message' => $message,
                'errors' => $errors,
            ],
            'status' => $status,
        ];
    }
}

if (!function_exists('hash_password')) {
    /**
     * Hash password
     */
    function hash_password($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}

if (!function_exists('verify_password')) {
    /**
     * Verify password
     */
    function verify_password($password, $hash)
    {
        return password_verify($password, $hash);
    }
}
