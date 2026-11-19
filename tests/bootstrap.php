<?php
/**
 * Offline tests: the pure classes only (no Joomla, no network).
 */

\define('SATELIT_TEST', true);

$lib = \dirname(__DIR__) . '/src/lib_satelit/src';

require_once $lib . '/CacheStore.php';
require_once $lib . '/Api.php';
require_once $lib . '/Render.php';
require_once $lib . '/Blocks.php';

/** A transport that answers from fixtures and records each call. */
final class FakeHttp
{
    public $calls = array();

    /** @var array  path => [code, body, headers] */
    public $answers = array();

    public $throw = false;

    public function __invoke($url, array $headers, $timeout)
    {
        $this->calls[] = array('url' => $url, 'headers' => $headers, 'timeout' => $timeout);

        if ($this->throw) {
            throw new \RuntimeException('connection refused');
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = substr($path, strlen('/v1'));

        return isset($this->answers[$path]) ? $this->answers[$path] : array(404, '{"error":{"code":"not_found"}}', array());
    }

    public static function fixture($name)
    {
        return file_get_contents(__DIR__ . '/fixtures/' . $name . '.json');
    }
}

/** English labels, as in en-GB.lib_satelit.ini. */
function satelit_test_labels($decimal = '.')
{
    $ini = parse_ini_file(\dirname(__DIR__) . '/src/lib_satelit/language/en-GB/en-GB.lib_satelit.ini');

    return function ($key) use ($ini, $decimal) {
        if ($key === 'DECIMAL') {
            return $decimal;
        }

        return isset($ini['LIB_SATELIT_' . $key]) ? $ini['LIB_SATELIT_' . $key] : $key;
    };
}
