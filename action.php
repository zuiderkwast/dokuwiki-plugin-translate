<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Viktor Söderqvist <viktor@zuiderkwast.se>
 *
 * Translate - A translation plugin using Dublin Core metadata. No restriction to page IDs of the translations.
 *
 * Metadata keys used by this plugin are
 *
 * relation/istranslationof
 *      a list of source pages, normally only one (array: ID => language)
 * relation/translations
 *      a list of translation pages (array: ID => language)
 * language
 *      the language code of the page, 2-letter ISO code
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_LF')) define ('DOKU_LF',"\n");

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_translate extends DokuWiki_Action_Plugin {

    /**
     * register the eventhandlers
     */
    public function register(Doku_Event_Handler $contr) {
        $contr->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'handleDokuwikiStarted');
        $contr->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'handleTplActUnknown');
        $contr->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleActPreprocess');
        // Events for DW > 2020-07-29 "hogfather"
        $contr->register_hook('FORM_EDIT_OUTPUT', 'BEFORE', $this, 'handleHtmlEditformOutput', []);
        // Events for DW ≤ 2020-07-29 "hogfather"
        $contr->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'handleHtmlEditformOutput');
        // TODO: When a translation is deleted, delete it from the original's list of translations.
        //$contr->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, 'handlePageWrite');
        $contr->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'handleActRender');
    }

    /** Ensure translators' permission to edit the page */
    public function handleDokuwikiStarted($event, $param) {
        global $INFO;
        $info = & $INFO;

        // Is the user blocked from editing the page?
        if (empty($_SERVER['REMOTE_USER']) ||
            empty($INFO['meta']['relation']['istranslationof']) ||
            $info['perm'] < AUTH_READ ||
            $info['perm'] >= AUTH_EDIT) return;

        // Any translator group set?
        $grp = $this->getConf('translator_group');
        if (empty($grp)) return;

        // Is the current user member of that group?
        if (!in_array($grp, $info['userinfo']['grps'])) return;

        // Set permission to edit
        $info['perm'] = AUTH_EDIT;

        // Recreate writable and editable values (code from pageinfo in common.php)
        if($info['exists']){
            $info['writable'] = (is_writable($info['filepath']) &&
                                 ($info['perm'] >= AUTH_EDIT));
        }else{
            $info['writable'] = ($info['perm'] >= AUTH_CREATE);
        }
        $info['editable']  = ($info['writable'] && empty($info['lock']));
    }

    /** Insert translation links on top of page */
    public function handleActRender($event, $param) {
        if ($event->data != 'show') return;
        // show links to translations at top of page if that option is on.
        if ($this->getConf('insert_translation_links')) {
            $my = $this->loadHelper('translate',true);
            echo $my->translationLinks();
        }
    }

    /**
     * Hook for event ACTION_ACT_PREPROCESS, action 'translate'.
     *
     * Redirect to the translated page if there is one already.
     */
    public function handleActPreprocess($event, $param) {
        $act = $event->data;
        if (is_array($act)) {
            list($act) = array_keys($act);
        }
        switch ($act) {
            case 'createpage':
                $this->handleActPreprocessCreatepage($event, $param);
                break;
            case 'translate':
                $this->handleActPreprocessTranslate($event, $param);
                break;
            default:
                return; // not handled here
        }
        $event->preventDefault();
        $event->stopPropagation();
    }

    /**
     * Hook for event TPL_ACT_UNKNOWN, action 'translate'
     * Show page with translation form (before the translated page is created)
     */
    public function handleTplActUnknown($event, $param) {
        global $ID, $INFO; // $PRE, $TEXT, $SUF,
        if ($event->data == 'translate') {
            $my = $this->loadHelper('translate',true);
            $my->printActionTranslatePage();
        }
        elseif ($event->data == 'createpage') {
            $my = $this->loadHelper('translate',true);
            $my->printActionCreatepagePage();
        }
        else {
            return; // not handled here
        }
        $event->preventDefault();
        $event->stopPropagation();
    }


    /**
     * Hook for event HTML_EDITFORM_OUTPUT (DW ≤ 2020-07-29 "hogfather") and
     * event FORM_EDIT_OUTPUT (DW > 2020-07-29 "hogfather" )
     * Check and gather info on the untranslated page and the current form.
     * If needed, call the method that adds our elements to the form.
     */
    public function handleHtmlEditformOutput($event, $param) {
        global $INFO, $ID;

        // Original from meta
        if (empty($INFO['meta']['relation']['istranslationof'])) return;
        list ($id) = array_keys($INFO['meta']['relation']['istranslationof']);

        // Get original content
        $file = wikiFN($id);
        if (!@file_exists($file)) {
            msg('The original file for this translation does not exist. Perhaps it has been deleted.');
            return;
        }
        $origtext = io_readWikiPage($file,$id);

        // Insert original page on the side
        $form = $event->data;
        if (is_a($form, \dokuwiki\Form\Form::class)) {
            // DW > 2020-07-29 "hogfather"
            $pos = $form->findPositionByType('textarea');
            if ($pos===false) return;
            $this->handleHtmlEditformOutputNG($form, $pos, $origtext);
        } else {
            // DW ≤ 2020-07-29 "hogfather"
            $pos = $form->findElementByType('wikitext');
            if ($pos===false) return;
            $this->handleHtmlEditformOutputLegacy($form, $pos, $origtext);
        }
    }

    /**
     * Add our elements to the form. DW > 2020-07-29 "hogfather"
     */
    protected function handleHtmlEditformOutputNG($form, $pos, $origtext) {
        // Before the wikitext...
        $form->addTagOpen('div', $pos++)->id('wrapper__wikitext')->addClass('hor');
        // After the wikitext...
        $pos++;
        $form->addTagClose('div', $pos++);
        $form->addTagOpen('div', $pos++)->id('wrapper__sourcetext')->addClass('hor');

        $attrs=Array();
        $attrs['readonly']='readonly';
        $attrs['cols']=80;
        $attrs['rows']=10;
        $attrs['style']='width:100%;';
        $attrs['readonly']='readonly';
        $form->addTextarea('origWikitext', '', $pos++)->attrs($attrs)->val($origtext)
            ->id('translate__sourcetext')->addClass('edit');

        $form->addTagClose('div', $pos++);
        $form->addTagOpen('div', $pos++)->addClass('clearer');
        $form->addTagClose('div', $pos++);
    }

    /**
     * Add our elements to the form. DW ≤ 2020-07-29 "hogfather"
     */
    protected function handleHtmlEditformOutputLegacy($form, $pos, $origtext) {
        // Before the wikitext...
        $form->insertElement($pos++, form_makeOpenTag('div', array('id'=>'wrapper__wikitext','class'=>'hor')));
        // After the wikitext...
        $pos++;
        $form->insertElement($pos++, form_makeCloseTag('div'));
        $form->insertElement($pos++, form_makeOpenTag('div', array('id'=>'wrapper__sourcetext','class'=>'hor')));
        $origelem = '<textarea id="translate__sourcetext" '.
                    //buildAttributes($attrs,true).
                    'class="edit" readonly="readonly" cols="80" rows="10"'.
                    'style="width:100%;"'.//  height:600px; overflow:auto
                    '>'.NL.
                    hsc($origtext).
                    '</textarea>';
        $form->insertElement($pos++, $origelem);
        $form->insertElement($pos++, form_makeCloseTag('div'));
        $form->insertElement($pos++, form_makeOpenTag('div', array('class'=>'clearer')));
        $form->insertElement($pos++, form_makeCloseTag('div'));
    }

    public function handleActPreprocessCreatepage($event, $param) {
        global $INFO;
        $my = $this->loadHelper('translate',true);
        $title = $_REQUEST['title'];
        $lang = $_REQUEST['lang'];

        // Check input
        if (empty($title) || empty($lang)) {
            // Not filled. Show form.
            return;
        }

        // Illegal language
        if (!$my->languageExists($lang)) {
            msg(sprintf("Illegal language %s",$lang), -1);
            return;
        }

        $id = $my->suggestPageId($title, $lang);
        if (page_exists($id)) {
            // Error message
            //$this->_formErrors['title'] = 1;
            msg(sprintf($this->getLang['e_pageexists'], $title),-1);
            return;
        }

        // Check permission to create the page.
        $auth = auth_quickaclcheck($id);
        $auth_ok = ($auth >= AUTH_CREATE);
        if (!$auth_ok && $auth >= AUTH_READ) {
            // Check special translation permission
            // Is the current user member of the translator group?
            $grp = $this->getConf('author_group');
            $auth_ok = !empty($grp) && 
                       in_array($grp, $INFO['userinfo']['grps']);
        }
        if (!$auth_ok) {
            msg($this->getLang('e_denied'), -1);
            return;
        }

        // Create and save page
        $wikitext = "====== ".$title." ======".DOKU_LF.DOKU_LF;
        saveWikiText($id, $wikitext, $GLOBALS['lang']['created']); //$this->getLang('translation_created'));

        // Add metadata to the new page
        $file = wikiFN($id);
        $created = @filectime($file);
        $meta = array();
        $meta['date']['created'] = $created;
        $user = $_SERVER['REMOTE_USER'];
        if ($user) $meta['creator'] = $INFO['userinfo']['name'];
        $meta['language'] = $lang;
        p_set_metadata($target_id, $meta);

        // Redirect to edit the new page
        // Should we trigger some event before redirecting to edit?
        $url = wl($id, 'do=edit');
        send_redirect($url);
    }

    /** Handle translate action. Validates input and creates the translation page */
    public function handleActPreprocessTranslate($event, $param) {
        global $ID, $INFO;
        $my = $this->loadHelper('translate',true);
        $target_title = $_REQUEST['title'];
        $target_lang = $_REQUEST['to'];
        $source_lang = $my->getPageLanguage();

        // Check if this is the original
        if ($my->isTranslation()) {
            $orig_id = $my->getOriginal();
            $param = array('do'=>'translate','to'=>$target_lang);
            if ($target_title) $param['title'] = $target_title;
            $url = wl($orig_id, $param, false, '&');
            send_redirect($url);
            return;
        }

        // Check original language
        if (!isset($source_lang)) {
            // show error message and no form
            msg($this->getLang('e_languageunknown'),-1);
            return;
        }

        // Require target language
        if (empty($target_lang)) {
            // Not filled. Show form.
            return;
        }

        // Translate to same language? Just show page.
        if ($target_lang == $source_lang) {
            $event->data = 'show';
            return;
        }

        // Illegal language
        if (!$my->languageExists($target_lang)) {
            msg(sprintf("Illegal language %s",$target_lang), -1);
            return;
        }

        // Check existence of source page
        if (!page_exists($ID)) {
            // Just ignore and show "page does not exist".
            $event->data = 'show';
            return;
        }

        // Check if already translated
        $translated_id = $my->translationLookup($target_lang, $ID);
        if (!empty($translated_id)) {
            //$langname = $my->getLanguageName($target_lang);
            //msg(sprintf($this->getLang('e_translationexists'), $langname));

            // Redirect to already translated page
            $opts = array('id' => $translated_id, 'preact' => 'translate');
            trigger_event('ACTION_SHOW_REDIRECT',$opts,'act_redirect_execute');
            // This will redirect and exit.
        }

        // Require title
        if (empty($target_title)) {
            // Not filled. Show form.
            return;
        }

        // Check if target page exists
        $target_id = $my->suggestTranslationId($target_title, $target_lang, $source_lang);
        if (page_exists($target_id)) {
            // Error message
            //$this->_formErrors['title'] = 1;
            msg(sprintf($this->getLang('e_pageexists'), $target_id),-1);
            return;
        }

        // Check permission to create the page.
        $auth = auth_quickaclcheck($target_id);
        $auth_ok = ($auth >= AUTH_CREATE);
        if (!$auth_ok && $auth >= AUTH_READ) {
            // Check special translation permission
            // Is the current user member of the translator group?
            $grp = $this->getConf('translator_group');
            $auth_ok = !empty($grp) && 
                       in_array($grp, $INFO['userinfo']['grps']);
        }
        if (!$auth_ok) {
            msg($this->getLang('e_denied'), -1);
            return;
        }

        // Create and save page
        $wikitext = "====== ".$target_title." ======".DOKU_LF.DOKU_LF;
        saveWikiText($target_id, $wikitext, $this->getLang('translation_created'));
        
        // Add metadata to the new page
        $file = wikiFN($target_id);
        $created = @filectime($file);
        $meta = array();
        $meta['date']['created'] = $created;
        $user = $_SERVER['REMOTE_USER'];
        if ($user) $meta['creator'] = $INFO['userinfo']['name'];
        $meta['relation']['istranslationof'][$ID] = $source_lang;
        $meta['language'] = $target_lang;
        p_set_metadata($target_id, $meta);
        
        // Add metadata to the original
        $meta = array('relation' => array('translations' => array($target_id => $target_lang)));
        p_set_metadata($ID, $meta);

        // Redirect to edit the new page
        // Should we trigger some event before redirecting to edit?
        $url = wl($target_id, 'do=edit');
        send_redirect($url);
    }
}
// vim:ts=4:sw=4:et:
