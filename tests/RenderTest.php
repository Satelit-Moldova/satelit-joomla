<?php

use PHPUnit\Framework\TestCase;
use Satelit\Joomla\Api;
use Satelit\Joomla\Blocks;
use Satelit\Joomla\MemoryStore;
use Satelit\Joomla\Render;

final class RenderTest extends TestCase
{
    /** 12:00 in Chișinău on the fixtures' day (UTC+2 in November). */
    private $noon;

    protected function setUp(): void
    {
        $this->noon = strtotime('2026-11-19 10:00:00 UTC');
    }

    private function res($name)
    {
        $j = json_decode(FakeHttp::fixture($name), true);

        return array('data' => $j['data'], 'meta' => $j['meta']);
    }

    private function blocks(FakeHttp $http, $decimal = '.')
    {
        $api = new Api($http, new MemoryStore(), 'sat_live_test');

        return new Blocks($api, new Render(satelit_test_labels($decimal), $this->noon));
    }

    public function testRatesShowTheChosenCodesAndBnmAttribution()
    {
        $r    = new Render(satelit_test_labels(','), $this->noon);
        $html = $r->rates($this->res('rates'), array('usd', 'EUR'), true);

        $this->assertStringContainsString('20,1411', $html);
        $this->assertStringContainsString('18,3744', $html);
        $this->assertStringNotContainsString('RON', $html);
        $this->assertLessThan(strpos($html, '>EUR<'), strpos($html, '>USD<'), 'codes keep the given order');
        $this->assertStringContainsString('Sursa: Banca Națională a Moldovei, bnm.md, CC BY 4.0', $html);
        $this->assertStringContainsString('Data: Satelit', $html);
        $this->assertStringContainsString('19.11.2026', $html);
    }

    public function testFuelListsRangesAndCheapestNetworks()
    {
        $r    = new Render(satelit_test_labels(), $this->noon);
        $html = $r->fuel($this->res('fuel'), 3);
        $d    = $this->res('fuel')['data'];

        $this->assertStringContainsString(number_format($d['range']['a95']['min'], 2), $html);
        $this->assertStringContainsString(number_format($d['cap']['diesel'], 2), $html);
        $this->assertSame(3, substr_count($html, '<li>'));
    }

    public function testNewsEscapesTitlesAndDropsBadLinks()
    {
        $res                               = $this->res('news');
        $res['data'][0]['title']           = '<script>alert(1)</script> & co';
        $res['data'][0]['sources'][0]['url'] = 'javascript:alert(1)';
        $html                              = (new Render(satelit_test_labels(), $this->noon))->news($res);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; co', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString(htmlspecialchars($res['data'][1]['title'], ENT_QUOTES, 'UTF-8'), $html);
    }

    public function testCinemaHidesSessionsAlreadyStarted()
    {
        $res  = $this->res('cinema');
        $html = (new Render(satelit_test_labels(), $this->noon))->cinema($res);

        $this->assertStringContainsString('Cineplex', $html);
        $this->assertStringNotContainsString('>10:10<', $html);

        $later = array();

        foreach ($res['data']['cinemas'][0]['films'] as $f) {
            foreach ($f['sessions'] as $s) {
                if ($s['time'] >= '12:00') {
                    $later[] = $s['time'];
                }
            }
        }

        $this->assertNotEmpty($later);
        $this->assertStringContainsString('>' . $later[0], $html);
        $this->assertStringContainsString('https://api.satelit.md/v1/movies/img/s/', $html);
    }

    public function testJokeAndLaw()
    {
        $r = new Render(satelit_test_labels(), $this->noon);

        $joke = $r->joke($this->res('joke'));
        $this->assertStringContainsString(htmlspecialchars($this->res('joke')['data']['title'], ENT_QUOTES, 'UTF-8'), $joke);

        $law = $r->law($this->res('law'), 2);
        $this->assertSame(2, substr_count($law, '<li>'));
        $this->assertStringContainsString('https://monitorul.gov.md/', $law);
    }

    public function testTagsAreReplaced()
    {
        $http                    = new FakeHttp();
        $http->answers['/rates'] = array(200, FakeHttp::fixture('rates'), array());
        $http->answers['/jokes/today'] = array(200, FakeHttp::fixture('joke'), array());

        $out = $this->blocks($http)->replaceTags('<p>Intro</p><p>{satelit rates EUR,&nbsp;USD}</p><p>{satelit joke}</p><p>{satelit nonsense}</p>');

        $this->assertStringNotContainsString('{satelit rates', $out);
        $this->assertStringNotContainsString('<p><div', $out, 'a block never lands inside a paragraph');
        $this->assertStringContainsString('20.1411', $out);
        $this->assertStringContainsString('18.3744', $out);
        $this->assertStringContainsString('satelit--joke', $out);
        $this->assertStringContainsString('{satelit nonsense}', $out, 'unknown kinds stay as typed');
    }

    public function testNoKeyRendersNothing()
    {
        $api    = new Api(new FakeHttp(), new MemoryStore(), '');
        $blocks = new Blocks($api, new Render(satelit_test_labels()));

        $this->assertSame('', $blocks->html('rates'));
        $this->assertSame('<p>x</p>', $blocks->replaceTags('<p>x</p><p>{satelit fuel}</p>'), 'the tag disappears, the page stays');
    }

    public function testNewsCountIsCapped()
    {
        $http                   = new FakeHttp();
        $http->answers['/news'] = array(200, FakeHttp::fixture('news'), array());
        $this->blocks($http)->html('news', '500');

        $this->assertStringContainsString('per=20', $http->calls[0]['url']);
    }
}
