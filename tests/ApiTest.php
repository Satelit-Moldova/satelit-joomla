<?php

use PHPUnit\Framework\TestCase;
use Satelit\Joomla\Api;
use Satelit\Joomla\MemoryStore;

final class ApiTest extends TestCase
{
    private $now = 1795000000;

    private function api(FakeHttp $http, MemoryStore $cache, $key = 'sat_live_test', array &$log = null)
    {
        $log = array();
        $now = &$this->now;

        return new Api($http, $cache, $key, '', 'ro', function ($line) use (&$log) {
            $log[] = $line;
        }, function () use (&$now) {
            return $now;
        });
    }

    public function testNoKeyMakesNoCall()
    {
        $http = new FakeHttp();
        $api  = $this->api($http, new MemoryStore(), '');

        $this->assertNull($api->get('/rates', array(), 60));
        $this->assertCount(0, $http->calls);
        $this->assertSame('no_key', $api->lastStatus()['code']);
    }

    public function testCallsOnceThenServesTheCache()
    {
        $http                    = new FakeHttp();
        $http->answers['/rates'] = array(200, FakeHttp::fixture('rates'), array());
        $api                     = $this->api($http, new MemoryStore());

        $a = $api->get('/rates', array(), 3600);
        $b = $api->get('/rates', array(), 3600);

        $this->assertSame('EUR', $a['data'][0]['code']);
        $this->assertSame($a, $b);
        $this->assertCount(1, $http->calls);
        $this->assertSame('Bearer sat_live_test', $http->calls[0]['headers']['Authorization']);
        $this->assertStringNotContainsString('sat_live_test', $http->calls[0]['url'], 'the key never goes in a URL');
        $this->assertStringContainsString('lang=ro', $http->calls[0]['url']);
        $this->assertLessThanOrEqual(10, $http->calls[0]['timeout']);
    }

    public function testExpiredCopyIsRefreshed()
    {
        $http                    = new FakeHttp();
        $http->answers['/rates'] = array(200, FakeHttp::fixture('rates'), array());
        $api                     = $this->api($http, new MemoryStore());

        $api->get('/rates', array(), 60);
        $this->now += 61;
        $api->get('/rates', array(), 60);

        $this->assertCount(2, $http->calls);
    }

    public function testBadKeyServesTheSavedCopyAndPauses()
    {
        $http                    = new FakeHttp();
        $cache                   = new MemoryStore();
        $http->answers['/rates'] = array(200, FakeHttp::fixture('rates'), array());
        $log                     = null;
        $api                     = $this->api($http, $cache, 'sat_live_test', $log);
        $api->get('/rates', array(), 60);

        $this->now += 120;
        $http->answers['/rates'] = array(401, '{"error":{"code":"key_invalid","message":"x","docs":"y"}}', array());
        $res = $api->get('/rates', array(), 60);

        $this->assertSame('EUR', $res['data'][0]['code'], 'the last good copy is served');
        $this->assertSame('key_invalid', $api->lastStatus()['code']);
        $this->assertCount(1, $log);
        $this->assertStringContainsString('key_invalid', $log[0]);
        $this->assertStringNotContainsString('sat_live_test', $log[0], 'the key never goes in the log');

        // Paused: the next page view makes no call.
        $api->get('/rates', array(), 60);
        $this->assertCount(2, $http->calls);

        $this->now += Api::PAUSE + 1;
        $api->get('/rates', array(), 60);
        $this->assertCount(3, $http->calls);
    }

    public function testDeadApiReturnsNullWithoutThrowing()
    {
        $http        = new FakeHttp();
        $http->throw = true;
        $api         = $this->api($http, new MemoryStore());

        $this->assertNull($api->get('/fuel', array(), 60));
        $this->assertSame('network', $api->lastStatus()['code']);
    }

    public function testRetryAfterIsHonoured()
    {
        $http                   = new FakeHttp();
        $cache                  = new MemoryStore();
        $http->answers['/news'] = array(429, '{"error":{"code":"rate_limited"}}', array('Retry-After' => '120'));
        $api                    = $this->api($http, $cache);

        $api->get('/news', array('per' => 5), 60);
        $this->assertSame($this->now + 120, $cache->get('pause'));
    }

    public function testMeReportsTheCode()
    {
        $http                 = new FakeHttp();
        $http->answers['/me'] = array(401, '{"error":{"code":"key_invalid"}}', array());
        $api                  = $this->api($http, new MemoryStore());

        $this->assertSame(array('ok' => false, 'code' => 'key_invalid', 'data' => null), $api->me());
    }
}
