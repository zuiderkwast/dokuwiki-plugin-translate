

/**
 * Fix the edit window size controls, for the translation view.
 * This JS is supposed to run on ancient DW (as of 2022) and modern DW
 * See https://www.dokuwiki.org/devel:jqueryfaq#section.
 */
(function () {
    var initSizeCtlOrg;
    var initSizeCtlParent;
    let ancientP=false;
    if( typeof initSizeCtl === 'function' ) {
        //console.log( "initSizeCtl \\o/" );
        initSizeCtlOrg = initSizeCtl;
        initSizeCtlParent=window;
        ancientP=true;
    }
    else if( typeof dw_editor === 'object' && typeof dw_editor.initSizeCtl === 'function' ) {
        // compatibility with 2013-05-10a “Weatherwax” and newer
        //console.log( "dw_editor.initSizeCtl \\o/" );
        initSizeCtlOrg = dw_editor.initSizeCtl;
        initSizeCtlParent=dw_editor;
    }
    else {
        //console.log( 'No initSizeCtl /o\\' );
        // What DW version could it be ???
        return;
    }
    initSizeCtlParent.initSizeCtl = function(ctlid,edid){

        // typically 'size__ctl', 'wiki__text'; on old DW
        // typically '#size__ctl', '#wiki__text'; on binky +
        //console.log( "calling original function" );
        initSizeCtlOrg(ctlid,edid);
        //console.log( "original function done, going on" );
        if( ctlid[0] == '#' ) {
            // modern DW use jQuery selector '#some_id'. But to be compatible with old DW
            // we use getElementById which expects 'some_id'.
            //console.log( "ctlid starts with '#', removing first char " + ctlid );
            ctlid=ctlid.substr(1);
            //console.log( "new ctlid " + ctlid );
        }

        var trid = 'translate__sourcetext';

        if(!document.getElementById){
            console.log( "No getElementById. Translate plugin cannot adjust elements size :-(");
            return;
        }
        //console.log( "getElementById exists \\o/");


        var ctl      = document.getElementById(ctlid);
        var textarea = document.getElementById(trid);
        //console.log( "ctlid " + ctlid );
        //console.log( "ctl " + ctl );
        if(!ctl || !textarea) return;

        var hgt = DokuCookie.getValue('sizeCtl');
        if(hgt){
          textarea.style.height = hgt;
        }else{
          textarea.style.height = '300px';
        }

        var wrp = DokuCookie.getValue('wrapCtl');
        if(wrp){
          setWrap(textarea, wrp);
        } // else use default value

        // loop through the images in ctl
        var c = ctl.getElementsByTagName('img');
        if (c) {
            if( ancientP ) {
                //if (console) console.debug(c);
                addEvent(c[0],'click',function(){sizeCtl(trid,100);});
                addEvent(c[1],'click',function(){sizeCtl(trid,-100);});
                addEvent(c[2],'click',function(){toggleWrap(trid);});
            }
            else {
                jQuery(c[0]).click(function(){sizeCtl(trid,100);});
                jQuery(c[1]).click(function(){sizeCtl(trid,-100);});
                jQuery(c[2]).click(function(){toggleWrap(trid);});
            }
        }

        // add a button to switch split view
        var v = document.createElement('img');
        v.src = DOKU_BASE+'lib/plugins/translate/images/splitswitch.gif';
        ctl.style.width = '80px'; // add 20px to the container
        ctl.appendChild(v);
        if( ancientP ) {
            addEvent(v,'click',function(){switchSplitView();});
        }
        else {
            jQuery(v).click(function(){switchSplitView();});
        }
    };

    function switchSplitView(){
        var edit = document.getElementById('wrapper__wikitext');
        var orig = document.getElementById('wrapper__sourcetext');
        if (!edit || !orig) { return; }
        if (edit.className == 'hor') {
            edit.className = orig.className = 'ver';
        } else if (edit.className == 'ver') {
            edit.className = orig.className = 'off';
        } else {
            edit.className = orig.className = 'hor';
        }
    };
})();
// vim:ts=4:sw=4:et:
