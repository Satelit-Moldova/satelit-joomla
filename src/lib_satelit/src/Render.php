<?php
/**
 * HTML for each kind of block. Pure PHP: every value from the API is escaped
 * here, labels come from the injected translator.
 *
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

namespace Satelit\Joomla;

\defined('_JEXEC') or \defined('SATELIT_TEST') or die;

final class Render
{
    /** @var callable  function (string $key): string, key without the LIB_SATELIT_ prefix */
    private $t;

    /** @var int  "now" as a Unix time, to hide cinema sessions already started */
    private $now;

    public function __construct(callable $t, $now = null)
    {
        $this->t   = $t;
        $this->now = $now === null ? time() : (int) $now;
    }

    /**
     * @param   array  $res    ['data' => [...], 'meta' => [...]] of GET /rates
     * @param   array  $codes  currency codes to show, in this order; empty = all
     */
    public function rates(array $res, array $codes, $heading = false)
    {
        $rows = array();
        $date = '';

        foreach ((array) $res['data'] as $r) {
            if (!isset($r['code'], $r['value'])) {
                continue;
            }

            $rows[strtoupper($r['code'])] = $r;
            $date = isset($r['date']) ? $r['date'] : $date;
        }

        if ($codes) {
            $pick = array();

            foreach ($codes as $c) {
                $c = strtoupper(trim($c));

                if (isset($rows[$c])) {
                    $pick[$c] = $rows[$c];
                }
            }

            $rows = $pick;
        }

        if (!$rows) {
            return '';
        }

        $h = '<table class="satelit__table"><thead><tr>'
            . '<th scope="col">' . $this->tr('CODE') . '</th>'
            . '<th scope="col" class="satelit__num">' . $this->tr('MDL') . '</th>'
            . '<th scope="col" class="satelit__num">' . $this->tr('CHANGE_7D') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $nominal = isset($r['nominal']) && (int) $r['nominal'] > 1 ? (int) $r['nominal'] . ' ' : '';
            $h .= '<tr><th scope="row"><abbr title="' . $this->e(isset($r['name']) ? $r['name'] : '') . '">'
                . $this->e($nominal . $r['code']) . '</abbr></th>'
                . '<td class="satelit__num">' . $this->num($r['value'], 4) . '</td>'
                . '<td class="satelit__num">' . $this->pct(isset($r['change_7d_pct']) ? $r['change_7d_pct'] : null) . '</td></tr>';
        }

        $h .= '</tbody></table>';

        return $this->wrap('rates', $this->title('RATES_TITLE', $date, $heading), $h, $res['meta']);
    }

    /**
     * @param   array  $res    GET /fuel
     * @param   int    $limit  networks listed, cheapest A95 first
     */
    public function fuel(array $res, $limit = 5, $heading = false)
    {
        $d = (array) $res['data'];

        if (empty($d['range'])) {
            return '';
        }

        $h = '<table class="satelit__table"><thead><tr><th scope="col"></th>'
            . '<th scope="col" class="satelit__num">' . $this->tr('FUEL_MIN') . '</th>'
            . '<th scope="col" class="satelit__num">' . $this->tr('FUEL_MAX') . '</th>'
            . '<th scope="col" class="satelit__num">' . $this->tr('FUEL_CAP') . '</th></tr></thead><tbody>';

        foreach (array('a95' => 'FUEL_A95', 'diesel' => 'FUEL_DIESEL', 'lpg' => 'FUEL_LPG') as $fuel => $label) {
            if (!isset($d['range'][$fuel]['min'])) {
                continue;
            }

            $cap = isset($d['cap'][$fuel]) ? $this->num($d['cap'][$fuel], 2) : '–';
            $h  .= '<tr><th scope="row">' . $this->tr($label) . '</th>'
                . '<td class="satelit__num">' . $this->num($d['range'][$fuel]['min'], 2) . '</td>'
                . '<td class="satelit__num">' . $this->num($d['range'][$fuel]['max'], 2) . '</td>'
                . '<td class="satelit__num">' . $cap . '</td></tr>';
        }

        $h .= '</tbody></table>';

        // The cheapest networks for A95 (fresh prices only).
        $nets = array();

        foreach (isset($d['networks']) ? (array) $d['networks'] : array() as $n) {
            if (empty($n['stale']) && isset($n['prices']['a95'], $n['name'])) {
                $nets[] = $n;
            }
        }

        usort($nets, function ($a, $b) {
            return $a['prices']['a95'] < $b['prices']['a95'] ? -1 : ($a['prices']['a95'] > $b['prices']['a95'] ? 1 : 0);
        });

        if ($limit > 0 && $nets) {
            $h .= '<p class="satelit__sub">' . $this->tr('FUEL_CHEAPEST') . '</p><ol class="satelit__list">';

            foreach (\array_slice($nets, 0, (int) $limit) as $n) {
                $h .= '<li>' . $this->e($n['name']) . ' <span class="satelit__num">' . $this->num($n['prices']['a95'], 2) . '</span></li>';
            }

            $h .= '</ol>';
        }

        return $this->wrap('fuel', $this->title('FUEL_TITLE', isset($d['date']) ? $d['date'] : '', $heading), $h, $res['meta']);
    }

    /**
     * @param   array  $res  GET /news
     */
    public function news(array $res, $heading = false)
    {
        $items = '';

        foreach ((array) $res['data'] as $n) {
            if (empty($n['title'])) {
                continue;
            }

            $src   = isset($n['sources'][0]) ? $n['sources'][0] : null;
            $title = $this->e($n['title']);
            $url   = $src && isset($src['url']) ? $this->url($src['url']) : '';
            $items .= '<li>' . ($url !== '' ? '<a href="' . $url . '" rel="noopener nofollow">' . $title . '</a>' : $title)
                . '<span class="satelit__meta">'
                . (isset($n['published_at']) ? $this->e(substr($n['published_at'], 11, 5)) : '')
                . ($src && isset($src['name']) ? ' · ' . $this->e($src['name']) : '')
                . '</span></li>';
        }

        if ($items === '') {
            return '';
        }

        return $this->wrap('news', $this->title('NEWS_TITLE', '', $heading), '<ul class="satelit__list satelit__list--news">' . $items . '</ul>', $res['meta']);
    }

    /**
     * @param   array  $res      GET /movies/cinemas/today
     * @param   bool   $posters  show small posters
     */
    public function cinema(array $res, $posters = true, $heading = false)
    {
        $d     = (array) $res['data'];
        $day   = isset($d['day']) ? (string) $d['day'] : '';
        $today = $this->today();
        $cut   = $day === $today ? $this->clock() : '';
        $h     = '';

        foreach (isset($d['cinemas']) ? (array) $d['cinemas'] : array() as $c) {
            if (empty($c['cinema']['name'])) {
                continue;
            }

            $films = '';

            foreach (isset($c['films']) ? (array) $c['films'] : array() as $f) {
                $times = array();

                foreach (isset($f['sessions']) ? (array) $f['sessions'] : array() as $s) {
                    if (empty($s['time']) || ($cut !== '' && $s['time'] < $cut) || !empty($s['sold_out'])) {
                        continue;
                    }

                    $label   = $this->e($s['time']) . (!empty($s['formats']) && $s['formats'] !== array('2D') ? ' <small>' . $this->e(implode(' ', $s['formats'])) . '</small>' : '');
                    $book    = isset($s['booking_url']) ? $this->url($s['booking_url']) : '';
                    $times[] = '<li>' . ($book !== '' ? '<a href="' . $book . '" rel="noopener nofollow">' . $label . '</a>' : $label) . '</li>';
                }

                if (!$times || empty($f['title'])) {
                    continue;
                }

                $img = '';

                if ($posters && isset($f['pictures']['poster']['s'])) {
                    $src = $this->url($f['pictures']['poster']['s']);
                    $img = $src !== '' ? '<img class="satelit__poster" src="' . $src . '" alt="" width="46" height="69" loading="lazy">' : '';
                }

                $films .= '<li class="satelit__film">' . $img . '<div><span class="satelit__film-title">' . $this->e($f['title']) . '</span>'
                    . '<ul class="satelit__times">' . implode('', $times) . '</ul></div></li>';
            }

            if ($films !== '') {
                $h .= '<h4 class="satelit__cinema">' . $this->e($c['cinema']['name']) . '</h4><ul class="satelit__films">' . $films . '</ul>';
            }
        }

        if ($h === '') {
            $h = '<p class="satelit__empty">' . $this->tr('CINEMA_NONE') . '</p>';
        }

        return $this->wrap('cinema', $this->title('CINEMA_TITLE', $day, $heading), $h, $res['meta']);
    }

    /**
     * @param   array  $res  GET /jokes/today
     */
    public function joke(array $res, $heading = false)
    {
        $j = (array) $res['data'];

        if (empty($j['text'])) {
            return '';
        }

        $h = (!empty($j['title']) ? '<p class="satelit__joke-title">' . $this->e($j['title']) . '</p>' : '')
            . '<p class="satelit__joke">' . nl2br($this->e($j['text']), false) . '</p>';

        if (isset($j['source']['name'])) {
            $url = isset($j['source']['url']) ? $this->url($j['source']['url']) : '';
            $h  .= '<p class="satelit__meta">' . ($url !== '' ? '<a href="' . $url . '" rel="noopener nofollow">' . $this->e($j['source']['name']) . '</a>' : $this->e($j['source']['name'])) . '</p>';
        }

        return $this->wrap('joke', $this->title('JOKE_TITLE', '', $heading), $h, $res['meta']);
    }

    /**
     * @param   array  $res  GET /law/acts?important=true
     */
    public function law(array $res, $limit = 5, $heading = false)
    {
        $items = '';
        $n     = 0;

        foreach ((array) $res['data'] as $a) {
            $text = !empty($a['heading']) ? $a['heading'] : (isset($a['title']) ? $a['title'] : '');

            if ($text === '' || $n++ >= $limit) {
                continue;
            }

            $url    = isset($a['url']) ? $this->url($a['url']) : '';
            $items .= '<li>' . ($url !== '' ? '<a href="' . $url . '" rel="noopener">' . $this->e($text) . '</a>' : $this->e($text))
                . (isset($a['published_on']) ? '<span class="satelit__meta">' . $this->date($a['published_on']) . (isset($a['mo_ref']) ? ' · ' . $this->e($a['mo_ref']) : '') . '</span>' : '')
                . '</li>';
        }

        if ($items === '') {
            return '';
        }

        return $this->wrap('law', $this->title('LAW_TITLE', '', $heading), '<ul class="satelit__list">' . $items . '</ul>', $res['meta']);
    }

    // Helpers.

    private function wrap($kind, $title, $body, $meta)
    {
        $src = '';

        if (!empty($meta['attribution']) && \is_string($meta['attribution'])) {
            $src = $this->e($meta['attribution']) . ' · ';
        }

        return '<div class="satelit satelit--' . $kind . '">' . $title . $body
            . '<p class="satelit__src">' . $src . '<a href="https://satelit.md" rel="noopener">' . $this->tr('DATA') . '</a></p></div>';
    }

    private function title($key, $date, $heading)
    {
        $date = $date !== '' ? $this->date($date) : '';

        if ($heading) {
            return '<h3 class="satelit__title">' . $this->tr($key) . ($date !== '' ? ' <small>' . $date . '</small>' : '') . '</h3>';
        }

        return $date !== '' ? '<p class="satelit__date">' . $date . '</p>' : '';
    }

    private function tr($key)
    {
        return $this->e(\call_user_func($this->t, $key));
    }

    private function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /** Only http(s) links leave the API's data; anything else is dropped. */
    private function url($u)
    {
        $u = (string) $u;

        return preg_match('#^https?://#i', $u) ? $this->e($u) : '';
    }

    private function num($v, $decimals)
    {
        $sep = (string) \call_user_func($this->t, 'DECIMAL');
        $sep = ($sep === '' || $sep === 'DECIMAL') ? '.' : $sep;

        return $this->e(number_format((float) $v, $decimals, $sep, ''));
    }

    private function pct($v)
    {
        if ($v === null || !is_numeric($v)) {
            return '–';
        }

        $v     = (float) $v;
        $class = $v > 0 ? 'up' : ($v < 0 ? 'down' : 'same');
        $sign  = $v > 0 ? '+' : ($v < 0 ? '−' : '');

        return '<span class="satelit__' . $class . '">' . $sign . $this->num(abs($v), 1) . '%</span>';
    }

    /** 2026-11-19 → 19.11.2026 */
    private function date($iso)
    {
        $iso = (string) $iso;

        return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m) ? $m[3] . '.' . $m[2] . '.' . $m[1] : $this->e($iso);
    }

    private function today()
    {
        return $this->local()->format('Y-m-d');
    }

    private function clock()
    {
        return $this->local()->format('H:i');
    }

    /** Sessions are in Chișinău time. */
    private function local()
    {
        $d = new \DateTime('@' . $this->now);
        $d->setTimezone(new \DateTimeZone('Europe/Chisinau'));

        return $d;
    }
}
