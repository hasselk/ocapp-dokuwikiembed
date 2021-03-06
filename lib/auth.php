<?php

/**Main driver module for this app.
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

/**DWEMBED namespace to prevent name-collisions.
 */
namespace DWEMBED 
{

/**This class provides the two static login- and logoff-hooks needed
 * for authentication without storing passwords verbatim.
 */
class AuthHooks
{
  public static function login($params)
  {
    if (defined('DOKU_INC')) {
      return;
    }

    $wikiLocation = \OCP\Config::GetAppValue(App::APPNAME, 'wikilocation', '');

    $dokuWikiEmbed = new App($wikiLocation);
    $wikiURL = $dokuWikiEmbed->wikiURL();

    $username = $params['uid'];
    $password = $params['password'];

    if ($dokuWikiEmbed->login($username, $password)) {
      $dokuWikiEmbed->emitAuthHeaders();
      \OCP\Util::writeLog(App::APPNAME,
                          "DokuWiki login of user ".
                          $username.
                          " probably succeeded.",
                          \OC_Log::INFO);
    }
  }
  
  public static function logout()
  {
    if (defined('DOKU_INC')) {
      return;
    }

    $wikiLocation = \OCP\Config::GetAppValue(App::APPNAME, 'wikilocation', '');
    $dokuWikiEmbed = new App($wikiLocation);
    if ($dokuWikiEmbed->logout()) {
      $dokuWikiEmbed->emitAuthHeaders();
      \OCP\Util::writeLog(App::APPNAME,
                          "DokuWiki logoff probably succeeded.",
                          \OC_Log::INFO);
    }
  }

  /**Try to refresh the DW session by fetching the DW version via
   * XMLRPC.
   */
  public static function refresh() 
  {
    $wikiLocation = \OCP\Config::GetAppValue(App::APPNAME, 'wikilocation', '');
    $dokuWikiEmbed = new App($wikiLocation);
    $version = $dokuWikiEmbed->version();
    if ($version === false) {
        \OCP\Util::writeLog(App::APPNAME,
                            "DokuWiki refresh failed.",
                            \OC_Log::ERROR);
    } else {
        \OCP\Util::writeLog(App::APPNAME,
                            "DokuWiki@".$version." refresh probably succeeded.",
                            \OC_Log::INFO);
    }
  }
  

};

} // namespace

?>
