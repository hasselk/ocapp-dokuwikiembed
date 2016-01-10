<?php

/**Main driver module for this app.
 *
 * @author Claus-Justus Heine
 * @copyright 2013-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  const SESSION_AUTHKEY = 'DokuWiki\\authHeaders';
  const RPCPATH = '/lib/exe/xmlrpc.php';
  const COOKIE_RE = '(?:KEY_)?(?:DokuWiki|DW)[a-zA-Z0-9]*'; ///< Cookies we want to capture

  private $dwProto;
  private $dwHost;
  private $dwPort;
  private $dwPath;  

  private $authHeaders; //!< Authentication headers returned by DokuWiki
  private $authCookies; //!< Authentication headers, cookies we send to DW

  public function __construct($location)
  {
    $url = Util::composeURL($location);

    $urlParts = parse_url($url);
    $this->dwProto = $urlParts['scheme'];
    $this->dwHost  = $urlParts['host'];
    $this->dwPort  = isset($urlParts['port']) ? ':'.$urlParts['port'] : '';
    $this->dwPath  = $urlParts['path'];

    $this->authHeaders = array();
    $this->authCookies = array();

    $sessionAuth = \OC::$server->getSession()->get(self::SESSION_AUTHKEY);
    if (is_array($sessionAuth)) {
      // cookies to be forwarded by us to the client
      $this->authHeaders = $sessionAuth;
      
      // cookies to be sent by us to DokuWiki
      $this->authCookies = array();
      foreach ($this->authHeaders as $cookieName => $cookieData) {
        $this->authCookies[$cookieName] = $cookieName.'='.$cookieData['value']; // already urlencoded
        //error_log(__METHOD__.' '.$cookieData['origin']);
      }
    }
  }

  /**Return the URL for use with an iframe or object tag
   */
  public function wikiURL()
  {
    return $this->dwProto.'://'.$this->dwHost.$this->dwPort.$this->dwPath;
  }

  private function updateAuthHeaders($responseHdr)
  {
    foreach ($responseHdr as $header) {
      if (preg_match('/^Set-Cookie:\s*('.self::COOKIE_RE.')\s*=\s*([^;]+)\s*;/i', $header, $match)) {
        $cookieName = $match[1];
        $cookieValue = $match[2];
        $this->authHeaders[$cookieName] = array(
          'value' => $cookieValue,
          'origin' => $header
          );
        $this->authCookies[$cookieName] = $cookieName.'='.$cookieValue; // already urlencoded
        //error_log(__METHOD__.' '.$header);
      }
    }
    \OC::$server->getSession()->set(self::SESSION_AUTHKEY, $this->authHeaders);
  }
  
  private function xmlRequest($method, $data)
  {
    // error_log(__METHOD__.' '.$method);
    // Generate the request
    $request = xmlrpc_encode_request($method, $data, array("encoding" => "UTF-8",
                                                           "escaping" => "markup",
                                                           "version" => "xmlrpc"));
    // Construct the header with any relevant cookies
    $httpHeader = "Content-Type: text/xml; charset=UTF-8".
      (empty($this->authCookies)
       ? ""
       : "\r\n"."Cookie: ".join("; ", $this->authCookies));

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
      $responseHdr = array();
    }

    $response = xmlrpc_decode($result);
    if (is_array($response) && xmlrpc_is_fault($response)) {
      \OCP\Util::writeLog(self::APP_NAME,
                          "Error: xlmrpc: $response[faultString] ($response[faultCode])",
                          \OCP\Util::ERROR);
      $this->authHeaders = array(); // nothing
      return false;
    }

    if ($method == "dokuwiki.login" ||
        $method == "dokuwiki.stickylogin" ||
        $method == "dokuwiki.logoff") {
      // Response _should_ be a single integer: if 0, login
      // unsuccessful, if 1: got it.
      if ($response == 1) {
        // fetch new auth headers
        $this->authHeaders = array();
        $this->authCookies = array();
        $this->updateAuthHeaders($responseHdr);
        \OCP\Util::writeLog(self::APP_NAME,
                            "XMLRPC method \"$method\" executed with success. Got cookies ".
                            print_r($this->authHeaders, true).
                            ". Sent cookies ".$httpHeader,
                            \OCP\Util::DEBUG);
        return true;
      } else {
        \OCP\Util::writeLog(self::APP_NAME,
                            "XMLRPC method \"$method\" failed. Got headers ".
                            print_r($responseHdr, true).
                            " data: ".$result.
                            " response: ".$response,
                            \OCP\Util::DEBUG);
        return false;
      }
    }

    $this->updateAuthHeaders($responseHdr);

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

  /**
   * Parse a cookie header in order to obtain name, value, date of
   * expiry and path.
   *
   * @parm cookieHeader Guess what
   *
   * @return Array with name, value, expires and path fields, or
   * false if $cookie was not a Set-Cookie header.
   *
   */
  static private function parseCookie($cookieHeader)
  {
    if (!preg_match('/Set-Cookie:\s*(.*)$/i', $cookieHeader, $match)) {
      return false;
    }
    $cookieData = $match[1];
    $cookieParts = preg_split('/\s*;\s*/', $cookieData);
    if (empty($cookieParts)) {
      return false;
    }
    $cookie = array();
    $nv = explode('=', array_shift($cookieParts));
    if (count($nv) == 1) {
      $nv[] = '';
    }
    $cookie['name'] = $nv[0];
    $cookie['value'] = $nv[1];

    foreach ($cookieParts as $part) {
      $nv = explode('=', $part);
      if (count($nv) == 1) {
        $nv[1] = true;
      }
      $attributes[$nv[0]] = $nv[1];
    }
    $cookie['attributes'] = $attributes;
    return $cookie;
  }

  /**
   * Normally, we do NOT want to replace cookies, we need two
   * paths: one for the RC directory, one for the OC directory
   * path. However: NGINX (a web-server software) on some
   * systems has a header limit of 4k, which is not much. At
   * least, if one tries to embed several web-applications into
   * RC by the same techniques which are executed here.
   *
   * This function tries to reduce the header size by replacing
   * cookies with the same name and path, but adding a new
   * cookie if name or path differs.
   *
   * @param cookieHeader The raw header holding the cookie.
   */
  static private function addCookie($cookieHeader)
  {
    $thisCookie = self::parseCookie($cookieHeader);
    $found = false;
    foreach(headers_list() as $header) {
      $cookie = self::parseCookie($header);
      if ($cookie === $thisCookie) {
        return;
      }
    }
    header($cookieHeader, false);
  }     

  /**Send authentication headers previously aquired
   */
  function emitAuthHeaders()
  {
    foreach ($this->authHeaders as $cookieName => $cookieData) {
      self::addCookie($cookieData['origin']);
    }
  }
};

} // namespace

?>
