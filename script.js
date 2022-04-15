/**
 * Fix the edit window size controls, for the translation view.
 */
(function () {
    var initSizeCtlOrg = dw_editor.initSizeCtl;
    dw_editor.initSizeCtl = function(ctlarea, editor) {
        // typically '#size__ctl', '#wiki__text'; on binky +

        initSizeCtlOrg(ctlarea, editor);

        var trid = '#translate__sourcetext';
        var $textarea = jQuery(trid);
        var $ctl      = jQuery(ctlarea);
        if(!$ctl.length || !$textarea.length) return;

        $textarea.css('height', DokuCookie.getValue('sizeCtl') || '300px');

        var wrp = DokuCookie.getValue('wrapCtl');
        if(wrp){
            dw_editor.setWrap($textarea[0], wrp);
        } // else use default value

        // loop through the images in $ctl
        var c=$ctl.find('img');
        jQuery(c[0]).on('click',function(){dw_editor.sizeCtl(trid,100);});
        jQuery(c[1]).on('click',function(){dw_editor.sizeCtl(trid,-100);});
        jQuery(c[2]).on('click',function(){dw_editor.toggleWrap(trid);});

        // add a button to switch split view
        jQuery(document.createElement('img'))
            .attr('src', DOKU_BASE+'lib/plugins/translate/images/splitswitch.gif')
            .attr('alt', '')
            .on('click', function(){switchSplitView();})
            .appendTo($ctl);
    };

    function switchSplitView(){
        var edit = document.getElementById('wrapper__wikitext');
        var orig = document.getElementById('wrapper__sourcetext');
        var cycle={ hor: 'ver', ver: 'off', off: 'hor'};
        if (!edit || !orig) { return; }
        edit.className=orig.className=cycle[edit.className];
    };
})();
// vim:ts=4:sw=4:et:
