<?php
/**
 * Simple browser visitor preference language detection for locale redirects and other utilities
 *
 * @author   Patrick LEFEVRE - https://github.com/cara-tm/pat_lang_detect
 * @type:    Public
 * @prefs:   no
 * @order:   5
 * @version: 0.2.3
 * @license: GPLv2
 */


/**
 * This plugin tag registry.
 */
if (class_exists('\Textpattern\Tag\Registry')) {
	Txp::get('\Textpattern\Tag\Registry')
		->register('pat_lang_detect')
		->register('pat_lang_default')
		->register('pat_lang_meta_href')
		->register('pat_lang_compare');
}


/**
 * This plugin lifecycle.
 */
if (txpinterface == 'admin')
{
	register_callback('pat_lang_detect_prefs', 'prefs', '', 1);
	register_callback('pat_lang_detect_cleanup', 'plugin_lifecycle.pat_lang_detect', 'deleted');
}


/**
 * Detect visitor's browser lang (ISO2) and create a variable to store it.
 * Part of this code based on Robert Wetzlmayr's script.
 *
 * @param  $atts array This plugin attributes
 * @return string      ISO2 language code
 */
function pat_lang_detect($atts)
{
	global $prefs, $pretext, $variable;

	extract(lAtts(array(
		'redirect'  => false,
		'display'   => false,
	), $atts));

	_pat_lang_detect_start_session();

	$langs = explode(',', @$_SERVER["HTTP_ACCEPT_LANGUAGE"]);
	$_SESSION['language'] = preg_replace('/(?:(?<=([a-z]{2}))).*/', '', $langs[0]);

	// Create a 'visitor_lang' variable for conveniences, default: prefs lang.
	if (!empty($_SESSION['language']) && false != $prefs['pat_lang_detect_enable'])
		$variable['visitor_lang'] = $_SESSION['language'];
	else
		$prefs['pat_lang_detect_enable'] ? $variable['visitor_lang'] = substr(get_pref('language'), 0, 2) : '';

	// Change 'visitor_lang' variable by the $_GET value from URLs
	if (gps('lang'))
		$variable['visitor_lang'] = gps('lang');

	// Overwrite 'visitor_lang' variable within a 2 letters section name (ISO2 code)
	if (strlen($pretext['s']) == 2)
		$variable['visitor_lang'] = $pretext['s'];

	// Redirection to locale page or not; otherwise display only the URL
	if ($variable['visitor_lang'] != substr(get_pref('language'), 0, 2)) {
		if (true != $redirect)
			return $display ? hu._pat_lang_detect_section_name($variable['visitor_lang']) : '';
		else
			header('Location: '.hu._pat_lang_detect_section_name($variable['visitor_lang']));
	}

}


/**
 * Session function
 *
 * @param
 * @return boolean
 */
function _pat_lang_detect_start_session()
{
	if (headers_sent()) {
		if (!isset($_SESSION)) {
			$_SESSION = array();
		}
		return false;
	} elseif (!isset($_SESSION)) {
		session_cache_limiter("must-revalidate");
		session_start();
		return true;
	} else {
		return true;
	}
}


/**
 * A simple helper tag to get the ISO2 code of the default TXP language
 *
 * @param
 * @return string ISO2 code
 */
function pat_lang_default()
{
	return substr(get_pref('language'), 0, 2);
}


/**
 * Compares a variable from names stored into the 'section' table
 *
 * @param  $code string ISO2 language code
 * @return $code string ISO2 language code found in DB
 */
function _pat_lang_detect_section_name($code)
{
	global $DB;
	$DB = new DB;
	$rs = safe_field('name', 'txp_section', "name = '".doSlash($code)."'");

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
	global $pretext, $is_article_list;
	$out = '';

	// ISO2 lang prefs
	$current = pat_lang_default();
	// Loads main function
	pat_lang_detect(array('redirect' => 0, 'display' => 0));
	// Query: get all section names
	$data = safe_rows('name', 'txp_section', "1=1");

	if ($pretext['s'] == 'default' || (strlen($pretext['s']) == 2 && true == $is_article_list)) {
		$out .= '<link rel="alternate" hreflang="x-default" href="'.hu.'">'.n;
		// Loop for locale sections
		foreach ($data as $value) {
			if (strlen($value['name']) == 2 && $value['name'] != $current)
				$out .= '<link rel="alternate" hreflang="'.$value['name'].'" href="'.pagelinkurl(array('s' => $value['name'])).'">'.n;	
		}
	} else {
		// Is there a 'Twin_ID' custom_field for this individual article?
		if (custom_field(array('name' => 'Twin_ID')) && custom_field(array('name' => 'Twin_ID')) != article_id(array())) {
			// Check all in the comma separated list of IDs
			$list = explode(',', trim(custom_field(array('name' => 'Twin_ID'))));
			foreach($list as $id) {
				// Retrieves the alternate link with the ISO2 section name
				$out .= _pat_lang_detect_section_grab(permlink(array('id' => $id)));
			}
		}
	}
	return $out;
}


/**
 * Filter the corresponding sections
 *
 * @param $scheme string URL
 * @return string        HTML link tag
 */
function _pat_lang_detect_section_grab($scheme)
{

	if ($scheme)
		preg_match('%\/([a-z]{2})\/%', $scheme, $m);

	if ($m[1] == substr(get_pref('language'), 0, 2))
		$ref = 'x-default';
	else
		$ref = $m[1];

	return '<link rel="alternate" hreflang="'.$ref.'" href="'.$scheme.'">'.n;
}


/**
 * Simple comparaison between TXP default language and the visitor one
 *
 * @param
 * @return empty|string Nothing or the visitor language ISO2 code
*/
function pat_lang_compare()
{
	global $variable;

	if ($variable['visitor_lang'] != pat_lang_default())
		$out = $variable['visitor_lang'];
	else
		$out = '';

	return $out;
}


/**
 * This plugin preferences
 *
 * @param
 * @return SQL Plugin preference field
 */
function pat_lang_detect_prefs()
{
	global $textarray;

	$textarray['pat_lang_detect_enable'] = 'Enable pat_lang_detect?';

	if (!safe_field('name', 'txp_prefs', "name='pat_lang_detect_enable'"))
	{
		safe_insert('txp_prefs', "name='pat_lang_detect_enable', val='0', type=1, event='admin', html='yesnoradio', position=30");
	}
}


/**
 * This plugin cleanup on deletion
 *
 * @param
 * @return SQL Safe delete field
 */
function pat_lang_detect_cleanup()
{
	safe_delete('txp_prefs', "name='pat_lang_detect_enable'");
}

