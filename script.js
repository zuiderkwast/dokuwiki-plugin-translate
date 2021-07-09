

/**
 * Fix the edit window size controls, for the translation view.
 */
var _initSizeCtl = initSizeCtl;
initSizeCtl = function(ctlid,edid){

    // typically 'size__ctl', 'wiki__text';
    _initSizeCtl(ctlid,edid);

    var trid = 'translate__sourcetext';

    if(!document.getElementById){ return; }

    var ctl      = $(ctlid);
    var textarea = $(trid);
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
        //if (console) console.debug(c);
        addEvent(c[0],'click',function(){sizeCtl(trid,100);});
        addEvent(c[1],'click',function(){sizeCtl(trid,-100);});
        addEvent(c[2],'click',function(){toggleWrap(trid);});
    }

    // add a button to switch split view
    var v = document.createElement('img');
    v.src = DOKU_BASE+'lib/plugins/translate/images/splitswitch.gif';
    ctl.style.width = '80px'; // add 20px to the container
    ctl.appendChild(v);
    addEvent(v,'click',function(){switchSplitView();});
};

function switchSplitView(){
    var edit = $('wrapper__wikitext');
    var orig = $('wrapper__sourcetext');
    if (!edit || !orig) { return; }
    if (edit.className == 'hor') {
        edit.className = orig.className = 'ver';
    } else if (edit.className == 'ver') {
        edit.className = orig.className = 'off';
    } else {
        edit.className = orig.className = 'hor';
    }
};

jQuery(function() {
    var $chkBox = jQuery('input[name="use_custom_id"]');
    var $idRow = jQuery('input[name="target_id"]').parent();
    if (!$chkBox.length) {
        return;
    }

    $idRow.hide();
    $chkBox.change(function() {
        $idRow.toggle();
    });
});

// vim:ts=4:sw=4:et:enc=utf-8:
