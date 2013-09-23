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
namespace QA\PhpbbImporter;

class Text2slug
{
    public static function convert($text)
    {
        $slug = $text;
        // TODO: add fullproof name to slug conversion; check against Q2A DB (other categories, pages)
        $slug = implode('-', \qa_string_to_words($slug));
        return $slug;
    }
}
