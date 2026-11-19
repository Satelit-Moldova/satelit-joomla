<?php
/**
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

namespace Satelit\Joomla;

\defined('_JEXEC') or \defined('SATELIT_TEST') or die;

/**
 * The small cache the client needs. Values are arrays or ints.
 */
interface CacheStore
{
    /**
     * @param   string  $id
     *
     * @return  mixed  null when missing
     */
    public function get($id);

    /**
     * @param   string  $id
     * @param   mixed   $value
     * @param   int     $seconds  how long the store keeps it
     */
    public function set($id, $value, $seconds);
}

/**
 * In-memory store, for tests and for a request without a cache.
 */
final class MemoryStore implements CacheStore
{
    public $items = array();

    public function get($id)
    {
        return isset($this->items[$id]) ? $this->items[$id] : null;
    }

    public function set($id, $value, $seconds)
    {
        $this->items[$id] = $value;
    }
}
