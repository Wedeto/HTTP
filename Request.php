<?php
/*
This is part of WASP, the Web Application Software Platform.
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

namespace WASP\HTTP;

use Throwable;
use DateTime;

use WASP\Log\LoggerAwareStaticTrait;
use WASP\Resolve\Resolver;
use WASP\Util\Dictionary;
use WASP\Platform\Path;
use WASP\Platform\Site;
use WASP\Platform\VirtualHost;
use WASP\Platform\TerminateRequest;
use WASP\Platform\AppRunner;
use WASP\Platform\Template;

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

    /** The accepted types by CLI scripts */
    public static $CLI_MIME = array(
        'text/plain' => 1.0,
        'text/html' => 0.9,
        'application/json' => 0.8,
        'application/xml' => 0.7,
        '*/*' => 0.5
    );

    /** If the error handler was already set */
    private static $error_handler_set = false;

    /** The current instance of the Request object */
    private static $current_request = null;

    /** The default language used for responses */
    private static $default_language = 'en';

    /** The time at which the request was constructed */
    protected $start_time;

    /** The site configuration */
    public $config;

    /** The path configuration */
    public $path;

    /** The server variables */
    public $server;

    /** The hostname used for the request */
    public $host;

    /** The full request URL */
    public $url;

    /** The URL from the webroot */
    public $webroot;

    /** The selected app path, based on the route */
    public $app;

    /** 
     * Suffix / file extension of the requested path. If this is not null, it
     * was removed from the route and app
     */
    public $suffix;

    /** The route specified on the URL */
    public $route;
    
    /** The query parameters specified in the URL */
    public $query;

    /** The protocol / scheme used to access the script */
    public $protocol;

    /** If https was used */
    public $secure;
    
    /** Whether the request was made using XMLHTTPRequest */
    public $ajax;

    /** The GET parameters specified as query in the URL */
    public $get;
    
    /** The arguments POST-ed to the script */
    public $post;

    /** The arguments after the selected route */
    public $url_args;

    /** The storage for cookies sent by the client */
    public $cookie;
    
    /** The request method used for the current request: GET, POST etc */
    public $method;
    
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

    /** The configured sites */
    public $sites = array();

    /** The selected VirtualHost for this request */
    public $vhost = null;

    /** The response builder */
    protected $response_builder = null;

    /** The file / asset resolver */
    protected $resolver = null;

    /** The template render engine */
    protected $template = null;

    /*** 
     * Create the request based on the request data provided by webserver and client
     *
     * @param array $get The GET parameters
     * @param array $post The POST parameters
     * @param array $cookie The COOKIE parameters
     * @param array $server The SERVER parameters
     * @param Path $path The path configuration
     * @param Dictionary $config The site configuration
     * @param Resolver $resolver The app, asset and template resolver
     */
    public function __construct(
        array &$get,
        array &$post,
        array &$cookie,
        array &$server,
        Dictionary $config,
        Path $path,
        Resolver $resolver
    )
    {
        $this->get = Dictionary::wrap($get);
        $this->post = Dictionary::wrap($post);
        $this->cookie = Dictionary::wrap($cookie);
        $this->server = Dictionary::wrap($server);
        $this->config = $config;
        $this->path = $path;
        $this->resolver = $resolver;

        self::$current_request = $this;
        $this->method = $this->server['REQUEST_METHOD'];
        $this->start_time = $this->server->dget('REQUEST_TIME_FLOAT', time());
        $this->setUrlFromServerVars();

        $this->ajax = 
            $this->server['HTTP_X_REQUESTED_WITH'] === 'xmlhttprequest' || 
            $this->get['ajax'] ||  $this->post['ajax'];

        $this->remote_ip = $this->server->get('REMOTE_ADDR');
        $this->remote_host = !empty($this->remote_ip) ? gethostbyaddr($this->remote_ip) : null;
        $this->accept = self::parseAccept($this->server->dget('HTTP_ACCEPT', ''));

        // Set up the site configuration
        $cfg = $this->config->getSection('site');
        $this->sites = Site::setupSites($cfg);
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
     * @return WASP\Platform\Template The current template renderer
     */
    public function getTemplate()
    {
        if ($this->template === null)
            $this->template = new Template($this);

        return $this->template;
    }

    /**
     * Set the template object
     * @param WASP\Platform\Template $tpl The template renderer to use
     * @return WASP\HTTP\Request Provides fluent interface
     */
    public function setTemplate(Template $tpl)
    {
        $this->template = $tpl;
        return $this;
    }

    /**
     * @return DateTime The start of the script
     */
    public function getStartTime()
    {
        return $this->start_time;
    }

    /**
     * @return WASP\HTTP\ResponseBuilder The response builder that will produce
     *                                   the final response to the client
     */
    public function getResponseBuilder()
    {
        if ($this->response_builder === null)
            $this->response_builder = new ResponseBuilder($this);
        return $this->response_builder;
    }

    /**
     * @return WASP\Autoload\Resolver The app, template and asset resolver
     */
    public function getResolver()
    {
        return $this->resolver;
    }

    /**
     * Set the resolver used for resolving apps
     * @param WASP\Resolve\Resolver The resolver
     * @return WASP\HTTP\Request Provides fluent interface
     */
    public function setResolver(Resolver $resolver)
    {
        $this->resolver = $resolver;
        return $this;
    }

    /**
     * Run the selected application
     */
    public function dispatch()
    {
        try
        {
            $this->resolveApp();
            $this->startSession();

            if ($this->route === null)
                throw new Error(404, 'Could not resolve ' . $this->url);

            $app = new AppRunner($this, $this->app);
            $app->execute();
        }
        catch (Throwable $e)
        {
            $rb = $this->getResponseBuilder();
            $session_cookie = $this->session !== null ? $this->session->getCookie() : null;
            if ($session_cookie)
                $rb->addCookie($session_cookie);
            $rb->setThrowable($e);
            $rb->respond();
        }
    }

    /**
     * Find out which VirtualHost was targeted and redirect if configuration
     * requests so.
     * @throws Throwable Various exceptions depending on the configuration - 
     *                   Error(404) when the configuration says to prohibit use of
     *                   unknown hosts, RedirectRequest when a redirect to a different
     *                   host is requested, RuntimeException when unexpected things happen.
     */
    protected function determineVirtualHost()
    {
        // Determine the proper VirtualHost
        $cfg = $this->config->getSection('site');
        $vhost = self::findVirtualHost($this->webroot, $this->sites);
        if ($vhost === null)
        {
            $result = $this->handleUnknownHost($this->webroot, $this->sites, $cfg);
            
            // Handle according to the outcome
            if ($result === null)
                throw new Error(404, "Not found: " . $this->url);

            if ($result instanceof URL)
                throw new RedirectRequest($result, 301);

            if ($result instanceof VirtualHost)
            {
                $vhost = $result;
                $site = $vhost->getSite();
                if (isset($this->sites[$site->getName()]))
                    $this->sites[$site->getName()] = $site;
            }
            else
                throw \RuntimeException("Unexpected response from handleUnknownWebsite");
        }
        else
        {
            // Check if the VirtualHost we matched wants to redirect somewhere else
            $target = $vhost->getRedirect($this->url);
            if ($target)
                throw new RedirectRequest($target, 301);
        }
        $this->vhost = $vhost;
    }

    /**
     * Start the HTTP Session, and initalize the session object
     */
    public function startSession()
    {
        if ($this->session === null)
        {
            $this->session = new Session($this->vhost->getHost(), $this->config, $this->server);
            $this->session->start();
        }
    }

    /** 
     * Resolve the app to run based on the incoming URL
     */
    public function resolveApp()
    {
        // Determine the correct vhost first
        $this->determineVirtualHost();

        // Resolve the application to start
        $path = $this->vhost->getPath($this->url);

        $resolved = $this->resolver->app($path);

        if ($resolved !== null)
        {
            if ($resolved['ext'])
            {
                $mime = ResponseTypes::getMimeFromExtension($resolved['ext']);
                if (!empty($mime))
                    $this->accept[$mime] = 1.5;
                $this->suffix = $resolved['ext'];
            }
            $this->route = $resolved['route'];
            $this->app = $resolved['path'];
            $this->url_args = new Dictionary($resolved['remainder']);
        }
        else
        {
            $this->route = null;
            $this->app = null;
            $this->url_args = new Dictionary();
        }
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
            $this->accept = self::$CLI_MIME;

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
     * From the list of availble responses, output the one that's preferred by
     * the client.  While it is of course possible to prepare all outputs
     * directly, a more efficient method is to provide a list of objects with
     * a __tostring() method that generates the response on demand.
     *
     * @param array $available A list of mime => output types.
     */
    public function outputBestResponseType(array $available)
    {
        $types = array_keys($available);
        $type = $this->getBestResponseType($types);
        
        if (!headers_sent())
        {
            // @codeCoverageIgnoreStart
            header("Content-type: " . $type);
            // @codeCoverageIgnoreEnd
        }
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
     * @see WASP\Platform\Template::want
     */
    public function wantJSON()
    {
        return $this->want('application/json', 'utf-8');
    }

    /**
     * @return boolean Whether the request accepts HTML as response
     * @see WASP\Platform\Template::want
     */
    public function wantHTML()
    {
        return $this->want('text/html', 'utf-8');
    }

    /**
     * @return boolean Whether the request accepts HTML as response
     * @see WASP\Platform\Template::want
     */
    public function wantText()
    {
        return $this->want('text/plain', 'utf-8');
    }

    /**
     * @return boolean Whether the request accepts XML as response
     * @see WASP\Platform\Template::want
     */
    public function wantXML()
    {
        return $this->want('application/xml');
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
     * Select the preferred reponse type based on a list of response types.
     * The response types should be provided in preference of the script, if any.
     * These are then matched with the accepted response types by the client,
     * and the most preferred one is selected. If more than one type is equally
     * desired by the client, the first one is selected.
     *
     * @param array $types The list of response types offered
     * @return string The preferred response type
     * @see WASP\HTTP\Request::want
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
     * Find the VirtualHost matching the provided URL.
     * @param URL $url The URL to match
     * @param array $sites A list of Site objects from which the correct
     *                     VirtualHost should be extracted.
     * @return VirtualHost The correct VirtualHost. Null if not found.
     */
    public static function findVirtualHost(URL $url, array $sites)
    {
        foreach ($sites as $site)
        {
            $vhost = $site->match($url);
            if ($vhost !== null)
                return $vhost;
        }
        return null;
    }

    /**
     * Determine what to do when a request was made to an unknown host.
     * 
     * The default configuration is IGNORE, which means that a new vhost will be
     * generated on the fly and attached to the site of the closest matching VirtualHost.
     * If no site is configured either, a new Site named 'defaul't is created and the new
     * VirtualHost is attached to that site. This makes configuration non-required for 
     * simple sites with one site and one hostname.
     * 
     * @param URL $url The URL that was requested
     * @param array $sites The configured sites
     * @param Dictionary $cfg The configuration to get the policy from
     * @return mixed One of:
     *               * null: if the policy is to error out on unknown hosts
     *               * URI: if the policy is to redirect to the closest matching host
     *               * VirtualHost: if the policy is to ignore / accept unknown hosts
     */
    public static function handleUnknownHost(URL $webroot, array $sites, Dictionary $cfg)
    {
        // Determine behaviour on unknown host
        $on_unknown = strtoupper($cfg->dget('unknown_host_policy', "IGNORE"));
        $best_matching = self::findBestMatching($webroot, $sites);

        if ($on_unknown === "ERROR" || ($best_matching === null && $on_unknown === "REDIRECT"))
            return null;

        if ($on_unknown === "REDIRECT")
        {
            $redir = $best_matching->URL($webroot->path);
            return $redir;
        }

        // Generate a proper VirtualHost on the fly
        $url = new URL($webroot);
        $url->fragment = null;
        $url->query = null;
        $vhost = new VirtualHost($url, self::$default_language);

        // Add the new virtualhost to a site.
        if ($best_matching === null)
        {
            // If no site has been defined, create a new one
            $site = new Site();
            $site->addVirtualHost($vhost);
        }
        else
            $best_matching->getSite()->addVirtualHost($vhost);

        return $vhost;
    }

    /**
     * Find the best matching VirtualHost. In case the URL used does not
     * match any defined VirtualHost, this function will find the VirtualHost
     * that matches the URL as close as possible, in an attempt to guess at
     * which information the visitor is interested.
     *
     * @param URL $url The URL that was requested
     * @param array $sites The configured sites
     * @return VirtualHost The best matching VirtualHost in text similarity.
     */ 
    public static function findBestMatching(URL $url, array $sites)
    {
        $vhosts = array();
        foreach ($sites as $site)
            foreach ($site->getVirtualHosts() as $vhost)
                $vhosts[] = $vhost;

        // Remove query and fragments from the URL in use
        $my_url = new URL($url);
        $my_url->set('query', null)->set('fragment', null)->toString();

        // Match the visited URL with all vhosts and calcualte their textual similarity
        $best_percentage = 0;
        $best_idx = null;
        foreach ($vhosts as $idx => $vhost)
        {
            $host = $vhost->getHost()->toString();
            similar_text($my_url, $host, $percentage);
            if ($best_idx === null || $percentage > $best_percentage)
            {
                $best_idx = $idx;
                $best_percentage = $percentage;
            }
        }
        
        // Return the best match, or null if none was found.
        if ($best_idx === null)
            return null;
        return $vhosts[$best_idx];
    }

    /**
     * @return boolean True when the script is run from CLI, false if run from webserver
     */
    public static function cli()
    {
        return PHP_SAPI === "cli";
    }

}

// @codeCoverageIgnoreStart
Request::setLogger();
// @codeCoverageIgnoreEnd
