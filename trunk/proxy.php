<?php

/******************************************************************************
 *  Copyright (c) 2008, dealnews.com, Inc.                                    *
 *  All rights reserved.                                                      *
 *                                                                            *
 *  Redistribution and use in source and binary forms, with or without        *
 *  modification, are permitted provided that the following conditions        *
 *  are met:                                                                  *
 *                                                                            *
 *   * Redistributions of source code must retain the above copyright         *
 *     notice, this list of conditions and the following disclaimer.          *
 *   * Redistributions in binary form must reproduce the above                *
 *     copyright notice, this list of conditions and the following            *
 *     disclaimer in the documentation and/or other materials provided        *
 *     with the distribution.                                                 *
 *   * Neither the name of dealnews.com, Inc. nor the names of its            *
 *     contributors may be used to endorse or promote products derived        *
 *     from this software without specific prior written permission.          *
 *                                                                            *
 *  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS       *
 *  "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT         *
 *  LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS         *
 *  FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE            *
 *  COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,       *
 *  INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES                  *
 *  (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR        *
 *  SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)        *
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,       *
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)             *
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED       *
 *  OF THE POSSIBILITY OF SUCH DAMAGE.                                        *
 *                                                                            *
 *****************************************************************************/

// current version
define("MEMPROXY", 0.1);



/**
 * Some things to be changed based on your preferences
 */


/**
 * Memcached caching layer
 *
 * Servers can be added to the array below as just IP
 * addresses or as an array.  If simple IP addresses are
 * used, default port and a weight of 1 will be applied.
 * If you wish to alter the default, you may list the
 * servers as an array.
 *
 * i.e. host, port, weight
 *
 * e.g.
 * array("10.1.1.1", 11222, 2),
 *
 */

$_MEMCACHE_SERVERS = array(
    "127.0.0.1"
);

/**
 * default ttl in seconds
 */
define("DEFAULT_TTL", 300);

/**
 * dead server retry ttl in seconds
 */
define("DEAD_RETRY", 30);






// change to show debug output
define("DEBUG", false);

// don't allow direct access
if(strstr($_SERVER["REQUEST_URI"], basename(__FILE__))) return;

// we don't accept file uploads
// if you want to do that, you will need to
// make it happen some other way
if(!empty($_FILES)) return;


/**
 * Initialize some state variables
 */

// $REFRESH is assumed false.
$REFRESH = false;

// assume we are caching
$NOCACHE = false;

// PHP will fully process HEAD requests
// However, we can not return the data
// and save something.
if($_SERVER['REQUEST_METHOD']=="HEAD"){
    $NODATA = true;
} else {
    $NODATA = false;
}


/**
 * Check for command on the URI
 *
 * Commands are placed at the begining of the path
 * for the URI.  e.g.
 *
 * To refresh /foo/bar.html, the URI would be
 *
 * /proxy:refresh/foo/bar.html
 *
 */

// simple security check. expand to fit your needs
// don't leave this wide open.  You have been warned!
// This checks for an IP on the local 10.1.* lan
if(DEBUG || strpos($_SERVER["REMOTE_ADDR"], "10.1.")==0){

    // check for commands on the request uri
    if(preg_match('!/proxy:(.+?)/!', $_SERVER["REQUEST_URI"], $match)){

        $_SERVER["REQUEST_URI"] = str_replace($match[0], "/", $_SERVER["REQUEST_URI"]);

        $command = $match[1];

        switch($command) {

            case "nodata":
                $NODATA = true;
                break;

            case "nocache":
                $NOCACHE = true;
                break;

            case "refresh":
                $REFRESH = true;
                break;

            case "refresh-nodata":
                $REFRESH = true;
                $NODATA = true;
                break;

            case "globals":
                if(DEBUG){
                    echo "<pre>";
                    print_r($GLOBALS);
                    echo "</pre>";
                    exit();
                }
                // don't break to fall through

            default:
                echo "Command not recognized: $command";
                header('HTTP/1.x 500 Internal Server Error');
                header('Status: 500 Internal Server Error');
                return;

        }

    }

}



/**
 * Load the memcached caching layer
 */

$_MEMCACHE = new Memcache();

