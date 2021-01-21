<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Viktor Söderqvist <viktor@zuiderkwast.se>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_LF')) define ('DOKU_LF',"\n");

class helper_plugin_translate extends DokuWiki_Plugin {

    // vars used for caching / lazy initialization
    private $enabledLangs;
    private $langnames;
    private $langflags;
    private $page_language;
    private $curPageIsTranslatable;

    public function getMethods() {
        return array(
            array(
                'name'   => 'getPageLanguage',
                'desc'   => 'Returns a language code or null if failed to detect',
                'return' => array('language' => 'string'),
            ),
            array(
                'name'   => 'languageExists',
                'desc'   => 'Checks if a language exists by checking the translations of dokuwiki itself',
                'param'  => array('language' => 'string'),
                'return' => array('exists' => 'boolean'),
            ),
            array(
                'name'   => 'translationLookup',
                'desc'   => 'Returns the ID of a page (default the current page) translated in another language, or null if not existing.',
                'param'  => array('language' => 'string', 'id (optional)' => 'sting'),
                'return' => array('translation_id' => 'string'),
            ),
            array(
                'name'   => 'suggestTranslationId',
                'desc'   => 'Returns an suggested ID of a translation of the current page, '.
                            'in the same or another namespace, according to the configuration',
                'param'  => array('title' => 'string', 'language' => 'sting', 'from_language' => 'string'),
                'return' => array('translation_id' => 'string'),
            ),
            array(
                'name'   => 'getLanguageName',
                'desc'   => 'Returns the name of a language in the native language and English',
                'param'  => array('code' => 'string'),
                'return' => array('name' => 'string'),
            ),
            // FIXME add the rest of them here.....
        );
    }

    /** using guessing rules set in configuration */
    public function getPageLanguage($id=null) {
        global $INFO, $ID, $conf;
        if (is_null($id)) $id = $ID;
        $meta = $id!==$ID ? p_get_metadata($id) : $INFO['meta'];
        if (!isset($this->page_language)) $this->page_language = array();
        if (!isset($this->page_language[$id])) {
            // Detect the language of the page
            if (isset($meta['language'])) {
                $lang = $meta['language'];
            }
            if (!isset($lang) && $this->getConf('guess_lang_by_ns')) {
                // If the first level of namespace is a language code, use that
                list($ns1) = explode(':',$id,2);
                if ($this->languageExists($ns1)) $lang = $ns1;
            }
            if (!isset($lang) && $this->getConf('guess_lang_by_ui_lang')) {
                // Use the UI language
                $lang = $conf['lang'];
            }
            if (!isset($lang) && ($default = $this->getConf('default_language')) &&
                $this->languageExists($default)) {
                // Use default language
                $lang = $default;
            }
            $this->page_language[$id] = $lang;
        }
        return $this->page_language[$id];
    }

    /** checks if a language exists, i.e. is enabled. */
    public function languageExists($lang) {
        return preg_match('/^\w{2,3}(?:-\w+)?$/', $lang) &&
               is_dir(DOKU_INC . 'inc/lang/' . $lang);
    }

    public function getLanguageName($code) {
        $this->loadLangnames();
        list ($key, $subkey) = explode('-',$code,2); // Locale style code, i.e. en-GB
        $name = isset($this->langnames[$key]) ? $this->langnames[$key] : $code;
        list ($name) = explode(',',$name,2);
        if ($subkey) $name .= '-'.strtoupper($subkey);
        return $name;
    }

    private function loadLangnames() {
        if (isset($this->langnames)) return;
        // A list where the first letter is capitalized only if
        // the local language has this convention
        if (@include(dirname(__FILE__).'/langinfo.php')) {
            foreach ($langinfo as $code => $info) {
                list ($local_name, $english_name) = $info;
                $this->langnames[$code] = $local_name;
            }
        }
        else {
            $this->langnames = array(); // failed to load
        }
    }

    /** Returns true if page is allowed to be translated */
    public function isTranslatable($id=null) {
        global $ID;
        if (is_null($id)) $id = $ID;
        if ($id===$ID && isset($this->curPageIsTranslatable)) // cached
            return $this->curPageIsTranslatable;
        $ret = $this->checkIsTranslatable($id);
        if ($id===$ID) $this->curPageIsTranslatable = $ret; // cache
        return $ret;
    }

