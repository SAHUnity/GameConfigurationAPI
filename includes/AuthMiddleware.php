<?php

/**
 * Authentication middleware for admin panels
 */
class AuthMiddleware
{
    private $auth;
    private $security;

    public function __construct($pdo)
    {
        $this->security = new SecurityMiddleware($pdo);
        $this->auth = new Auth($pdo, $this->security);
    }

    /**
     * Require admin authentication
     * @return array Admin user data
     */
    public function requireAuth()
    {
        if (!$this->auth->validateAdminSession()) {
            header('Location: index.php');
            exit;
        }

        return $this->auth->getCurrentAdmin();
    }

    /**
     * Validate and sanitize configuration input
     * @param array $input Input data
     * @param array $rules Validation rules
     * @return array [validated, errors]
     */
    public function validateConfigInput($input, $rules)
    {
        $validated = [];
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? null;

            if ($rule['required'] && ($value === null || $value === '')) {
                $errors[$field] = ucfirst($field) . ' is required';
                continue;
            }

            if ($value !== null && $value !== '') {
                $type = $rule['type'] ?? 'string';
                $maxLength = $rule['max_length'] ?? 1000;

                $sanitized = $this->security->validateInput($value, $type, $maxLength);

                if ($sanitized === false) {
                    $errors[$field] = 'Invalid ' . $field . ' format';
                } else {
                    $validated[$field] = $sanitized;
                }
            } elseif (!$rule['required']) {
                $validated[$field] = $rule['default'] ?? null;
            }
        }

        return [$validated, $errors];
    }

    /**
     * Validate configuration key and value
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @param string $type Data type
     * @return array [isValid, error]
     */
    public function validateConfigKeyValue($key, $value, $type)
    {
        if (!$this->security->validateConfigKey($key)) {
            return [false, 'Invalid configuration key format'];
        }

        if (!$this->security->validateConfigValue($value, $type)) {
            return [false, 'Invalid configuration value for type ' . $type];
        }

        return [true, null];
    }
}