foreach($_MEMCACHE_SERVERS as $server){
    if(is_array($server)){
        list($server, $port, $weight) = $server;
    } else {
        $port = 11211;
        $weight = 1;
    }

    $_MEMCACHE->addServer ( $server, $port, true, $weight );

}

$_MEMCACHE->setCompressThreshold(10240);


/**
 * A second caching option:
 *
 * Instead of memcached, load the xcache object that
 * emulates enough of the memcached interface.
 *
 * require_once "./contrib/XCache.php";
 *
 * $_MEMCACHE = new XCache;
 *
 */



/**
 * Start main script
 */

// get the path of the request
// This appears to be apache specific
$path = $_SERVER["REQUEST_URI"];

// deal with empty.  assume /
if(empty($path)){
    $path = "/";
}


//////////////////////////////////
// pre-fetch plugins
//  - put any code here that you want to run before the proxy starts its
//    requests.


// end plugins
//////////////////////////////////



// start magic
list($body, $content_type) = fetch_document($path, true);

//////////////////////////////////
// post-fetch plugins
//  - put any code here that you want to run after the data is returned


// end plugins
//////////////////////////////////


// set up some debug info
if(DEBUG){

    // add a header for cache status
    header("X-Cache-Status: $cache_status");

    // add debug information to the end of HTML documents
    if(strpos($body, "</body>")!==false){
        $body = str_replace("</body>", "Via MemProxy!<br />".nl2br($debug)."</body>", $body);
    }
}

// set the content type
header("Content-Type: ".$content_type);

// if this is not text content, disable compression
// this is conservative, but reliable
if(strpos($content_type, "text")===false){
    ini_set("zlib.output_compression", "0");
}


// if no data was requested (i.e. a HEAD request)
// don't send the data.  Otherwise, send it.
if(!$NODATA){
    echo $body;
}

// just return here to keep spaces
// and stuff before from messing us up
return;




/**
 * Fetches documents from cache or app servers
 *
 * @param string $path              the URI to fetch
 * @param bool   $primary_request   only should be set to true by the calling script
 *                                  sub requests will not be passed this value
 */

