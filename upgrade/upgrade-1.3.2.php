<?php
/**
 * 2026 Nitron
 *
 * @author    nitron
 * @copyright 2026 Nitron
 * @license   Commercial License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_2($module)
{
    $module->unregisterHook('displayAdminOrder');

    return $module->registerHook('displayAdminOrderMainBottom');
}
