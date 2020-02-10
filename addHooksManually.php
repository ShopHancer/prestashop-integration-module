<?php

require(dirname(__FILE__).'/../../config/config.inc.php');
require('shophancerintegration.php');

$objects = file(realpath(__DIR__ . '/hookObjectList.txt'));

$module = new ShopHancerIntegration();

// register hooks
foreach (['Add', 'Update', 'Delete'] as $operation) {
    foreach ($objects as $object) {
        $hookName = sprintf('actionObject%s%sAfter', trim($object), $operation);
        $module->registerHook($hookName);
    }
}




