<?php
/**
 * Package install script: checks the versions and turns the content
 * plugin on after the first install (Joomla installs plugins switched off).
 *
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class pkg_satelitInstallerScript
{
    public function preflight($type, $parent)
    {
        if ($type === 'uninstall') {
            return true;
        }

        if (version_compare(JVERSION, '3.10', '<') || version_compare(PHP_VERSION, '7.2', '<')) {
            Factory::getApplication()->enqueueMessage('Satelit needs Joomla 3.10+ and PHP 7.2+.', 'error');

            return false;
        }

        return true;
    }

    public function postflight($type, $parent)
    {
        if ($type !== 'install') {
            return true;
        }

        try {
            $db = Factory::getDbo();
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 1')
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('content'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('satelit'))
            )->execute();
        } catch (\Exception $e) {
            // The admin can switch it on by hand.
        }

        return true;
    }
}
