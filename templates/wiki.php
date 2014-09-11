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

$cssClass = 'dokuwiki-'.(isset($_['cssClass']) ? $_['cssClass'] : 'fullscreen');

?>

<div id="dokuwiki_container" class="<?php echo $cssClass; ?>">

<!-- <?php echo $_['wikiURL']; ?>  -->
<!-- <?php echo $_['wikiPath']; ?>  -->
<!-- <?php echo $_['debug']; ?>  -->
<!-- <?php print_r($_); ?> -->

  <img src="<?php echo \OCP\Util::imagePath($_['app'], 'loader.gif'); ?>" id="dokuwikiLoader" class="<?php echo $cssClass; ?>">
  <iframe style="display:none;overflow:auto"
          class="<?php echo $cssClass; ?>"
          src="<?php echo $_['wikiURL'].$_['wikiPath'];?>"
          id="dokuwikiFrame"
          name="dokuwikiembed"
          width="100%">
  </iframe>

</div>

