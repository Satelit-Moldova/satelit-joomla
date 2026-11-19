<?php
/**
 * Satelit API client: cached GET calls that never throw.
 *
 * Pure PHP (no Joomla classes), so it runs the same on Joomla 3.10, 4 and 5
 * and in the offline tests. The HTTP call and the cache are injected.
 *
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

namespace Satelit\Joomla;

\defined('_JEXEC') or \defined('SATELIT_TEST') or die;

final class Api
{
    const VERSION      = '1.0.0';
    const DEFAULT_BASE = 'https://api.satelit.md/v1';
    const TIMEOUT      = 6;      // seconds, per call
    const KEEP         = 604800; // a copy is kept 7 days, served when the API is down
    const PAUSE        = 300;    // after a failure, no new call for 5 minutes

    /** @var callable  function (string $url, array $headers, int $timeout): array [code, body, headers] */
    private $transport;

    /** @var CacheStore */
    private $cache;

    /** @var string */
    private $key;

    /** @var string */
    private $base;

    /** @var string */
    private $lang;

    /** @var callable|null  function (string $line): void */
    private $log;

    /** @var callable  function (): int */
    private $clock;

    public function __construct(callable $transport, CacheStore $cache, $key, $base = '', $lang = 'ro', $log = null, $clock = null)
    {
        $this->transport = $transport;
        $this->cache     = $cache;
        $this->key       = trim((string) $key);
        $this->base      = rtrim($base !== '' ? (string) $base : self::DEFAULT_BASE, '/');
        $this->lang      = \in_array($lang, array('ro', 'ru', 'en', 'uk'), true) ? $lang : 'ro';
        $this->log       = $log;
        $this->clock     = $clock ?: 'time';
    }

    public function hasKey()
    {
        return $this->key !== '';
    }

    /**
     * GET a route. Returns ['data' => ..., 'meta' => [...]] or null.
     *
     * A fresh cached copy is served without a call. On any failure the last
     * good copy (up to 7 days old) is served, and calls pause for a while so
     * a dead API never slows every page view by the timeout.
     *
     * @param   string  $path   route under /v1, e.g. '/rates'
     * @param   array   $query  query parameters
     * @param   int     $ttl    seconds a copy counts as fresh
     *
     * @return  array|null
     */
    public function get($path, array $query = array(), $ttl = 3600)
    {
        $now          = (int) \call_user_func($this->clock);
        $query['lang'] = $this->lang;
        ksort($query);
        $rel = $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $url = $this->base . $rel;
        // Keyed on the route, not the host: a copy stays usable if the address changes.
        $id = 'r_' . md5($rel);

        $hit = $this->cache->get($id);
        $hit = (\is_array($hit) && isset($hit['exp'], $hit['v'])) ? $hit : null;

        if ($hit && $hit['exp'] > $now) {
            return $hit['v'];
        }

        $old = $hit ? $hit['v'] : null;

        if ($this->key === '') {
            $this->status('no_key', 0, $now);

            return $old;
        }

        $pause = $this->cache->get('pause');

        if (\is_int($pause) && $pause > $now) {
            return $old;
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $this->key,
            'Accept'        => 'application/json',
            'User-Agent'    => 'satelit-joomla/' . self::VERSION,
        );

        $code = 0;
        $body = '';
        $resp = array();

        try {
            $res  = \call_user_func($this->transport, $url, $headers, self::TIMEOUT);
            $code = isset($res[0]) ? (int) $res[0] : 0;
            $body = isset($res[1]) ? (string) $res[1] : '';
            $resp = isset($res[2]) && \is_array($res[2]) ? array_change_key_case($res[2], CASE_LOWER) : array();
        } catch (\Exception $e) {
            $code = 0;
        } catch (\Throwable $e) {
            $code = 0;
        }

        $json = json_decode($body, true);

        if ($code === 200 && \is_array($json) && array_key_exists('data', $json)) {
            $value = array(
                'data' => $json['data'],
                'meta' => isset($json['meta']) && \is_array($json['meta']) ? $json['meta'] : array(),
            );
            $this->cache->set($id, array('exp' => $now + (int) $ttl, 'v' => $value), self::KEEP);
            $this->status('ok', 200, $now);

            return $value;
        }

        $error = isset($json['error']['code']) && \is_string($json['error']['code'])
            ? $json['error']['code']
            : ($code ? 'http_' . $code : 'network');

        // Wait what the API asks for (429, 503), else the default pause.
        $wait = isset($resp['retry-after']) && is_numeric($resp['retry-after'])
            ? max(30, min(3600, (int) $resp['retry-after']))
            : self::PAUSE;
        $this->cache->set('pause', $now + $wait, $wait);
        $this->status($error, $code, $now);

        if ($this->log) {
            \call_user_func($this->log, sprintf('Satelit %s: %s (HTTP %d)%s', $path, $error, $code, $old ? ', saved copy shown' : ''));
        }

        return $old;
    }

    /**
     * The key's plan and credits (GET /me, 0 credits). Not cached; admin only.
     *
     * @return  array  ['ok' => bool, 'code' => string, 'data' => array|null]
     */
    public function me()
    {
        if ($this->key === '') {
            return array('ok' => false, 'code' => 'no_key', 'data' => null);
        }

        try {
            $res = \call_user_func($this->transport, $this->base . '/me', array(
                'Authorization' => 'Bearer ' . $this->key,
                'Accept'        => 'application/json',
                'User-Agent'    => 'satelit-joomla/' . self::VERSION,
            ), self::TIMEOUT);
        } catch (\Exception $e) {
            return array('ok' => false, 'code' => 'network', 'data' => null);
        } catch (\Throwable $e) {
            return array('ok' => false, 'code' => 'network', 'data' => null);
        }

        $json = json_decode(isset($res[1]) ? (string) $res[1] : '', true);

        if ((int) $res[0] === 200 && isset($json['data'])) {
            return array('ok' => true, 'code' => 'ok', 'data' => $json['data']);
        }

        $code = isset($json['error']['code']) ? (string) $json['error']['code'] : ((int) $res[0] ? 'http_' . (int) $res[0] : 'network');

        return array('ok' => false, 'code' => $code, 'data' => null);
    }

    /**
     * The last call's outcome, for the settings page.
     *
     * @return  array|null  ['code' => string, 'http' => int, 'at' => int]
     */
    public function lastStatus()
    {
        $s = $this->cache->get('status');

        return \is_array($s) ? $s : null;
    }

    private function status($code, $http, $now)
    {
        $last = $this->cache->get('status');

        // Same outcome written less than a minute ago: skip the write.
        if (\is_array($last) && $last['code'] === $code && $now - (int) $last['at'] < 60) {
            return;
        }

        $this->cache->set('status', array('code' => $code, 'http' => $http, 'at' => $now), self::KEEP);
    }
}
