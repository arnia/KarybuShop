<?php

if(!defined('__DEBUG__')) define('__DEBUG__', 1);
if(!defined('__KARYBU__')) define('__KARYBU__', TRUE);
if(!defined('__KARYBU__')) define('__KARYBU__', TRUE);
if(!defined('__ZBXE__')) define('__ZBXE__', TRUE);
if(!defined('_KARYBU_PATH_')) define('_KARYBU_PATH_', realpath(dirname(__FILE__) . '/../../../../') . '/');

require_once(_KARYBU_PATH_ . 'config/config.inc.php');
require_once(_KARYBU_PATH_ . 'modules/shop/libs/autoload/autoload.php');

// Delete any cache files
FileHandler::removeFilesInDir(_KARYBU_PATH_ . 'files/cache');

$oContext = Context::getInstance();
Context::setLangType('en');

// Load common language files, for the error messages to be displayed
Context::loadLang(_KARYBU_PATH_.'common/lang/');

/* End of file Bootstrap.php */
/* Location: ./modules/shop/tests/lib/Bootstrap.php */
