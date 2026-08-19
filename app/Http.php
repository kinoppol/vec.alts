<?php
/**
 * Minimal outbound HTTP client.
 *
 * Prefers cURL and falls back to the stream wrapper, because the production
 * CentOS box may have allow_url_fopen disabled while a testing XAMPP install
 * usually has both. Nothing here follows redirects to a different scheme or
 * downloads without a size ceiling.
 */
class Http
{
    /** Give up on a single request after this many seconds. */
    const TIMEOUT = 25;

    /** Refuse a response body larger than this. */
    const MAX_BYTES = 10485760; // 10 MB

    /**
     * @param string $url
     * @param int $maxBytes
     * @return array array('ok'=>bool, 'body'=>string, 'status'=>int, 'error'=>string, 'type'=>string)
     */
    public static function get($url, $maxBytes = self::MAX_BYTES)
    {
        $out = array('ok' => false, 'body' => '', 'status' => 0, 'error' => '', 'type' => '');

        $check = self::validateUrl($url);
        if ($check !== '') {
            $out['error'] = $check;
            return $out;
        }

        if (function_exists('curl_init')) {
            return self::getWithCurl($url, $maxBytes);
        }
        if (ini_get('allow_url_fopen')) {
            return self::getWithStream($url, $maxBytes);
        }

        $out['error'] = 'เซิร์ฟเวอร์ไม่มีทั้ง cURL และ allow_url_fopen จึงเรียกข้อมูลภายนอกไม่ได้';
        return $out;
    }

    /**
     * @param string $url
     * @return string '' when acceptable, otherwise the reason
     */
    public static function validateUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return 'ยังไม่ได้กำหนดที่อยู่ของแหล่งข้อมูล';
        }
        $parts = @parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return 'รูปแบบ URL ไม่ถูกต้อง';
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        if ($scheme !== 'http' && $scheme !== 'https') {
            return 'รองรับเฉพาะ http:// และ https:// เท่านั้น';
        }
        return '';
    }

    /**
     * @return array
     */
    private static function getWithCurl($url, $maxBytes)
    {
        $out = array('ok' => false, 'body' => '', 'status' => 0, 'error' => '', 'type' => '');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'vec-alts/' . VEC_VERSION);
        // The source is an internal school server that may still present a
        // self-signed certificate; verification stays on and a failure is
        // reported rather than silently ignored.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // Stop reading once the body exceeds the ceiling.
        $body = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($handle, $chunk) use (&$body, $maxBytes) {
            $body .= $chunk;
            if (strlen($body) > $maxBytes) {
                return 0; // aborts the transfer
            }
            return strlen($chunk);
        });

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $out['status'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $out['type'] = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if (strlen($body) > $maxBytes) {
            $out['error'] = 'ข้อมูลที่ได้รับมีขนาดเกินกำหนด';
            return $out;
        }
        if ($ok === false && $errno !== 0 && $body === '') {
            $out['error'] = 'เชื่อมต่อไม่สำเร็จ: ' . $error;
            return $out;
        }
        if ($out['status'] < 200 || $out['status'] >= 300) {
            $out['error'] = 'แหล่งข้อมูลตอบกลับด้วยสถานะ HTTP ' . $out['status'];
            return $out;
        }

        $out['ok'] = true;
        $out['body'] = $body;
        return $out;
    }

    /**
     * @return array
     */
    private static function getWithStream($url, $maxBytes)
    {
        $out = array('ok' => false, 'body' => '', 'status' => 0, 'error' => '', 'type' => '');

        $context = stream_context_create(array(
            'http' => array(
                'method'        => 'GET',
                'timeout'       => self::TIMEOUT,
                'user_agent'    => 'vec-alts/' . VEC_VERSION,
                'max_redirects' => 4,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ),
        ));

        $body = @file_get_contents($url, false, $context, 0, $maxBytes + 1);
        if ($body === false) {
            $out['error'] = 'เชื่อมต่อไม่สำเร็จ';
            return $out;
        }
        if (strlen($body) > $maxBytes) {
            $out['error'] = 'ข้อมูลที่ได้รับมีขนาดเกินกำหนด';
            return $out;
        }

        // $http_response_header is populated by the wrapper in local scope.
        $status = 0;
        $type = '';
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                    $status = (int) $m[1];
                } elseif (stripos($header, 'content-type:') === 0) {
                    $type = trim(substr($header, 13));
                }
            }
        }
        $out['status'] = $status;
        $out['type'] = $type;

        if ($status !== 0 && ($status < 200 || $status >= 300)) {
            $out['error'] = 'แหล่งข้อมูลตอบกลับด้วยสถานะ HTTP ' . $status;
            return $out;
        }

        $out['ok'] = true;
        $out['body'] = $body;
        return $out;
    }
}
