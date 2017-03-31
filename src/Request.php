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

    /** The request method used for the current request: GET, POST etc */
    public $method;
    
    /** The full request URL */
    public $url;

    /** The URL from the webroot */
    public $webroot;

    /** The server variables */
    public $server;

    /** Whether the request was made using XMLHTTPRequest */
    public $ajax;

    /** The GET parameters specified as query in the URL */
    public $get;
    
    /** The arguments POST-ed to the script */
    public $post;

    /** The storage for cookies sent by the client */
    public $cookie;
    
    /** Accepted response types indicated by client */
	public $accept = array();

    /** Session storage */
    public $session;

    /** The IP address of the client */
    public $remote_ip;

    /** The hostname of the client */
    public $remote_host;

    /** The language the response should use */
    public $language;

    /** The path to the document root / the script containing the entry point */
    public $docroot;

    public static function createFromGlobals()
    {
        return new Request(
            $_GET,
            $_POST,
            $_COOKIE,
            $_SERVER
        );
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
        array &$server
    )
    {
        self::getLogger();
        $this->get = Dictionary::wrap($get);
        $this->post = Dictionary::wrap($post);
        $this->cookie = Dictionary::wrap($cookie);
        $this->server = Dictionary::wrap($server);

        $this->method = $this->server['REQUEST_METHOD'];
        $this->start_time = Date::createFromFloat($this->server->dget('REQUEST_TIME_FLOAT', time()));
        $this->setUrlFromServerVars();
        $this->docroot = realpath($_SERVER['SCRIPT_FILENAME']);

        $this->ajax = 
            $this->server['HTTP_X_REQUESTED_WITH'] === 'xmlhttprequest' || 
            $this->get['ajax'] ||  $this->post['ajax'];

        $this->remote_ip = $this->server->get('REMOTE_ADDR');
        $this->remote_host = !empty($this->remote_ip) ? gethostbyaddr($this->remote_ip) : null;
        $this->accept = self::parseAccept($this->server->dget('HTTP_ACCEPT', ''));
    }

    /**
     * Determine the webroot and the URL from server variables. Webroot is
     * based on the location of the index.php that is executing, which we
     * consider to be the webroot.
     */
    protected function setUrlFromServerVars()
    {
        if ($this->server->get('REQUEST_SCHEME'))
        {
            $base = $this->server['REQUEST_SCHEME'] . '://' . $this->server['SERVER_NAME'];
            $this->url = new URL($base . $this->server['REQUEST_URI']);
            $this->webroot = new URL($base . dirname($this->server->get('SCRIPT_NAME')) . '/');
        }
        else
        {
            $this->url = new URL($this->server->get('REQUEST_URI'));
            $this->webroot = new URL("/");
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
        if ($this->session === null)
            $this->startSession();

        return $this->session;
    }

    /**
     * Check if the mime-type is accepted by the configured list
     * @param string $mime The mime type to match against the list of accepted
     * mime types
     * @return boolean True if the type is accepted, false if it is not
     */
    public function isAccepted($mime)
    {
        if (empty($this->accept))
            return true;

        foreach ($this->accept as $type => $priority)
        {
            if (strpos($type, "*") !== false)
            {
                $regexp = "/" . str_replace("WC", ".*", preg_quote(str_replace("*", "WC", $type), "/")) . "/i";
                if (preg_match($regexp, $mime))
                    return $priority;
            }
            elseif (strtolower($mime) === strtolower($type))
                return $priority;
        }
        return false;
    }

    /**
     * Check if a specified response type is accepted by the client, and if so,
     * sets it as the preferred response type. It is therefore assumed that if
     * you call this function and it returns true, you are going to output
     * that response type.
     *
     * @param string $mime The mime-type of the response that is to be checked
     * @param string $charset The character set / encoding use for output
     * @return boolean Whether the request accepts the mime-type as response
     */
    public function want($mime, $charset = null)
    {
        $priority = $this->isAccepted($mime);
        if ($priority === false)
            return false;
        if (!empty($charset))
            $mime .= "; charset=" . $charset;

        $this->mime = $mime;
        return $priority;
    }

    /**
     * Select the best response type to return from a list of available types.
     * @param array $types The types that the script is willing to return
     * @return string The mime type preferred by the client
     * 
     * @return string The mime-type of the preferred response type. Null if
     *                none of them are accepted
     */
    public function getBestResponseType(array $types)
    {
        $best_priority = null;
        $best_type = null;

        // Auto-fill mime-types when running from CLI.
        if (self::cli() && empty($this->accept))
            $this->accept = array('text/plain');

        foreach ($types as $type)
        {
            $priority = $this->isAccepted($type);
            if ($priority === false)
                continue;

            if ($best_priority === null || $priority > $best_priority)
            {
                $best_priority = $priority;
                $best_type = $type;
            }
        }

        return $best_type;
    }

    /**
     * Parse the HTTP Accept headers into an array of Type => Priority pairs.
     *
     * @param string $accept The accept header to parse
     * @return The parsed accept list
     */
    public static function parseAccept(string $accept)
    {
        if (empty($accept))
            $accept = "text/html";

		$accept = explode(",", $accept);
        $accepted = array();
		foreach ($accept as $type)
		{
			if (preg_match("/^([^;]+);q=([\d.]+)$/", $type, $matches))
			{
				$type = $matches[1];
				$prio = (float)$matches[2];
			}
			else
				$prio = 1.0;

			$accepted[$type] = $prio;
		}

        return $accepted;
    }

    /**
     * @return boolean Whether the request accepts JSON as response
     * @see Wedeto\Platform\Template::want
     */
    public function wantJSON()
    {
        return $this->want('application/json', 'utf-8');
    }

    /**
     * @return boolean Whether the request accepts HTML as response
     * @see Wedeto\Platform\Template::want
     */
    public function wantHTML()
    {
        return $this->want('text/html', 'utf-8');
    }

    /**
     * @return boolean Whether the request accepts HTML as response
     * @see Wedeto\Platform\Template::want
     */
    public function wantText()
    {
        return $this->want('text/plain', 'utf-8');
    }

    /**
     * @return boolean Whether the request accepts XML as response
     * @see Wedeto\Platform\Template::want
     */
    public function wantXML()
    {
        return $this->want('application/xml');
    }

    /**
     * Select the preferred reponse type based on a list of response types.
     * The response types should be provided in preference of the script, if any.
     * These are then matched with the accepted response types by the client,
     * and the most preferred one is selected. If more than one type is equally
     * desired by the client, the first one is selected.
     *
     * @param array $types The list of response types offered
     * @return string The preferred response type
     * @see Wedeto\HTTP\Request::want
     */
    public function chooseResponse(array $types)
    {
        $best = $this->getBestResponseType($types);

        // Set the mime-type to the best selected output
        $charset = (substr($best, 0, 5) == "text/") ? "utf-8" : null;
        $this->want($best, $charset);

        return $best;
    }


    /**
     * @return boolean True when the script is run from CLI, false if run from webserver
     */
    public static function cli()
    {
        return PHP_SAPI === "cli";
    }

}
