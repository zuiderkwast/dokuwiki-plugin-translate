<?php
/**
 * Metadata for configuration manager plugin
 *
 * @author    Viktor Söderqvist <viktor@zuiderkwast.se>
 */

// languages
$meta['enabled_languages'] = array('string',
    // This pattern will accept most types of language codes...
    '_pattern'=>'/^\s*(?:(?:\w{2,3}(?:-\w+)?)(?:\s*,\s*\w{2,3}(?:-\w+)?)*)?\s*$/');
$meta['guess_lang_by_ns'] = array('onoff');
$meta['guess_lang_by_ui_lang'] = array('onoff');
$meta['default_language'] = array('string',
    // Language code
    '_pattern'=>'/^\s*(?:(?:\w{2,3}(?:-\w+)?)(?:\s*,\s*\w{2,3}(?:-\w+)?)*)?\s*$/');
$meta['use_language_namespace'] = array('onoff');

// namespaces and pages
$meta['include_namespaces'] = array('string');
$meta['exclude_namespaces'] = array('string');
$meta['exclude_pagenames']  = array('string');

// permissions
$meta['translator_group'] = array('string');
$meta['author_group'] = array('string');

// appearence
$meta['insert_translation_links'] = array('onoff');
$meta['link_style']  = array('multichoice', '_choices' => array('langcode','langname'));

//Setup VIM: ex: et ts=4 enc=utf-8 :
