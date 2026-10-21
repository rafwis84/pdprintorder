<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_0($module): bool
{
    require_once _PS_MODULE_DIR_ . 'pdprintorder/classes/PdPrintOrderCommentRepository.php';

    if (!PdPrintOrderCommentRepository::installSchema()) {
        return false;
    }

    if (method_exists($module, 'ensureHiddenTab') && !$module->ensureHiddenTab()) {
        return false;
    }

    $hooks = [
        'displayAdminOrderTop',
        'displayAdminOrderSide',
        'displayAdminOrderMain',
        'actionOrderGridDefinitionModifier',
    ];

    foreach ($hooks as $hook) {
        if (!$module->registerHook($hook)) {
            return false;
        }
    }

    if (method_exists('Tools', 'clearSf2Cache')) {
        Tools::clearSf2Cache();
    }

    return true;
}