    /** helper used by isTranslatable */
    private function checkIsTranslatable($id) {
        $str = trim($this->getConf('include_namespaces'));
        if ($str == '') return false; // nothing to include
        if ($str != '*') {
            $inc_nss = array_map('trim',explode(',',$str));
            $any = false;
            foreach ($inc_nss as $ns) {
                if (self::isIdInNamespace($id,$ns)) {
                    $any = true;
                    break;
                }
            }
            if (!$any) return false;
        }
        $str = $this->getConf('exclude_namespaces');
        if ($str != '') {
            $exc_nss = array_map('trim',explode(',',$str));
            foreach ($exc_nss as $ns)
                if (self::isIdInNamespace($id,$ns))
                    return false;
        }
        $str = $this->getConf('exclude_pagenames');
        if ($str != '') {
            $exc_pages = array_map('trim',explode(',',$str));
            $p = noNS($id);
            foreach ($exc_pages as $exc)
                if ($p == $exc)
                    return false;
        }
        // Finally check if the language can be detected
        $lang = $this->getPageLanguage($id);
        return !empty($lang);
    }

    private static function isIdInNamespace($id, $ns) {
        return substr($id, 0, strlen($ns) + 1) === "$ns:";
    }

    /** Returns true if the current user is may translate the current page, false otherwise */
    private function hasPermissionTranslate() {
        global $INFO;
        // Assume that if we have EDIT on the current page, we may also create translation pages.
        if ($INFO['perm'] >= AUTH_EDIT) return true;
        $grp = $this->getConf('translator_group');
        return $grp && in_array($grp, $INFO['userinfo']['grps']);
    }

    /** Returns true if the current page is a translation, false otherwise. */
    public function isTranslation($id=null) {
        global $ID;
        if (is_null($id)) $id = $ID;
        return $this->getOriginal($id) != $id;
    }

    /** Returns the id of the original page */
    public function getOriginal($id=null) {
        global $INFO, $ID;
        if (is_null($id)) $id = $ID;
        $meta = $id!==$ID ? p_get_metadata($id) : $INFO['meta'];
        if (empty($meta['relation']['istranslationof'])) return $id;
        list ($orig) = array_keys($meta['relation']['istranslationof']);
        return $orig;
    }

    /**
     * Returns the ID of the current page translated in another language, or null if not existing.
     */
    public function translationLookup($language, $id=null) {
        global $INFO, $ID;
        if (is_null($id)) $id = $ID;
        $id = $this->getOriginal($id);
        $orig_lang = $this->getPageLanguage($id);
        if ($language == $orig_lang) return $id;
        $meta = $id!==$ID ? p_get_metadata($id) : $INFO['meta'];
        if (!isset($meta['relation']['translations'])) return null;
        foreach ($meta['relation']['translations'] as $tid => $tlang) {
            if ($tlang == $language && page_exists($tid)) return $tid;
        }
        return null;
    }

    /** returns HTML for a link to a translation or a page where it can be created */
    public function translationLink($language,$text='') {
        $langname = $this->getLanguageName($language);
        if ($text=='') {
            $text = $this->getConf('link_style') == 'langname' ? $langname : $language;
        }
        $current_lang = $this->getPageLanguage();
        $original_id = $this->getOriginal();
        $id = $this->translationLookup($language);
        if (isset($id)) {
            $url = wl($id);
            $class = 'wikilink1';
            $more = '';
        }
        else {
            $url = wl($original_id, array('do'=>'translate', 'to'=>$language));
            $class = 'wikilink2';
            $more = ' rel="nofollow"';
        }
        return '<a href="'.$url.'" class="'.$class.'" title="'.hsc($langname).'"'.$more.'>'.hsc($text).'</a>';
    }

