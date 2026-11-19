<?php
/**
 * Layout of the Satelit module. Copy it to your template's html/mod_satelit/
 * to change the wrapper. $satelitHtml is already escaped.
 *
 * @copyright  (c) 2026 Satelit (satelit.md)
 * @license    MIT
 */

\defined('_JEXEC') or die;

$satelitSfx = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');
?>
<div class="mod-satelit mod-satelit--<?php echo htmlspecialchars($satelitShow, ENT_QUOTES, 'UTF-8') . $satelitSfx; ?>">
	<?php echo $satelitHtml; ?>
</div>
