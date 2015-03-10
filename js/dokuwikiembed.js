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

  /**Unfortunately, the textare element does not fire a resize
   * event. This function emulates one.
   *
   * @param textarea jQuery descriptor for the textarea element
   *
   * @param delay Optional, defaults to 50. If true, fire the event
   * immediately, if set, then this is a delay in ms.
   * 
   *
   */
  DWEmbed.textareaResize = function(textarea, delay) {
    if (typeof delay == 'undefined') {
      delay = 50; // ms
    }
    textarea.off('mouseup mousemove');
    textarea.on('mouseup mousemove', function() {
      if (this.oldwidth  === null) {
        this.oldwidth  = this.style.width;
      }
      if (this.oldheight === null) {
        this.oldheight = this.style.height;
      }
      if (this.style.width != this.oldwidth || this.style.height != this.oldheight) {
        var self = this;
        if (delay > 0) {
          if (this.resize_timeout) {
            clearTimeout(this.resize_timeout);
        }
          this.resize_timeout = setTimeout(function() {
            $(self).resize();
          }, delay);
        } else {
          $(this).resize();
        }
        this.oldwidth  = this.style.width;
        this.oldheight = this.style.height;
      }
    });
  };

  /**Called after loading the DokuWiki has been loaded by the
   * iframe. We make sure that external links are opened in another
   * tab/window.
   */
  DWEmbed.loadCallback = function(frame, frameWrapper, callback) {
    var contents = frame.contents();

    contents.find('.logout').remove();
    contents.find('li:empty').remove();
    contents.find('form.btn_logout').remove();
    contents.find('#dokuwiki__usertools li.user').remove();
    contents.find('#dokuwiki__usertools a.action.profile').remove();

    // Make sure all external links are opened in another window
    contents.find('a').filter(function() {
      return this.hostname && this.hostname !== window.location.hostname;
    }).each(function() {
      $(this).attr('target','_blank');
    });

    // make sure that links in the preview pane are NOT followed.
    contents.find('div.preview').find('a[class^="wikilink"]').off('click').on('click', function() {
      var wikiPage = $(this).attr('href');
      wikiPage = wikiPage.replace(/^\/[^?]+\?id=(.*)$/, '$1');
      OC.dialogs.alert(t('dokluwikiembed', 'Links to wiki-pages are disabled in preview mode.'),
                       t('dokluwikiembed', 'Link to Wiki-Page') + ' "' + wikiPage + '"');
      return false;
    });

    contents.find('div.preview').find('a[class^="media"]').off('click').on('click', function() {
      var mediaPage = $(this).attr('href');
      mediaPage = mediaPage.replace(/^\/[^?]+\?id=(.*)$/, '$1');
      OC.dialogs.alert(t('dokluwikiembed', 'Links to media-files are disabled in preview mode.'),
                       t('dokluwikiembed', 'Link to Media') + ' "' + mediaPage + '"');
      return false;
    });

    if (typeof callback == 'undefined') {
      callback = function() {};
    }

    var loader = $('#dokuwikiLoader');
    if (frameWrapper.is(':hidden')) {
      loader.fadeOut('slow', function() {
        frameWrapper.slideDown('slow', function() {
          callback(frame, frameWrapper);
        });
      });
    } else {
      loader.fadeOut('slow');
      callback(frame, frameWrapper);
    }
  };

  /**Show the given wiki-page in a jQuery dialog popup. The page
   *name is sent to an Ajax callback which generates a suitable
   *iframe which then finally holds the wiki contents.
   *
   * @param options Object with the following components:
   * {
   *   wikiPage: 'page',
   *   popupTitle: 'title',
   *   modal: true/false
   * }
   * 
   * @param openCallback Optional callback to be call on open. The
   * callback will get the element holding the dialog content as
   * argument and the dialog widget itself. The callback is called BEFORE the iframe is loaded.
   */
  DWEmbed.wikiPopup = function(options, openCallback, closeCallback) {

    $.post(OC.filePath('dokuwikiembed', 'ajax', 'dokuwikiframe.php'),
	   {
	     wikiPage: options.wikiPage,
             popupTitle: options.popupTitle,
             cssClass: 'popup',
             iframeAttributes: 'scrolling="no"'
	   },
           function (data) {
	     var containerId  = 'dokuwiki_popup';
	     var containerSel = '#'+containerId;
	     var dialogHolder;
             if (data.status == 'success') {
	       dialogHolder = $('<div id="'+containerId+'"></div>');
	       dialogHolder.html(data.data.contents);
               $('body').append(dialogHolder);
	       dialogHolder = $(containerSel);
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
             var popup = dialogHolder.dialog({
               title: data.data.title,
               position: { my: "middle top",
                           at: "middle bottom+50px",
                           of: "#header" },
               width: 'auto',
               height: 'auto',
               modal: options.modal,
               closeOnEscape: false,
               dialogClass: 'dokuwiki-page-popup',
               resizable: false,
               open: function() {
                 var dialogHolder = $(this);
                 var dialogWidget = dialogHolder.dialog('widget');
                 var frameWrapper = dialogHolder.find('#dokuwikiFrameWrapper');
                 var frame        = dialogHolder.find('#dokuwikiFrame');
                 var titleHeight  = dialogWidget.find('.ui-dialog-titlebar').outerHeight();

                 if (typeof openCallback == 'function') {
                   openCallback(dialogHolder, dialogWidget);
                 }

                 frame.load(function(){
                   var self = this;
                   var contents = $(self).contents();

                   // Remove some more stuff. The popup is meant for a
                   // single page.
                   contents.find('#dokuwiki__header div.pad').remove();
                   contents.find('#dokuwiki__header').css('padding', '2.5em 0px 0px');

                   // <HACK REASON="determine the height of the iframe contents">
                   dialogHolder.height('');

                   var scrollHeight = self.contentWindow.document.body.scrollHeight;
                   frame.css({ height: scrollHeight + 'px',
                               overflow: 'hidden' });
                   if (frameWrapper.css('height') == '0px') {
                     frameWrapper.css({ height: 'auto',
                                        display: 'none' });
                   }
                   // </HACK>

                   DWEmbed.loadCallback(frame, frameWrapper, function() {
		     //dialogHolder.dialog('option', 'height', 'auto');
		     //dialogHolder.dialog('option', 'width', 'auto');
                     var newHeight = dialogWidget.height() - titleHeight;
                     dialogHolder.height(newHeight);

                     // Unfortunately, there is no resize event on
                     // textareas. We simulate one
                     var editArea = contents.find('textarea');
                     if (editArea.length > 0) {
                       DWEmbed.textareaResize(editArea);

                       editArea.on('resize', function() {
                         var scrollHeight = self.contentWindow.document.body.scrollHeight;
                         frame.css({ height: scrollHeight + 'px',
                                     overflow: 'hidden' });
                         dialogHolder.dialog('option', 'height', 'auto');
                         dialogHolder.dialog('option', 'width', 'auto');
                         var newHeight = dialogWidget.height() - titleHeight;
                         dialogHolder.height(newHeight);
                       });
                     }
                     
                   });
                 });
               },
               close: function() {
                 $('.tipsy').remove();
                 var dialogHolder = $(this);

                 dialogHolder.dialog('close');
                 dialogHolder.dialog('destroy').remove();

                 if (typeof closeCallback == 'function') {
                   closeCallback();
                 }

                 return false;
               }
             });
             return false;
           });
    return true;
  };

})(window, jQuery, DWEmbed);

$(document).ready(function() {

  var wikiContainer = $('#dokuwiki_container');
  var wikiFrame = $('#dokuwikiFrame');
  var dokuwiki = wikiFrame.contents();
  var frameWrapper = $('#dokuwikiFrameWrapper');

  var setHeightCallback = function() {
    wikiContainer.height($('#content').height());
  };

  if (wikiFrame.length > 0) {
    wikiFrame.load(function(){
      DWEmbed.loadCallback($(this), frameWrapper, setHeightCallback);
    });

    var resizeTimer;
    $(window).resize(function()  {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(setHeightCallback);
    });
  }
  if (dokuwiki.find('.logout')) {
    DWEmbed.loadCallback(wikiFrame, frameWrapper, setHeightCallback);
  }

  
});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