    /** Returns HTML with translation links for all enabled languages */
    public function translationLinksAll() {
        global $INFO, $ID;
        if (!$INFO['exists']) return;
        if (!$this->isTranslatable()) return;
        $orig = $this->getOriginal();
        $origlang = $this->getPageLanguage($orig);

        // If no permission to translate, hide links to untranslated languages
        if ($this->hasPermissionTranslate()) {
            $langs = $this->getEnabledLanguages();
        } else {
            // Show only languages with existing translations
            $langs = array_values($this->getTranslations($orig));
            if (empty($langs)) return; // no translations exist
        }

        // Add link to the original language, if not present
        if (!in_array($origlang, $langs)) array_unshift($langs, $origlang);

        $out = '<div class="plugin_translate">'.DOKU_LF;
        $out .= '<ul>'.DOKU_LF;
        foreach ($langs as $lang) {
            $out .= '<li>'.DOKU_LF;
            $out .= '<div class="li">'.$this->translationLink($lang).'</div>'.DOKU_LF;
		    $out .= '</li>'.DOKU_LF;
        }
        $out .= '</ul>'.DOKU_LF;
        $out .= '</div>'.DOKU_LF;
        return $out;
    }

    /**
     * Returns HTML with links to all existing translations of the current
     * page and a link to create additional translations
     */
    public function translationLinksExisting() {
        global $INFO,$ID;
        if (!$INFO['exists']) return;
        if (!$this->isTranslatable()) return;
        $orig = $this->getOriginal();
        $origlang = $this->getPageLanguage($orig);
        $langs = array_values($this->getTranslations($orig));

        $has_permission_translate = $this->hasPermissionTranslate();

        if (count($langs) == 0 && !$has_permission_translate) return;

        // Add the original language if not present
        if (count($langs) > 0 && !in_array($origlang, $langs)) {
            array_unshift($langs, $origlang);
        }

        $out = '<div class="plugin_translate">'.DOKU_LF;
        $out .= '<ul>'.DOKU_LF;
        foreach ($langs as $lang) {
            $out .= '<li>'.DOKU_LF;
            $out .= '<div class="li">'.$this->translationLink($lang).'</div>'.DOKU_LF;
            $out .= '</li>'.DOKU_LF;
        }
        if ($has_permission_translate) {
            // "Translate this page" link
            $text = $this->getLang('translate_this_page');
            $url = wl($orig, 'do=translate');
            $link = '<a href="'.$url.'" class="translate" title="'.$text.'">'.$text.'</a>';
            $out .= '<li>'.DOKU_LF;
            $out .= '<div class="li">'.$link.'</div>'.DOKU_LF;
	        $out .= '</li>'.DOKU_LF;
        }
        $out .= '</ul>'.DOKU_LF;
        $out .= '</div>'.DOKU_LF;
        return $out;
    }

    /**
     * returns HTML with links to translations of the current page in all
     * available languages
     */
    public function translationLinks() {
        global $INFO;
        if (!$INFO['exists']) return;
        if (!$this->isTranslatable()) return;
        $langs = $this->getEnabledLanguages();
        if (count($langs) > 10) // FIXME configuration
            return $this->translationLinksExisting();
        else
            return $this->translationLinksAll();
    }

    /**
     * Returns an suggested ID of a translation of the current page,
     * in the same or another namespace, according to configuration
     */
    public function suggestTranslationId($title, $language, $from_language=null) {
        global $INFO;
        $ns = $INFO['namespace'];
        if ($this->getConf('use_language_namespace')) {
            if (!isset($from_language)) {
                $from_language = $this->getPageLanguage();
            }
            list ($ns1,$ns_tail) = explode(':',$ns,2);
            if ($ns1 === $from_language) {
                // replace language as first part of ns
                $ns = isset($ns_tail) ? $language.':'.$ns_tail : $language;
            }
            else {
                // prepend language to ns
                $ns = !empty($ns) ? $language.':'.$ns : $language;
            }
        }
        return (empty($ns) ? '' : $ns.':') . cleanID($title);
    }

    public function suggestPageId($title, $language) {
        return $this->getConf('use_language_namespace') ?
            $language.':'.cleanID($title) : cleanID($title);
    }

