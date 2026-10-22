<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_4($module): bool
{
    if (!($module instanceof PdPrintOrder)) {
        return false;
    }

    if (method_exists('Tools', 'clearSf2Cache')) {
        Tools::clearSf2Cache();
    }

    return true;
}