function fetch_document($path, $primary_request=false) {

    global $_MEMCACHE, $NODATA, $NOCACHE, $REFRESH, $debug, $cache_status;

    // init document
    $document = false;

    $page_key = "mem_proxy_".$_SERVER["HTTP_HOST"].$path;

    // if the key is over 250, we need to make it shorter as best we can
    if(strlen($page_key) > 250){
        $page_key = substr($page_key, 0, 210).sha1(substr($page_key, 211));
    }

    // only cache GET requests
    if($primary_request && $_SERVER["REQUEST_METHOD"]!="GET"){
        $NOCACHE = true;
    }

    // check if we are using cache
    if(!$NOCACHE && !$REFRESH){
        $document = $_MEMCACHE->get($page_key);
    }

    if($document!==false && is_array($document)){

        // cache hit - yay!

        if($primary_request){

            // just in case something goes wrong
            // reset out accessed time if it looks wrong
            if($document["accessed"] > time()){
                $document["accessed"] = time();
            }

            // calculate the cached time remaining
            $current_ttl = $document["proxy_ttl"] - (time() - $document["accessed"]);

            // if the time the item will remain in proxy cache
            // is greater than the document's non-proxy ttl,
            // send the non-proxy ttl to the browser
            if($current_ttl > $document["ttl"]){
                $current_ttl = $document["ttl"];
            }

            if(DEBUG) {
                $cache_status = "Cache Hit (ttl: ".$current_ttl." accessed: ".$document["accessed"]." orig ttl: ".$document["ttl"]." proxy ttl: ".$document["proxy_ttl"].")";
                $debug.= $cache_status;
            }

            // respect the If-Modified-Since header
            if(isset($_SERVER["HTTP_IF_MODIFIED_SINCE"])){
                $iftime = strtotime($_SERVER["HTTP_IF_MODIFIED_SINCE"]);
                if($document["accessed"] <= $iftime){
                    header('HTTP/1.x 304 Not Modified');
                    header('Status: 304 Not Modified');
                    return array("", $document["content-type"]);
                }
            }

            // send cache control headers
            header("Cache-Control: max-age=$current_ttl");
            header("Expires: ".gmdate("r", time()+$current_ttl));
            header("Last-Modified: ".gmdate("r", $document["accessed"]));

        }


    } else {

        // get from source server

        if($primary_request){
            $cache_status = "Cache Miss";
            if($NOCACHE || $REFRESH){
                $cache_status.= ": Skipped";
            } else {
                $cache_status.= ": Not Found";
            }
            if(DEBUG) $debug.= $cache_status;
        }

        // if this is the first request and a POST request,
        // add content type and make method POST
        if($_SERVER["REQUEST_METHOD"]=="POST" && $primary_request){
            $cache_status = "Cache Miss: Post Request";
            $content = file_get_contents('php://input');
            $method = "POST";
            $content_type = "application/x-www-form-urlencoded";
            $NOCACHE = true;
        } else {
            $method = "GET";
        }


        // open socket
        $fp = false;

        $fp = open_backend($errno, $errstr);

        if(!$fp){
            trigger_error("Could not contact source server for request {$_SERVER['REQUEST_URI']}", E_USER_WARNING);
            header('HTTP/1.x 504 Gateway Timeout');
            header('Status: 504 Gateway Timeout');
            exit();
        }

        // set the timeout to 5 seconds
        stream_set_timeout($fp, 5);

        // build our backend request
        $out = "$method $path HTTP/1.1\r\n";
        $out.= "Host: {$_SERVER['HTTP_HOST']}\r\n";
        $out.= "User-Agent: {$_SERVER['HTTP_USER_AGENT']} (MemProxy/".MEMPROXY.")\r\n";

        if(isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
            $out.= "X-Forwarded-For: {$_SERVER['HTTP_X_FORWARDED_FOR']}\r\n";
        } else {
            $out.= "X-Forwarded-For: {$_SERVER['REMOTE_ADDR']}\r\n";
        }

        if(isset($_SERVER["HTTP_REFERER"])){
            $out.= "Referer: {$_SERVER['HTTP_REFERER']}\r\n";
        }

        if(!empty($_COOKIE)){
            $out.= "Cookie: {$_SERVER['HTTP_COOKIE']}\r\n";
        }

        if($REFRESH || $NOCACHE){
            $out.= "Cache-Control: no-cache\r\n";
        }

        if(isset($content_type)){
            $out.= "Content-Type: $content_type\r\n";
        }

        if(isset($content)){
            $out.= "Content-Length: ".strlen($content)."\r\n";
        }

        if(isset($_SERVER["HTTP_ACCEPT"])){
            $out.= "Accept: ".$_SERVER["HTTP_ACCEPT"]."\r\n";
        }

        if(isset($_SERVER["HTTP_ACCEPT_CHARSET"])){
            $out.= "Accept-Charset: ".$_SERVER["HTTP_ACCEPT_CHARSET"]."\r\n";
        }

        if(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])){
            $out.= "Accept-Language: ".$_SERVER["HTTP_ACCEPT_LANGUAGE"]."\r\n";
        }

        $out.= "Connection: close\r\n\r\n";

        if(isset($content)){
            $out.=$content;
        }

        // write the request
        fwrite($fp, $out);


        // prep vars to hold return values
        $document = array("body" => "");
        $headers = array();
        $status = "";
        $cookies = array();

        // read headers
        $data = "";
        while($data!="\r\n") {
            $data = fgets($fp, 1024);
            if($data=="\r\n") break;
            if(empty($status)){
                // first line if the HTTP status
                $status = $data;
            } else {

                $split = strpos($data, ":");
                $key = trim(strtolower(substr($data, 0, $split)));
                $val = trim(substr($data, $split+1));

                // we have to handle cookies different
                // from other headers
                if($key=="set-cookie"){
                    $cookies[] = $val;
                } else {
                    $headers[$key] = $val;
                }
            }
        }

        if(isset($headers["transfer-encoding"]) && $headers["transfer-encoding"]=="chunked"){
            // read chunked data
            $data = fgets($fp, 1024);
            $size = hexdec(trim($data));
            while($size!=0){
                $buff = "";
                while(!feof($fp) && strlen($buff)<$size){
                    $buff.= fread($fp, $size - strlen($buff));
                }
                $document["body"].= $buff;
                $data = fgets($fp, 1024);
                $size = hexdec(trim($data));
            }
        } else {
            // read non-chunked data
            while (!feof($fp)) {
                $data = fgets($fp, 1024);
                $document["body"].= $data;
            }
        }

        // track the last time the document was read in from source
        $document["accessed"] = time();


        // process return code and make sure we send back the same thing
        preg_match("!HTTP/\d+\.\d+ ((\d+) [A-Za-z \-_]+)!", $status, $matches);

        $status_code = $matches[2];
        $status_text = $matches[1];

        // only cache 200 requests
        if($status_code!="200") {
            $NOCACHE = true;
        }

        // only send back the status of the primary request
        if($primary_request){
            header($status);
            header("Status: $status_text");
        }

        /**
         *  Just in case you come across this and ask "Why don't we just use header() here and
         *  send the Set-Cookie header?"  Well, it seems PHP, for security reasons, only allows
         *  the header() function to set one entry for a given header.  So, the last Set-Cookie
         *  call would be the only one sent.
         */
        foreach($cookies as $cookie){
            $expires = 0;
            $domain = null;
            $path = null;

            preg_match('!^(.+?)=([^;$]+)!', $cookie, $match);
            $name = $match[1];
            $val = $match[2];

            if(preg_match('!expires=([^;$]+)!', $cookie, $match)){
                $expires = strtotime($match[1]);
            }

            if(preg_match('!path=([^;$]+)!', $cookie, $match)){
                $path = $match[1];
            }

            if(preg_match('!domain=([^;$]+)!', $cookie, $match)){
                $domain = $match[1];
            }

            setcookie($name, $val, $expires, $path, $domain);
        }

        // process other headers returned from the back end server
        foreach($headers as $name=>$value){

            switch($name) {

                case "content-type":
                    if($primary_request){
                        $document["content-type"] = $value;
                    }
                    break;

                // store any cache control data for later use
                case "cache-control":
                    // See RFC 2616 -
                    // If a request includes the no-cache directive, it
                    // SHOULD NOT include min-fresh, max-stale, or max-age.
                    if(stripos($value, "no-cache")!==false){
                        $document["no-cache"] = true;
                        $ttl = 0;
                        $proxy_ttl = 0;
                    } elseif(preg_match('!max-age=(\d+)!i', $value, $match)){
                        $ttl = (int)$match[1];
                        $proxy_ttl = $ttl;
                    }
                    // should this be stored at all?  passed to client
                    if(stripos($value, "no-store")!==false){
                        $document["no-store"] = true;
                        $ttl = 0;
                        $proxy_ttl = 0;
                    } elseif(preg_match('!s-maxage=(\d+)!i', $value, $match)){
                        // RFC 2616 does not specify if s-maxage is valid or not
                        // when no-cache is set.  But, no-store is quite clear.
                        $proxy_ttl = (int)$match[1];
                    }
                    // is this cacheable for others?
                    if(strpos($value, "private")!==false){
                        $document["private"] = true;
                    }
                    break;

                // store any proxy directives for later use
                case "x-mem-proxy-prepend":
                    $document["prepend"] = explode(";", substr($value, strpos($value, ":")+1));
                    break;

                case "x-mem-proxy-append":
                    $document["append"] = explode(";", substr($value, strpos($value, ":")+1));
                    $document["append"] = array_reverse($document["append"]);
                    break;

                case "x-mem-proxy-embed":
                    $document["embed"] = explode(";", substr($value, strpos($value, ":")+1));
                    break;


                // if any Location headers are sent, we pass those through
                // don't send the Location header now, save it till the end
                case "location":
                    if($primary_request) {
                        $redirect = $value;
                        $NOCACHE = true;
                    }
                    break;
            }
        }

        // redirect now after all headers are sent
        if(isset($redirect)){
            header("Location: $redirect");
        }

        // if no ttl is provided, the content is assumed static for one hour
        if(!isset($ttl)) $ttl = DEFAULT_TTL;
        if(!isset($proxy_ttl)) $proxy_ttl = DEFAULT_TTL;

        // set cache status to indicate the page did not want to be cached
        if(DEBUG && $primary_request && $ttl==0){
            $cache_status = "Cache Miss: TTL 0";
        }

        // if $NOCACHE is set, then do not cache here or in browser
        if($NOCACHE){
            $ttl = 0;
        }

        // set document ttl in cache
        $document["ttl"] = $ttl;
        $document["proxy_ttl"] = $proxy_ttl;

        // send cache control header
        if($primary_request && $document["content-type"]){
            $cache_control = "Cache-Control: max-age=$ttl";
            if($document["no-cache"]) $cache_control.= ",no-cache";
            if($document["no-store"]) $cache_control.= ",no-store";
            if($document["private"]) $cache_control.= ",private";
            header($cache_control);
            header("Expires: ".gmdate("r", time()+$ttl));
            header("Last-Modified: ".gmdate("r"));
        }

        // if the page did not set its cache time to 0, put it in cache
        if($proxy_ttl>0 && !$NOCACHE){
            if(DEBUG){
                $cache_status.= " (ttl: $ttl)";
            }
            $success = $_MEMCACHE->set($page_key, $document, 0, $proxy_ttl);
            if(!$success){
                // failed sets do not remove previous values using this key
                // so, we need to remove any objects with this key that currenlty exist
                $_MEMCACHE->delete($page_key);
            }
        } elseif($ttl<=0 && $REFRESH) {
            $_MEMCACHE->delete($page_key);
        }

    }


    // look for embeded items first to prevent accidental replacement in other parts
    if(isset($document["embed"])){
        foreach($document["embed"] as $embed){
            list($replace, $src)=explode("|", trim($embed));
            $subdoc = fetch_document(trim($src));
            $document["body"] = str_replace(trim($replace), $subdoc, $document["body"]);
        }
    }

    // these come before
    if(isset($document["prepend"])){
        foreach($document["prepend"] as $prepend){
            $before.=fetch_document(trim($prepend));
        }
        $document["body"] = $before.$document["body"];
    }

    // these come after
    if(isset($document["append"])){
        foreach($document["append"] as $append){
            $after.=fetch_document(trim($append));
        }
        $document["body"] = $document["body"].$after;
    }

    // if this was the primary request, return the content type
    if($primary_request){
        return array($document["body"], $document["content-type"]);
    } else {
        return $document["body"];
    }
}


