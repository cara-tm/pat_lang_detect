<?php
/**
 * Simple browser language detection for section redirect (simple multilinguage support)
 * Created for the "FOTO" theme
 *
 * @type:    Public
 * @prefs:   no
 * @order:   5
 * @version: 0.1.1
 * @license: GPLv2
 */


/**
 * This plugin tag registry.
 */
if (class_exists('\Textpattern\Tag\Registry')) {
	Txp::get('\Textpattern\Tag\Registry')
		->register('pat_lang_detect')
		->register('pat_lang_meta_href');
}


/**
 * Detect visitor's browser lang (ISO2) and create a variable to store it.
 * Part of this code based on Robert Wetzlmayr's script.
 *
 * @param
 * @return string
 */
function pat_lang_detect($atts, $thing='')
{
	global $variable;

	extract(lAtts(array(
		'redirect'  => false,
		'display'   => false,
	), $atts));

	$langs = explode(",", @$_SERVER["HTTP_ACCEPT_LANGUAGE"]);
	$_SESSION['language'] = preg_replace('/(?:(?<=([a-z]{2}))).*/', '', $langs[0]);

	// Create a 'visitor_lang' variable for conveniences, default: prefs lang.
	if ( !empty($_SESSION['language']) )
		$variable['visitor_lang'] = $_SESSION['language'];
	else
		$variable['visitor_lang'] = substr(get_pref('language'), 0, 2);

	// Change 'visitor_lang' variable by the $_GET value from URLs
	if ( gps('lang') )
		$variable['visitor_lang'] = gps('lang');

	// Redirection to locale page or not; otherwise display only the URL
	if (true != $redirect) {
		return $display ? hu._pat_lang_detect_section_name($variable['visitor_lang']) : '';
	} else {
		header('Location: '.hu._pat_lang_detect_section_name($variable['visitor_lang']));
	}

}


/**
 * Compares a variable from names stored into the 'section' table
 *
 * @param  $code string
 * @return $code string
 */
function _pat_lang_detect_section_name($code)
{
	global $DB;
	$DB = new DB;
	$rs = safe_row('name', 'txp_section', "name = '".doSlash($code)."'");

	if ($rs)
		$out = $code;
	else
		$out = '';

	return $out;
}

/**
 * Creates link tags for locale alternate URLs
 *
 * @param
 * @return string HTML link tag
 */
function pat_lang_meta_href()
{

	$current = substr(get_pref('language'), 0, 2);
	$out = '';

	$data = safe_rows('name', 'txp_section', "1=1");

	foreach ($data as $value) {

		if ($value['name'] == $current) 
			$out .= '<link rel="alternate" hreflang="x-default" href="'.hu.$current.'">'.n;

		if (strlen($value['name']) == 2 && $value['name'] != $current)
			$out .= '<link rel="alternate" hreflang="'.$value['name'].'" href="'.hu.$value['name'].'">'.n;

	}

	return $out;

}
