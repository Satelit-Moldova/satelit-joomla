<?php
/**
 * Content - Satelit: {satelit ...} tags in articles, and the API key.
 *
 *   {satelit rates EUR,USD}   {satelit fuel}   {satelit news 5}
 *   {satelit cinema Chișinău} {satelit joke}   {satelit law 5}
 *
 * A classic plugin class (no service provider), loaded the same way by
 * Joomla 3.10, 4.x and 5.x.
 *
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Registry\Registry;

class PlgContentSatelit extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /**
     * Replace the tags in an article (and anything else run through content
     * plugins: custom modules with "Prepare content", category descriptions).
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        // Smart Search indexes the text: no API calls for it.
        if ($context === 'com_finder.indexer' || !\is_object($article) || empty($article->text)
            || strpos($article->text, '{satelit') === false || !self::load()) {
            return;
        }

        $html = \Satelit\Joomla\Site::blocks()->replaceTags($article->text);

        if ($html !== $article->text) {
            \Satelit\Joomla\Site::stylesheet();
            $article->text = $html;
        }
    }

    /**
     * The key field is always shown empty: an empty field keeps the saved
     * key, a single "-" removes it.
     */
    public function onExtensionBeforeSave($context, $table, $isNew, $data = array())
    {
        if (!$this->isMe($context, $table) || !self::load()) {
            return true;
        }

        $params = new Registry($table->params);
        $typed  = trim((string) $params->get('api_key', ''));

        if ($typed === '') {
            $params->set('api_key', (string) \Satelit\Joomla\Site::settings()->get('api_key', ''));
        } elseif ($typed === '-') {
            $params->set('api_key', '');
        }

        $table->params = $params->toString();

        return true;
    }

    /** After saving, check the key once (GET /me, 0 credits) and say what the API answered. */
    public function onExtensionAfterSave($context, $table, $isNew, $data = array())
    {
        if (!$this->isMe($context, $table) || !self::load()) {
            return;
        }

        $params = new Registry($table->params);
        $app    = Factory::getApplication();

        if ((string) $params->get('api_key', '') === '') {
            $app->enqueueMessage(Text::_('PLG_CONTENT_SATELIT_KEY_MISSING'), 'warning');

            return;
        }

        $api = new \Satelit\Joomla\Api(
            array('Satelit\Joomla\Site', 'transport'),
            new \Satelit\Joomla\MemoryStore(),
            (string) $params->get('api_key', ''),
            \Satelit\Joomla\Site::base((string) $params->get('api_base', ''))
        );
        $me = $api->me();

        if ($me['ok']) {
            $app->enqueueMessage(self::okLine($me['data']), 'message');
        } else {
            $app->enqueueMessage(Text::sprintf('PLG_CONTENT_SATELIT_KEY_BAD', $me['code']), 'warning');
        }
    }

    /** "Key works: plan pro, 299 400 credits left this period." */
    public static function okLine(array $me)
    {
        $c    = isset($me['credits']) ? (array) $me['credits'] : array();
        $left = isset($c['limit'], $c['used']) ? max(0, (int) $c['limit'] - (int) $c['used']) : 0;

        return Text::sprintf('PLG_CONTENT_SATELIT_KEY_OK', isset($me['plan']) ? (string) $me['plan'] : '?', number_format($left, 0, '', ' '));
    }

    private function isMe($context, $table)
    {
        return $context === 'com_plugins.plugin' && \is_object($table)
            && isset($table->element, $table->folder) && $table->element === 'satelit' && $table->folder === 'content';
    }

    /** The shared library; when it is missing the plugin does nothing. */
    private static function load()
    {
        $file = JPATH_LIBRARIES . '/satelit/loader.php';

        if (!is_file($file)) {
            return false;
        }

        require_once $file;

        return true;
    }
}