/**
 * Opens a connection to a server in the pool
 */
function open_backend(&$errno, &$errstr) {

    global $_MEMCACHE, $debug;

    $save_server_data = false;

    // get stored server data from memcached rather than disk
    $server_data = $_MEMCACHE->get("mem_proxy_backend_servers");

    // if it is not there, or the file on disk has changed, reload it.
    if($server_data===false || filemtime("./backend.php") > $server_data["load_time"]){

        // reparse the server array on disk and store in memcached

        $server_data = array();

        $server_data["load_time"] = time();

        include "./backend.php";

        $server_data["servers"] = $backend_array;

        $save_server_data = true;

    }

    $host = strtolower($_SERVER["HTTP_HOST"]);

    if(false !== ($pos=strpos($host, ":"))){
        $host = substr($host, 0, $pos);
    }

    if(!isset($server_data["servers"][$host])){
        $errno = -1;
        $errstr = "No backend servers listed for {$host}.";
        return false;
    }

    $pool = $server_data["servers"][$host];

    $fp = false;

    while(!$fp && count($pool)){

        $key = array_rand($pool);

        $server = $pool[$key];

        unset($pool[$key]);

        if(DEBUG) $debug.= "\nTrying server $server[0]:$server[1]";

        if(!isset($server[2]) || $server[2] < time() - DEAD_RETRY){

            $fp = @fsockopen($server[0], $server[1], $errno, $errstr, 5);

            if(!$fp){
                // mark server down in cached list
                // We put the current time in the 3rd part of the array
                // and check it for retry later
                $server_data["servers"][$host][$key][2]=time();
                $save_server_data = true;
            } elseif(isset($server[2])){
                unset($server_data["servers"][$host][$key][2]);
                $save_server_data = true;
            }
        }
    }

    if($save_server_data){
        $_MEMCACHE->set("mem_proxy_backend_servers", $server_data, 0, 0);
    }

    return $fp;
}



?>
