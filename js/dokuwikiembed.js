/**Embed a DokuWiki instance as app into ownCloud, intentionally with
 * single-sign-on.
 * 
 * @author Claus-Justus Heine
 * @copyright 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

var DWEmbed = DWEmbed || {
  appName: 'dokuwikiembed',
  refreshInterval: 300
};

(function(window, $, DWEmbed) {

  // Dummy, maybe more later

  DWEmbed.loadCallback = function(frame) {
    frame.contents().find('.logout').remove();
    frame.contents().find('li:empty').remove();
    frame.contents().find('form.btn_logout').remove();

    frame.contents().find('a').filter(function() {
      return this.hostname && this.hostname !== window.location.hostname;
    }).each(function() {
      $(this).attr('target','_blank');
    });

    $('#dokuwikiLoader').fadeOut('fast');
    frame.slideDown('fast');
  };

  /**Show the given wiki-page in a jQuery dialog popup. The page
   *name is sent to an Ajax callback which generates a suitable
   *iframe which then finally holds the wiki contents.
   *
   * @param wikiPage The DokuWiki page name.
   */
  DWEmbed.wikiPopup = function(wikiPage, popupTitle) {

    $.post(OC.filePath('dokuwikiembed', 'ajax', 'dokuwikiframe.php'),
	   {
	     wikiPage: wikiPage,
             popupTitle: popupTitle
	   },
           function (data) {
	     var containerId  = 'dokuwiki_popup';
	     var containerSel = '#'+containerId;
	     var container;
             if (data.status == 'success') {
	       container = $('<div id="'+containerId+'"></div>');
	       container.html(data.data.contents);
               $('body').append(container);
	       container = $(containerSel);
             } else {
               var info = '';
	       if (typeof data.data.message != 'undefined') {
	         info = data.data.message;
	       } else {
	         info = t('dokuwikiembed', 'Unknown error :(');
	       }
	       if (typeof data.data.error != 'undefined' && data.data.error == 'exception') {
	         info += '<p><pre>'+data.data.exception+'</pre>';
	         info += '<p><pre>'+data.data.trace+'</pre>';
	       }
	       OC.dialogs.alert(info, t('dokluwikiembed', 'Error'));
	       if (data.data.debug != '') {
                 OC.dialogs.alert(data.data.debug, t('dokuwikiembed', 'Debug Information'), null, true);
	       }
	       return false;
             }
             var popup = container.dialog({
               title: data.data.title,
               position: { my: "middle top+5%",
                           at: "middle bottom",
                           of: "#controls" },
               width: 'auto',
               //height: 'auto',
               modal: true,
               closeOnEscape: false,
               dialogClass: 'dokuwiki-page-popup',
               resizable: false,
               open: function() {
                 var dialogHolder = $(this);
                 var dialogWidget = dialogHolder.dialog('widget');

                 $('#dokuwikiFrame').load(function(){
                   DWEmbed.loadCallback($(this));
                   //this.style.width = 
                   //this.contentWindow.document.body.scrollWidth+20 + 'px';
                   this.style.height = 
                     this.contentWindow.document.body.scrollHeight + 'px';
                   var newHeight = dialogWidget.height()
                     - dialogWidget.find('.ui-dialog-titlebar').outerHeight();
                   //this.style.height = newHeight + 'px';
                   dialogHolder.height(newHeight);
                   dialogHolder.find('#docuwiki-container').height(newHeight);
                   this.height = newHeight + 'px';
                   //alert(this.contentWindow.document.body.scrollHeight + 'px'+"dialog: "+dialogWidget.height());
		   //dialogHolder.dialog('option', 'height', 'auto');
		   //dialogHolder.dialog('option', 'width', 'auto');
                 });
               },
               close: function() {
                 $('.tipsy').remove();
                 var dialogHolder = $(this);

                 dialogHolder.dialog('close');
                 dialogHolder.dialog('destroy').remove();
               },
             });
           });
    return true;
  };

})(window, jQuery, DWEmbed);

$(document).ready(function() {

  var wikiFrame = $('#dokuwikiFrame');

  if (wikiFrame.length > 0) {
    $(window).resize(function() {
      //fillWindow($('#dokuwiki_container'));
    });
    $(window).resize();
    
    $('#dokuwikiFrame').load(function(){
      DWEmbed.loadCallback($(this));
    });
  }

});
