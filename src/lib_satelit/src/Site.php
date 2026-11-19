<?php
/**
 * Joomla glue: the key and options from the plugin's settings, Joomla's HTTP
 * client, Joomla's cache, Joomla's log, language strings and the stylesheet.
 * Uses only classes present in Joomla 3.10, 4.x and 5.x.
 *
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

namespace Satelit\Joomla;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;

final class Site
{
    /** @var Blocks|null */
    private static $blocks;

    /** @var Api|null */
    private static $api;

    private static $logger = false;

    private static $css = false;

    /**
     * The settings saved in "Content - Satelit" (key, language, API address).
     * Read from the table, so the module works even with the plugin disabled.
     */
    public static function settings()
    {
        static $params = null;

        if ($params === null) {
            $params = new Registry();

            try {
                $db    = self::db();
                $query = $db->getQuery(true)
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('content'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('satelit'));
                $params->loadString((string) $db->setQuery($query)->loadResult());
            } catch (\Exception $e) {
                self::log('Satelit: settings not readable (' . $e->getMessage() . ')');
            }
        }

        return $params;
    }

    public static function api()
    {
        if (self::$api === null) {
            $p = self::settings();

            self::$api = new Api(
                array(__CLASS__, 'transport'),
                new JoomlaStore(),
                (string) $p->get('api_key', ''),
                self::base((string) $p->get('api_base', '')),
                self::lang((string) $p->get('lang', '')),
                array(__CLASS__, 'log')
            );
        }

        return self::$api;
    }

    public static function blocks()
    {
        if (self::$blocks === null) {
            self::language();
            self::$blocks = new Blocks(self::api(), new Render(function ($key) {
                return Text::_('LIB_SATELIT_' . $key);
            }));
        }

        return self::$blocks;
    }

    /** Adds the small stylesheet once, on HTML pages only. */
    public static function stylesheet()
    {
        if (self::$css) {
            return;
        }

        self::$css = true;

        try {
            $app = Factory::getApplication();
            $doc = method_exists($app, 'getDocument') ? $app->getDocument() : Factory::getDocument();

            if ($doc && $doc->getType() === 'html') {
                HTMLHelper::_('stylesheet', 'lib_satelit/satelit.css', array('version' => 'auto', 'relative' => true));
            }
        } catch (\Exception $e) {
            // No document (CLI): nothing to style.
        }
    }

    /**
     * Joomla's HTTP client. Returns [code, body, headers]; throws on network errors.
     */
    public static function transport($url, array $headers, $timeout)
    {
        $http = HttpFactory::getHttp();
        $r    = $http->get($url, $headers, $timeout);

        // Joomla 4/5 responses are PSR-7; Joomla 3 has public properties.
        if (method_exists($r, 'getStatusCode')) {
            $out = array();

            foreach ($r->getHeaders() as $name => $values) {
                $out[$name] = implode(', ', (array) $values);
            }

            return array($r->getStatusCode(), (string) $r->getBody(), $out);
        }

        $out = array();

        foreach ((array) $r->headers as $name => $value) {
            $out[$name] = \is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return array((int) $r->code, (string) $r->body, $out);
    }

    /** One short line in logs/satelit.php. */
    public static function log($line)
    {
        if (!self::$logger) {
            self::$logger = true;
            Log::addLogger(array('text_file' => 'satelit.php'), Log::ALL, array('satelit'));
        }

        Log::add($line, Log::WARNING, 'satelit');
    }

    public static function language()
    {
        static $done = false;

        if (!$done) {
            $done = true;
            $lang = self::joomlaLanguage();
            // An override in /language first, else the files shipped with the library.
            $lang->load('lib_satelit', JPATH_SITE) || $lang->load('lib_satelit', JPATH_LIBRARIES . '/satelit');
        }
    }

    /** ro-RO → ro, ru-RU → ru, uk-UA → uk, else en; a saved choice wins. */
    public static function lang($saved)
    {
        if (\in_array($saved, array('ro', 'ru', 'en', 'uk'), true)) {
            return $saved;
        }

        $tag = strtolower(substr(self::joomlaLanguage()->getTag(), 0, 2));

        return \in_array($tag, array('ro', 'ru', 'uk'), true) ? $tag : 'en';
    }

    /** Only an https address (or http on localhost, for tests) replaces the default. */
    public static function base($saved)
    {
        $saved = trim($saved);

        if ($saved !== '' && preg_match('#^(https://[^\s]+|http://(127\.0\.0\.1|localhost)(:\d+)?/[^\s]*)$#i', $saved)) {
            return $saved;
        }

        return Api::DEFAULT_BASE;
    }

    private static function joomlaLanguage()
    {
        $app = Factory::getApplication();

        return method_exists($app, 'getLanguage') && $app->getLanguage() ? $app->getLanguage() : Factory::getLanguage();
    }

    private static function db()
    {
        if (method_exists('Joomla\CMS\Factory', 'getContainer')) {
            $c = Factory::getContainer();

            if ($c->has('DatabaseDriver')) {
                return $c->get('DatabaseDriver');
            }
        }

        return Factory::getDbo();
    }
}

/**
 * Joomla's cache, always on for these entries (even with the site's cache
 * off), in group "satelit": System → Clear Cache empties it.
 */
final class JoomlaStore implements CacheStore
{
    private $cache;

    public function __construct()
    {
        $options = array(
            'defaultgroup' => 'satelit',
            'caching'      => true,
            'lifetime'     => (int) ceil(Api::KEEP / 60) + 60, // minutes
        );

        if (interface_exists('Joomla\CMS\Cache\CacheControllerFactoryInterface') && method_exists('Joomla\CMS\Factory', 'getContainer')) {
            $this->cache = Factory::getContainer()
                ->get('Joomla\CMS\Cache\CacheControllerFactoryInterface')
                ->createCacheController('output', $options);
        } else {
            $this->cache = Factory::getCache('satelit', 'output');
            $this->cache->setLifeTime($options['lifetime']);
        }

        $this->cache->setCaching(true);
    }

    public function get($id)
    {
        try {
            $raw = $this->cache->get($id, 'satelit');
        } catch (\Exception $e) {
            return null;
        }

        if (!\is_string($raw) || $raw === '') {
            return null;
        }

        $v = json_decode($raw, true);

        return $v === null ? null : $v;
    }

    public function set($id, $value, $seconds)
    {
        try {
            $this->cache->store(json_encode($value), $id, 'satelit');
        } catch (\Exception $e) {
            // A full disk must not break the page.
        }
    }
}
