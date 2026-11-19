<?php
/**
 * Loads the Satelit library. Plain require_once, so it works the same on
 * Joomla 3.10 (no namespace autoloading for libraries) and Joomla 4/5.
 *
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

\defined('_JEXEC') or die;

require_once __DIR__ . '/src/CacheStore.php';
require_once __DIR__ . '/src/Api.php';
require_once __DIR__ . '/src/Render.php';
require_once __DIR__ . '/src/Blocks.php';
require_once __DIR__ . '/src/Site.php';
