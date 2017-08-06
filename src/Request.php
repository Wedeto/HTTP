<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace Wedeto\HTTP;

use Wedeto\Util\Date;
use Wedeto\Util\LoggerAwareStaticTrait;
use Wedeto\Util\Dictionary;

/**
 * Request encapsulates a HTTP request, containing all data transferrred to the
 * script by the client and the webserver.
 *
 * It will dispatch the request to the correct app by resolving the route in
 * the configured paths.
 */
class Request
{
    use LoggerAwareStaticTrait;

    /** The default language used for responses */
    private static $default_language = 'en';

    /** The time at which the request was constructed */
    protected $start_time;

    /** The HTTP version */
    protected $http_version = "1.1";

    /** The request method used for the current request: GET, POST etc */
    protected $method;
    
    /** The full request URL */
    protected $url;

    /** The URL from the webroot */
    protected $webroot;

    /** The server variables */
    protected $server;

    /** Whether the request was made using XMLHTTPRequest */
    protected $ajax;

    /** The GET parameters specified as query in the URL */
    protected $get;
    
    /** The arguments POST-ed to the script */
    protected $post;

    /** The storage for cookies sent by the client */
    protected $cookie;
    
    /** Accepted response types indicated by client */
	protected $accept;

    /** Accepted response languages indicated by client */
	protected $accept_language;

    /** Session storage */
    protected $session;

    /** Uploaded files */
    protected $files;

    /** The IP address of the client */
    protected $remote_ip;

    /** The hostname of the client */
    protected $remote_host;

    /** The language the response should use */
    protected $language;

    /** The path to the document root / the script containing the entry point */
    protected $docroot;

    public static function createFromGlobals()
    {
        return new Request($_GET, $_POST, $_COOKIE, $_SERVER, $_FILES);
    }

    /*** 
     * Create the request based on the request data provided by webserver and client
     *
     * @param array $get The GET parameters
     * @param array $post The POST parameters
     * @param array $cookie The COOKIE parameters
     * @param array $server The SERVER parameters
     */
    public function __construct(
        array &$get,
        array &$post,
        array &$cookie,
        array &$server,
        array &$files
    )
    {
        self::getLogger();
        $this->get = Dictionary::wrap($get);
        $this->post = Dictionary::wrap($post);
        $this->cookie = Dictionary::wrap($cookie);
        $this->server = Dictionary::wrap($server);

        $this->method = $this->server['REQUEST_METHOD'];
        $proto = $this->server->dget('SERVER_PROTOCOL', 'HTTP/1.1');
        if (preg_match('|HTTP/(\d+\.\d+)|', $proto, $matches))
            $this->http_version = $proto[1];

        $this->start_time = Date::createFromFloat($this->server->dget('REQUEST_TIME_FLOAT', time()));
        $this->setURLFromServerVars();
        $this->docroot = realpath($_SERVER['SCRIPT_FILENAME']);

        $this->ajax = 
            $this->server['HTTP_X_REQUESTED_WITH'] === 'xmlhttprequest' || 
            $this->get['ajax'] ||  $this->post['ajax'];

        $this->remote_ip = $this->server->get('REMOTE_ADDR');
        $this->remote_host = !empty($this->remote_ip) ? gethostbyaddr($this->remote_ip) : null;
        $this->accept = new Accept($this->server->dget('HTTP_ACCEPT', ''), Accept::ACCEPT_MIME);
        $this->accept_language = new Accept($this->server->dget('HTTP_ACCEPT_LANGUAGE', ''), Accept::ACCEPT_LANGUAGE);

        $this->files = FileUpload::parseFileArray($files);
    }

    /**
     * Determine the webroot and the URL from server variables. Webroot is
     * based on the location of the index.php that is executing, which we
     * consider to be the webroot.
     */
    public function setURLFromServerVars()
    {
        if ($this->server->get('REQUEST_SCHEME'))
        {
            $base = $this->server['REQUEST_SCHEME'] . '://' . $this->server['SERVER_NAME'];
            $this->url = new URL($base . $this->server['REQUEST_URI']);
            $this->webroot = new URL($base . rtrim(dirname($this->server->get('SCRIPT_NAME')), '/') . '/');
        }
        else
        {
            $this->url = new URL($this->server->get('REQUEST_URI'));

            $host = $this->server['SERVER_NAME'] ?: $this->url->host;
            $port = $this->server['SERVER_PORT'] ?: $this->url->port;
            $this->webroot = new URL('http://' . $host);
            if ($port && $port != 80)
                $this->webroot->port = $port;
        }
    }

    /**
     * @return DateTime The start of the script
     */
    public function getStartTime()
    {
        return $this->start_time;
    }
        
    /**
     * Set the Accept instance to modify the accepted response types
     * @param Accept $accept The accept instance
     * @return Request Provides fluent interface
     */
    public function setAccept(Accept $accept)
    {
        $this->accept = $accept;
        return $this;
    }
    
    /**
     * Start the HTTP Session, and initalize the session object
     * @param Wedeto\HTTP\Request Provides fluent interface
     */
    public function startSession(URL $domain, Dictionary $config)
    {
        if ($this->session === null)
        {
            $this->session = new Session($domain, $config, $this->server);
            $this->session->start();
        }
        return $this;
    }

    /** 
     * Get the HTTP session
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Check for accepted type. Forwards to Accept instance
     * 
     * @param string $mime The mime type to check
     * @return bool True if the mime type is accepted by the client, false
     * otherwise
     */
    public function accepts(string $mime)
    {
        return $this->accept->accepts($mime);
    }
    
    /**
     * Get a variable of the request. Magic method
     * to allow read-only access to properties.
     *
     * @param string $var The property to get
     * @throws BadMethodCallException When an invalid property is requested
     */
    public function __get(string $var)
    {
        if (!property_exists($this, $var))
            throw new \BadMethodCallException("Property does not exist: " . $var);
        return $this->$var;
    }

    /**
     * @return boolean True when the script is run from CLI, false if run from webserver
     */
    public static function cli()
    {
        return PHP_SAPI === "cli";
    }

}
