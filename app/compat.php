<?php
/**
 * Polyfills so one codebase runs on PHP 5.4 (CentOS 7 production) and PHP 8.x
 * (XAMPP development). Everything is guarded, so newer PHP uses its natives.
 */

if (!function_exists('vec_random_bytes')) {
    /**
     * @param int $length
     * @return string raw binary
     */
    function vec_random_bytes($length)
    {
        $length = (int) $length;
        if ($length < 1) {
            $length = 1;
        }
        if (function_exists('random_bytes')) {
            try {
                return random_bytes($length);
            } catch (Exception $e) {
                // fall through
            }
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes($length, $strong);
            if ($bytes !== false) {
                return $bytes;
            }
        }
        if (function_exists('mcrypt_create_iv') && defined('MCRYPT_DEV_URANDOM')) {
            $bytes = @mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
            if ($bytes !== false && strlen($bytes) === $length) {
                return $bytes;
            }
        }
        if (@is_readable('/dev/urandom')) {
            $fh = @fopen('/dev/urandom', 'rb');
            if ($fh !== false) {
                $bytes = @fread($fh, $length);
                fclose($fh);
                if ($bytes !== false && strlen($bytes) === $length) {
                    return $bytes;
                }
            }
        }
        // Last resort. Weaker, but never leaves the caller without a value.
        $out = '';
        while (strlen($out) < $length) {
            $out .= pack('N', mt_rand()) . pack('N', mt_rand());
            $out .= md5(uniqid(mt_rand(), true), true);
        }
        return substr($out, 0, $length);
    }
}

if (!function_exists('vec_random_token')) {
    function vec_random_token($bytes = 16)
    {
        return bin2hex(vec_random_bytes($bytes));
    }
}

if (!function_exists('hash_equals')) {
    function hash_equals($known, $user)
    {
        $known = (string) $known;
        $user  = (string) $user;
        if (strlen($known) !== strlen($user)) {
            return false;
        }
        $diff = 0;
        $len = strlen($known);
        for ($i = 0; $i < $len; $i++) {
            $diff |= ord($known[$i]) ^ ord($user[$i]);
        }
        return $diff === 0;
    }
}

if (!defined('PASSWORD_BCRYPT')) {
    define('PASSWORD_BCRYPT', 1);
}
if (!defined('PASSWORD_DEFAULT')) {
    define('PASSWORD_DEFAULT', PASSWORD_BCRYPT);
}

if (!function_exists('password_hash')) {
    function password_hash($password, $algo = PASSWORD_DEFAULT, $options = array())
    {
        $cost = 10;
        if (is_array($options) && isset($options['cost'])) {
            $cost = (int) $options['cost'];
        }
        if ($cost < 4 || $cost > 31) {
            $cost = 10;
        }
        $raw = vec_random_bytes(16);
        $salt = substr(strtr(base64_encode($raw), '+', '.'), 0, 22);
        $prefix = sprintf('$2y$%02d$%s', $cost, $salt);
        $hash = crypt((string) $password, $prefix);
        if (!is_string($hash) || strlen($hash) !== 60) {
            return false;
        }
        return $hash;
    }
}

if (!function_exists('password_verify')) {
    function password_verify($password, $hash)
    {
        $hash = (string) $hash;
        if ($hash === '') {
            return false;
        }
        $computed = crypt((string) $password, $hash);
        if (!is_string($computed) || strlen($computed) < 13) {
            return false;
        }
        return hash_equals($hash, $computed);
    }
}

if (!function_exists('password_needs_rehash')) {
    function password_needs_rehash($hash, $algo = PASSWORD_DEFAULT, $options = array())
    {
        $cost = 10;
        if (is_array($options) && isset($options['cost'])) {
            $cost = (int) $options['cost'];
        }
        $hash = (string) $hash;
        if (strpos($hash, '$2y$') !== 0) {
            return true;
        }
        return ((int) substr($hash, 4, 2)) !== $cost;
    }
}

if (!function_exists('array_column')) {
    function array_column($input, $columnKey, $indexKey = null)
    {
        $out = array();
        if (!is_array($input)) {
            return $out;
        }
        foreach ($input as $row) {
            if (!is_array($row) && !is_object($row)) {
                continue;
            }
            $row = (array) $row;
            if ($columnKey === null) {
                $value = $row;
            } elseif (array_key_exists($columnKey, $row)) {
                $value = $row[$columnKey];
            } else {
                continue;
            }
            if ($indexKey !== null && array_key_exists($indexKey, $row)) {
                $out[$row[$indexKey]] = $value;
            } else {
                $out[] = $value;
            }
        }
        return $out;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        return strpos((string) $haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        return strncmp((string) $haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        $haystack = (string) $haystack;
        if (strlen($needle) > strlen($haystack)) {
            return false;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('json_last_error_msg')) {
    function json_last_error_msg()
    {
        return json_last_error() === JSON_ERROR_NONE ? 'No error' : 'JSON error';
    }
}

if (!defined('JSON_UNESCAPED_UNICODE')) {
    define('JSON_UNESCAPED_UNICODE', 256);
}
if (!defined('JSON_UNESCAPED_SLASHES')) {
    define('JSON_UNESCAPED_SLASHES', 64);
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null)
    {
        // Counts UTF-8 code points without the mbstring extension.
        return strlen(preg_replace('/[\x80-\xBF]/', '', (string) $string));
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null)
    {
        preg_match_all('/./us', (string) $string, $m);
        $chars = isset($m[0]) ? $m[0] : array();
        $slice = ($length === null)
            ? array_slice($chars, $start)
            : array_slice($chars, $start, $length);
        return implode('', $slice);
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string, $encoding = null)
    {
        return strtoupper((string) $string);
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null)
    {
        return strtolower((string) $string);
    }
}
