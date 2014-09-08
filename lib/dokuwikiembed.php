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

class App
{
  const APP_NAME = 'dokuwikiembed';
  const RPCPATH = '/lib/exe/xmlrpc.php';

  private $dwProto;
  private $dwHost;
  private $dwPort;
  private $dwPath;  

  private $authHeaders; //!< Authentication headers returned by DokuWiki
  private $reqHeaders;  //!< Authentication headers, cookies we send to DW

  public function __construct($location)
  {
    $url = Util::composeURL($location);

    $urlParts = parse_url($url);
    $this->dwProto = $urlParts['scheme'];
    $this->dwHost  = $urlParts['host'];
    $this->dwPort  = isset($urlParts['port']) ? ':'.$urlParts['port'] : '';
    $this->dwPath  = $urlParts['path'];

    $this->authHeaders = array();

    // If we have cookies with AuthData, then store them in authHeaders
    $this->reqHeaders = array();
    foreach ($_COOKIE as $cookie => $value) {
      if (preg_match('/^(DokuWiki|DW).*/', $cookie)) {
        $this->reqHeaders[] = "$cookie=".urlencode($value);
      }
    }
  }

  /**Return the URL for use with an iframe or object tag
   */
  public function wikiURL()
  {
    return $this->dwProto.'://'.$this->dwHost.$this->dwPort.$this->dwPath;
  }

  private function cleanCookies()
  {
    $this->authHeaders = array();
    $this->reqHeaders = array();
    foreach ($_COOKIE as $cookie => $value) {
      if (preg_match('/^(DokuWiki|DW).*/', $cookie)) {
        unset($_COOKIE[$cookie]);
      }
    }
  }

  private function xmlRequest($method, $data)
  {
    // Generate the request
    $request = xmlrpc_encode_request($method, $data, array("encoding" => "UTF-8",
                                                           "escaping" => "markup",
                                                           "version" => "xmlrpc"));
    // Construct the header with any relevant cookies
    $httpHeader = "Content-Type: text/xml; charset=UTF-8".
      (empty($this->reqHeaders)
       ? ""
       : "\r\n"."Cookie: ".join("; ", $this->reqHeaders));

    // Compose the context with method, headers and data
    $context = stream_context_create(array('http' => array(
                                             'method' => "POST",
                                             'header' => $httpHeader,
                                             'content' => $request
                                             )));
    $url  = self::wikiURL().self::RPCPATH;
    $fp   = fopen($url, 'rb', false, $context);
    if ($fp !== false) {
      $result = stream_get_contents($fp);
      fclose($fp);
      $responseHdr = $http_response_header;
    } else {
      $result = '';
      $responseHdr = '';
    }

    $response = xmlrpc_decode($result);
    if (is_array($response) && xmlrpc_is_fault($response)) {
      \OCP\Util::writeLog(self::APP_NAME,
                          "Error: xlmrpc: $response[faultString] ($response[faultCode])",
                          \OC_Log::ERROR);
      $this->authHeaders = array(); // nothing
      return false;
    }

    if ($method == "dokuwiki.login" ||
        $method == "dokuwiki.stickylogin" ||
        $method == "dokuwiki.logoff") {
      // Response _should_ be a single integer: if 0, login
      // unsuccessful, if 1: got it.
      if ($response == 1) {
        $this->authHeaders = array();
        // Store and duplicate set cookies for forwarding to the users web client
        foreach ($responseHdr as $header) {
          if (preg_match('/^Set-Cookie:\s*(DokuWiki|DW).*/', $header)) {
            $this->authHeaders[] = $header;
            $this->authHeaders[] = preg_replace('|path=([^;]+);|i', 'path='.\OC::$WEBROOT.'/;', $header);
          }
        }
        \OCP\Util::writeLog(self::APP_NAME,
                            "XMLRPC method \"$method\" executed with success. Got cookies ".
                            print_r($this->authHeaders, true).
                            ". Sent cookies ".$httpHeader,
                            \OC_Log::DEBUG);
        return true;
      } else {
        \OCP\Util::writeLog(self::APP_NAME,
                            "XMLRPC method \"$method\" failed. Got headers ".
                            print_r($responseHdr, true).
                            " data: ".$result.
                            " response: ".$response,
                            \OC_Log::DEBUG);
        return false;
      }
    }
    
    return $result == '' ? false : $response;
  }  

  /**Perform the login by means of a RPCXML call and stash the cookies
   * storing the credentials away for later; the cookies are
   * re-emitted to the users web-client when the OC wiki-app is
   * activated. This login function itself is only meant for being
   * called during the login process.
   *
   * @param[in] $username Login name
   *
   * @param[in] $password credentials
   *
   * @return true if successful, false otherwise.
   */
  function login($username, $password)
  {
    $this->cleanCookies();
    if (!empty($_POST["remember_login"])) {
      $result = $this->xmlRequest("dokuwiki.stickylogin", array($username, $password));
      if ($result !== false) {
        return $result;
      }
    }
    // Fall back to "normal" login if long-life token could not be aquired.
    return $this->xmlRequest("dokuwiki.login", array($username, $password));
  }

  /**Logoff from (hacked) DokuWiki with added XMLRPC dokuwiki.logoff
   * call. For this to work we have to send the DokuWiki cookies
   * alongside the XMLRPC request.
   */
  function logout()
  {
    return $this->xmlRequest("dokuwiki.logoff", array());
  }

  /**Fetch the version from the DW instance in the hope that this also
   * touches the session life-time.
   */
  function version()
  {
    return $this->xmlRequest("dokuwiki.getVersion", array());
  }
  
  /**Rather a support function in case some other app wants to create
   * some automatic wiki-pages (e.g. overview stuff and the like, may
   * a changelog here and a readme there.
   */
  function putPage($pagename, $pagedata, $attr = array())
  {
    return $this->xmlRequest("wiki.putPage",
                             array($pagename, $pagedata, $attr));
  }  

  /**Rather a support function in case some other app wants to create
   * some automatic wiki-pages (e.g. overview stuff and the like, may
   * a changelog here and a readme there.
   */
  function getPage($pagename)
  {
    return $this->xmlRequest("wiki.getPage", array($pagename));
  }  


  /**Send authentication headers previously aquired
   */
  function emitAuthHeaders() 
  {
    foreach ($this->authHeaders as $header) {
      header($header, false /* replace or not??? */);
    }
  }
};

} // namespace

?>
