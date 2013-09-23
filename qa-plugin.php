<?php
/*
	Plugin Name: phpBB Importer
	Plugin URI:
	Plugin Description: Imports phpBB forum to your Q2A site.
	Plugin Version: 1.0
	Plugin Date: 2012-11-12
	Plugin Author: ImpressPages CMS team
	Plugin Author URI: http://www.impresspages.org/
	Plugin License: Commercial
	Plugin Minimum Question2Answer Version: 1.5
	Plugin Update Check URI:
*/

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
    header('Location: ../../');
    exit;
}

qa_register_plugin_module('page', 'qa-phpbb-importer.php', 'qa_phpbb_importer', 'phpBB Importer');
