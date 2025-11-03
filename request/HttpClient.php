<?php

// keep exception classes local to this file
class HttpException extends \RuntimeException {}
class HttpRequestException extends HttpException {}
class HttpResponseException extends HttpException {}

// Ensure the Response class is available from a single source file
require_once __DIR__ . '/Response.php';

class HttpClient
{
    private array $defaultOptions = [
        'timeout' => 10,           // seconds total
        'connect_timeout' => 3,    // seconds to connect
        'headers' => [],
        'retries' => 0,
        'backoff_factor' => 1,     // seconds base for exponential backoff
        'verify' => true,          // CA verify
        'follow_redirects' => false,
        'max_redirects' => 5,
    ];

    public function __construct(array $defaults = [])
    {
        $this->defaultOptions = array_merge($this->defaultOptions, $defaults);
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('curl extension is required');
        }
    }

    public function get(string $url, array $options = []): Response
    {
        return $this->request('GET', $url, $options);
    }

    public function post(string $url, array $options = []): Response
    {
        return $this->request('POST', $url, $options);
    }

    public function request(string $method, string $url, array $options = []): Response
    {
        $opts = array_merge($this->defaultOptions, $options);
        $attempt = 0;
        $retries = max(0, (int)$opts['retries']);

        do {
            $attempt++;
            $ch = curl_init();

            $headers = $this->formatHeaders($opts['headers'] ?? []);

            // prepare body
            $body = $opts['body'] ?? null;
            if (array_key_exists('json', $opts)) {
                $body = json_encode($opts['json']);
                $headers[] = 'Content-Type: application/json';
            } elseif (array_key_exists('form_params', $opts)) {
                $body = http_build_query($opts['form_params']);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }

            // query params
            if (!empty($opts['query']) && is_array($opts['query'])) {
                $sep = strpos($url, '?') === false ? '?' : '&';
                $url = $url . $sep . http_build_query($opts['query']);
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true); // we will split headers/body
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$opts['connect_timeout']);
            curl_setopt($ch, CURLOPT_TIMEOUT, (int)$opts['timeout']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, (bool)$opts['follow_redirects']);
            curl_setopt($ch, CURLOPT_MAXREDIRS, (int)$opts['max_redirects']);
            curl_setopt($ch, CURLOPT_FAILONERROR, false); // we want to inspect 4xx/5xx
            if (!$opts['verify']) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $errmsg = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            if ($raw === false || $errno !== 0) {
                // network / curl error
                $lastError = $errmsg ?: 'curl error ' . $errno;
                $shouldRetry = $attempt <= $retries;
                if ($shouldRetry) {
                    $this->sleepForAttempt($attempt, (float)$opts['backoff_factor']);
                    continue;
                }
                throw new HttpRequestException('HTTP request failed: ' . $lastError);
            }

            // split headers and body
            $headerSize = $info['header_size'] ?? 0;
            $rawHeaders = substr($raw, 0, $headerSize);
            $bodyStr = substr($raw, $headerSize);
            $status = (int)($info['http_code'] ?? 0);
            $headersArr = $this->parseRawHeaders($rawHeaders);

            // retry on 5xx by default (unless user changed behavior)
            if ($status >= 500 && $attempt <= $retries) {
                $this->sleepForAttempt($attempt, (float)$opts['backoff_factor']);
                continue;
            }

            return new Response($status, $headersArr, $bodyStr);
        } while ($attempt <= $retries);

        throw new HttpResponseException('Exceeded retries without receiving response');
    }

    private function formatHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            if (is_int($k)) {
                $out[] = (string)$v;
            } else {
                $out[] = $k . ': ' . $v;
            }
        }
        return $out;
    }

    private function parseRawHeaders(string $raw): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($raw));
        $headers = [];
        foreach ($lines as $line) {
            if (strpos($line, ':') === false) {
                // status line
                $headers[] = $line;
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if (!isset($headers[$k])) {
                $headers[$k] = $v;
            } else {
                if (is_array($headers[$k])) {
                    $headers[$k][] = $v;
                } else {
                    $headers[$k] = [$headers[$k], $v];
                }
            }
        }
        return $headers;
    }

    private function sleepForAttempt(int $attempt, float $factor): void
    {
        $delay = (int) ($factor * (2 ** ($attempt - 1)));
        // cap a reasonable maximum delay
        $delay = min($delay, 30);
        sleep($delay);
    }
}