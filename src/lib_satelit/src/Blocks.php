<?php
/**
 * One place that knows which route each block reads, with which cache time,
 * and the {satelit ...} inline tags. Pure PHP, tested offline.
 *
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

namespace Satelit\Joomla;

\defined('_JEXEC') or \defined('SATELIT_TEST') or die;

final class Blocks
{
    const KINDS = 'rates,fuel,news,cinema,joke,law';

    // Seconds a copy counts as fresh. BNM publishes once a day, ANRE fuel caps
    // daily; news moves fast; a cinema day costs 40 credits a call.
    const TTL = array(
        'rates'  => 10800, // 3 h, 1 credit
        'fuel'   => 7200,  // 2 h, 1 credit
        'news'   => 900,   // 15 min, 2 credits
        'cinema' => 10800, // 3 h, 40 credits
        'joke'   => 21600, // 6 h, 1 credit (one joke per day)
        'law'    => 21600, // 6 h, 2 credits
    );

    /** @var Api */
    private $api;

    /** @var Render */
    private $render;

    public function __construct(Api $api, Render $render)
    {
        $this->api    = $api;
        $this->render = $render;
    }

    /**
     * HTML of one block, or '' when there is nothing to show (no key, API down
     * with no saved copy, unknown kind).
     *
     * @param   string  $kind  rates|fuel|news|cinema|joke|law
     * @param   string  $arg   rates: codes "EUR,USD"; news/law: a count; cinema: a city
     * @param   array   $opt   heading (bool), posters (bool), networks (int), city, codes, count
     */
    public function html($kind, $arg = '', array $opt = array())
    {
        $kind    = strtolower(trim((string) $kind));
        $arg     = trim((string) $arg);
        $heading = !empty($opt['heading']);

        switch ($kind) {
            case 'rates':
                $codes = $arg !== '' ? $arg : (isset($opt['codes']) ? (string) $opt['codes'] : 'EUR,USD,RON');
                $res   = $this->api->get('/rates', array(), self::TTL['rates']);

                return $res ? $this->render->rates($res, preg_split('/[\s,;]+/', $codes, -1, PREG_SPLIT_NO_EMPTY), $heading) : '';

            case 'fuel':
                $res = $this->api->get('/fuel', array(), self::TTL['fuel']);

                return $res ? $this->render->fuel($res, isset($opt['networks']) ? (int) $opt['networks'] : 5, $heading) : '';

            case 'news':
                $per = $this->count($arg, $opt, 5);
                $q   = array('per' => $per);

                if (!empty($opt['category']) && preg_match('/^[a-z_]{2,30}$/', $opt['category'])) {
                    $q['category'] = $opt['category'];
                }

                $res = $this->api->get('/news', $q, self::TTL['news']);

                return $res ? $this->render->news($res, $heading) : '';

            case 'cinema':
                $city = $arg !== '' ? $arg : (isset($opt['city']) ? trim((string) $opt['city']) : '');
                $q    = array('country' => 'MD');

                if ($city !== '') {
                    $q['city'] = mb_substr($city, 0, 60);
                }

                $res = $this->api->get('/movies/cinemas/today', $q, self::TTL['cinema']);

                return $res ? $this->render->cinema($res, !isset($opt['posters']) || $opt['posters'], $heading) : '';

            case 'joke':
                $res = $this->api->get('/jokes/today', array(), self::TTL['joke']);

                return $res ? $this->render->joke($res, $heading) : '';

            case 'law':
                $res = $this->api->get('/law/acts', array('important' => 'true'), self::TTL['law']);

                return $res ? $this->render->law($res, $this->count($arg, $opt, 5), $heading) : '';
        }

        return '';
    }

    /**
     * Replace every {satelit kind args} in an article's HTML.
     * A tag alone in a paragraph replaces the whole <p>, so no block lands
     * inside a <p>. Unknown kinds are left as typed, so an editor sees the typo.
     */
    public function replaceTags($text)
    {
        if (strpos($text, '{satelit') === false) {
            return $text;
        }

        $text = preg_replace('#<p[^>]*>\s*(\{satelit\s[^}]*\})\s*</p>#iu', '$1', $text);
        $self = $this;

        return preg_replace_callback('#\{satelit\s+([a-z]+)(?:\s+([^}]*))?\}#iu', function ($m) use ($self) {
            $kind = strtolower($m[1]);

            if (!\in_array($kind, explode(',', Blocks::KINDS), true)) {
                return $m[0];
            }

            // An editor may have typed &nbsp; or entities in the argument.
            $arg = isset($m[2]) ? html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8') : '';
            $arg = trim(str_replace("\xC2\xA0", ' ', $arg));

            return $self->html($kind, $arg, array('heading' => true));
        }, $text);
    }

    private function count($arg, array $opt, $default)
    {
        $n = $arg !== '' ? (int) $arg : (isset($opt['count']) ? (int) $opt['count'] : $default);

        return max(1, min(20, $n ?: $default));
    }
}
