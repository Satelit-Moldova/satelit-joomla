<?php
/**
 * API key field: always rendered empty (the saved key never goes back to the
 * browser), with the key's state read from GET /me (0 credits).
 *
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

class JFormFieldSatelitkey extends FormField
{
    protected $type = 'Satelitkey';

    protected function getInput()
    {
        $saved = trim((string) $this->value) !== '';
        $hint  = Text::_('PLG_CONTENT_SATELIT_KEY_HINT_NEW');
        $state = '<span class="badge bg-secondary label">' . $this->e(Text::_('PLG_CONTENT_SATELIT_KEY_MISSING')) . '</span>';

        if ($saved && is_file(JPATH_LIBRARIES . '/satelit/loader.php')) {
            require_once JPATH_LIBRARIES . '/satelit/loader.php';
            require_once JPATH_PLUGINS . '/content/satelit/satelit.php';

            $me = \Satelit\Joomla\Site::api()->me();

            if ($me['ok']) {
                $prefix = isset($me['data']['key']['prefix']) ? $me['data']['key']['prefix'] . '…' : '';
                $hint   = Text::sprintf('PLG_CONTENT_SATELIT_KEY_HINT_SAVED', $prefix);
                $state  = '<span class="badge bg-success label label-success">' . $this->e(PlgContentSatelit::okLine($me['data'])) . '</span>';
            } else {
                $hint  = Text::sprintf('PLG_CONTENT_SATELIT_KEY_HINT_SAVED', '');
                $state = '<span class="badge bg-warning text-dark label label-warning">' . $this->e(Text::sprintf('PLG_CONTENT_SATELIT_KEY_BAD', $me['code'])) . '</span>';
            }
        }

        return '<input type="password" class="form-control inputbox" name="' . $this->e($this->name) . '"'
            . ' id="' . $this->e($this->id) . '" value="" autocomplete="new-password" spellcheck="false"'
            . ' placeholder="' . $this->e($hint) . '">'
            . '<div class="satelit-key-state" style="margin-top:.5em">' . $state . '</div>';
    }

    private function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}
