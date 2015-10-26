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

use DWEMBED\L;
use DWEMBED\App;
?>

<div class="section">
  <h2><?php p(L::t('Embedded DokuWiki')) ?></h2>
  <!--
  <img class="svg dokuwikilogo" src="<?php echo OCP\Util::imagePath(App::APP_NAME, 'dokuwiki-logo.svg'); ?>" >
  -->
  <form id="dwembedsettings">
    <input type="text"
           name="DW_Location"
           id="DW_Location"
           value="<?php echo $_['wikilocation']; ?>"
           placeholder="<?php echo L::t('Location');?>"
           title="<?php echo L::t('Please enter the location of the already installed DokuWiki
instance. This should either be an abolute path relative to the
root of the web-browser, or a complete URL which points to the
web-location of the DokuWiki. In order to make things work your
have to enable the XMLRPC protocol in your DokuWiki.'); ?>"
    />
    <label for="DW_Location"><?php echo L::t('DokuWiki Location');?></label>
    <br/>
    <input type="text"
           name="DW_RefreshInterval"
           id="DW_RefreshInterval"
           value="<?php echo $_['wikiRefreshInterval']; ?>"
           placeholder="<?php echo L::t('Refresh Time [s]'); ?>"
           title="<?php echo L::t('Please enter the desired session-refresh interval here. The interval is measured in seconds and should be somewhat smaller than the con3figured session life-time for the DokuWiki instance in use.'); ?>"
    />
    <label for="DW_RefreshInterval"><?php echo L::t('DokuWiki Session Refresh Interval [s]'); ?></label>
    <br/>        
    <span class="msg"></span>
  </form>
</div>
