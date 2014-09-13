<?php

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

use DWEMBED\App;
use DWEMBED\L;
use DWEMBED\Util;

$appName = App::APP_NAME;

OCP\JSON::callCheck();
OCP\App::checkAppEnabled($appName);

if (!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}

$debugText = "";

try {

  $debugText = print_r($_POST, true);

  $wikiLocation = OCP\Config::GetAppValue($appName, 'wikilocation', '');

  $dokuWikiEmbed = new App($wikiLocation);
  $wikiURL  = $dokuWikiEmbed->wikiURL();

  $wikiPage   = Util::cgiValue('wikiPage', '');
  $popupTitle = Util::cgiValue('popupTitle', '');
  $cssClass   = Util::cgiValue('cssClass', 'dokuwiki-popup');
  $attributes = Util::cgiValue('iframeAttributes', '');
  

  $dokuWikiEmbed->emitAuthHeaders();

  $tmpl = new OCP\Template($appName, "wiki");

  $tmpl->assign('app', $appName);
  $tmpl->assign('wikilocation', $wikiLocation);
  $tmpl->assign('wikiURL', $wikiURL);
  $tmpl->assign('wikiPath', '/doku.php?id='.$wikiPage);
  $tmpl->assign('cssClass', $cssClass);
  $tmpl->assign('iframeAttributes', $attributes);
  $tmpl->assign('debug', $debugText);

  $html = $tmpl->fetchPage();

  OCP\JSON::success(
    array('data' => array('contents' => $html,
                          'title' => $popupTitle,
                          'debug' => $debugText)));

  return true;

} catch (\Exception $e) {

  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'debugText' => $debugText,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception'))));
  return false;
  }


