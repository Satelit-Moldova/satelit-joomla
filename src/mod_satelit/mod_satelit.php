<?php
/**
 * Satelit module: one block of Moldova's data, chosen in the module's
 * settings. The key lives in "Content - Satelit" (one key for the site).
 * A classic module entry file, run the same by Joomla 3.10, 4.x and 5.x.
 *
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

$satelitLoader = JPATH_LIBRARIES . '/satelit/loader.php';

// Library missing: show nothing rather than break the page.
if (!is_file($satelitLoader)) {
    return;
}

require_once $satelitLoader;

$satelitShow = (string) $params->get('show', 'rates');
$satelitHtml = \Satelit\Joomla\Site::blocks()->html($satelitShow, '', array(
    'codes'    => (string) $params->get('codes', 'EUR,USD,RON'),
    'networks' => (int) $params->get('networks', 5),
    'count'    => (int) $params->get('count', 5),
    'category' => (string) $params->get('category', ''),
    'city'     => (string) $params->get('city', 'Chișinău'),
    'posters'  => (bool) $params->get('posters', 1),
));

// No key, API down and no saved copy: the module disappears.
if ($satelitHtml === '') {
    return;
}

\Satelit\Joomla\Site::stylesheet();

require ModuleHelper::getLayoutPath('mod_satelit', $params->get('layout', 'default'));
