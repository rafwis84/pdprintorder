<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_6($module): bool
{
    if (!($module instanceof PdPrintOrder)) {
        return false;
    }

    if (method_exists('Tools', 'clearSf2Cache')) {
        Tools::clearSf2Cache();
    }

    return true;
}
