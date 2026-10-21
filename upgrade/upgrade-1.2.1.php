<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_1($module)
{
    if (!($module instanceof PdPrintOrder)) {
        return false;
    }

    if (!PdPrintOrderCommentRepository::ensureSchema()) {
        return false;
    }

    if (!$module->ensureHiddenTab()) {
        return false;
    }

    if (!$module->isRegisteredInHook('displayAdminOrderMain') && !$module->registerHook('displayAdminOrderMain')) {
        return false;
    }

    if (method_exists('Tools', 'clearSf2Cache')) {
        Tools::clearSf2Cache();
    }

    return true;
}