    /** Returns an array of language codes */
    public function getEnabledLanguages() {
        if (!isset($this->enabledLangs)) {
            $langs = $this->getConf('enabled_languages');
            if (empty($langs)) {
                // Not set. Use all DokuWiki's supported languages.
                $langs = array();
                if ($handle = opendir(DOKU_INC.'inc/lang')) {
                    while (false !== ($file = readdir($handle))) {
                        if ($file[0] == '.') continue;
                        if (is_dir(DOKU_INC.'inc/lang/'.$file))
                            array_push($langs,$file);
                    }
                    closedir($handle);
                }
                sort($langs);
            }
            else {
                $langs = array_map('trim', explode(',', strtolower($langs)));
            }
            $this->enabledLangs = $langs;
        }
        return $this->enabledLangs;
    }

    /**
     * do=translate
     */
    public function printActionTranslatePage() {
        global $ID, $INFO;
        $target_title = $_REQUEST['title'];
        $target_lang = $_REQUEST['to'];

        // Get source language form metadata or from namespace according to configuration
        $source_lang = $this->getPageLanguage();

        // Start of page
        echo $this->locale_xhtml('newtrans');

        if (!isset($source_lang)) {
            // Can't translate in this case. No form.
            return;
        }

        // build form
        $form = new Doku_Form('translate__plugin');
        $form->startFieldset($this->getLang('translation'));
        $form->addHidden('id',$ID);
        $form->addHidden('do','translate');

        $class = ''; // a class could be used on fields for form validation feedback (TODO)

        $form->addElement(form_makeTextField('',$this->getLanguageName($source_lang),
                                             $this->getLang('original_language'),
                                             '','',array('readonly'=>'readonly')));

        $langs = $this->getEnabledLanguages();
        $options = array(''=>'');
        foreach ($langs as $lang) {
            if ($lang == $source_lang) continue;
            $options[$lang] = $this->getLanguageName($lang);
        }
        $form->addElement(form_makeListboxField('to',$options,$target_lang,$this->getLang('translate_to'),'',$class));

        $form->addElement(form_makeTextField('',p_get_first_heading($ID),
                                             $this->getLang('original_title'),
                                             '','',array('readonly'=>'readonly')));

        $form->addElement(form_makeTextField('title',$target_title,$this->getLang('translated_title'),'',$class));
        $form->addElement(form_makeButton('submit','', $this->getLang('create_translation')));
        $form->printForm();
    }

    /**
     * do=createpage
     */
    public function printActionCreatepagePage() {
        global $INFO;

        // Start of page
        echo $this->locale_xhtml('newpage');

        // build form
        $form = new Doku_Form('translate__plugin');
        $form->startFieldset($this->getLang('create_new_page'));
        $form->addHidden('do','createpage');
        $class = '';
        $langs = $this->getEnabledLanguages();
        $options = array(''=>'');
        foreach ($langs as $lang) {
            if ($lang == $_REQUEST['lang']) continue;
            $options[$lang] = $this->getLanguageName($lang);
        }
        $form->addElement(form_makeListboxField('lang',$options,$_REQUEST['lang'],$this->getLang('language'),'',$class));
        $form->addElement(form_makeTextField('title',$_REQUEST['title'],$this->getLang('title'),'',$class));
        $form->addElement(form_makeButton('submit','', $GLOBALS['lang']['btn_create']));
        $form->printForm();
    }

    /**
     * Returns an associative of translations on the form page-id => language-code.
     */
    private function getTranslations($id) {
        global $INFO, $ID;
        $meta = $id === $ID ? $INFO['meta'] : p_get_metadata($id);
        if (empty($meta['relation']['translations'])) return array(); // no translations exist
        // Check if any of the translations have been deleted
        foreach ($meta['relation']['translations'] as $page_id => $lang) {
            if (!page_exists($page_id)) {
                unset($meta['relation']['translations'][$page_id]);
                $has_deleted = true;
            }
        }
        if ($has_deleted) {
            // Store the updated list of translations in metadata
            $set_metadata['relation']['translations'] = $meta['relation']['translations'];
            p_set_metadata($id, $set_metadata);
        }
        return $meta['relation']['translations'];
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
