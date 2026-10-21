<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_0($module)
{
    // Retain configuration, payment records, receipts and order states.
    return $module->processSQL('upgrade/sql/upgrade-2.0.0.sql');
}
