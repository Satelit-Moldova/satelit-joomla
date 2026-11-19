<?php

use PHPUnit\Framework\TestCase;
use Satelit\Joomla\Api;
use Satelit\Joomla\MemoryStore;

/**
 * Opt-in live smoke test: SATELIT_API_KEY=sat_live_... vendor/bin/phpunit --group live
 * Costs 2 credits. Skipped without a key.
 */
final class LiveTest extends TestCase
{
    /**
     * @group live
     */
    public function testRatesAndFuelFromTheLiveApi()
    {
        $key = getenv('SATELIT_API_KEY');

        if (!$key) {
            $this->markTestSkipped('SATELIT_API_KEY not set');
        }

        $curl = function ($url, array $headers, $timeout) {
            $h = array();

            foreach ($headers as $k => $v) {
                $h[] = $k . ': ' . $v;
            }

            $c = curl_init($url);
            curl_setopt_array($c, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => $timeout));
            $body = curl_exec($c);
            $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
            curl_close($c);

            return array($code, (string) $body, array());
        };

        $api   = new Api($curl, new MemoryStore(), $key, getenv('SATELIT_API_BASE') ?: '', 'ro');
        $rates = $api->get('/rates', array(), 60);

        $this->assertNotNull($rates, json_encode($api->lastStatus()));
        $this->assertSame('Sursa: Banca Națională a Moldovei, bnm.md, CC BY 4.0', $rates['meta']['attribution']);

        $fuel = $api->get('/fuel', array(), 60);
        $this->assertArrayHasKey('a95', $fuel['data']['range']);
    }
}
