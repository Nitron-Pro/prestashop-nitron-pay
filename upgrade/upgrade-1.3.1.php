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

function upgrade_module_1_3_1($module)
{
    if (!method_exists($module, 'ensurePendingOrderState')) {
        return false;
    }

    return (bool) $module->ensurePendingOrderState();
}
