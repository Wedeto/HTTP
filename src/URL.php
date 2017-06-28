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

/**
 * URL is a class that parses and modifies URLs. This
 * class is limited to a limited set of schemes - it
 * supports http, https and ftp.
 */
class URL implements \ArrayAccess
{
    private $scheme = null;
    private $port = null;
    private $username = null;
    private $password = null;
    private $host = null;
    private $path = null;
    private $query = null;
    private $fragment = null;

    /**
     * Create a URL from a string or another URL
     * @param string|URL $url The URL to parse
     * @param string $default_scheme The scheme to use when the URL doesn't have any
     */
    public function __construct($url = "", string $default_scheme = '')
    {
        if (empty($url))
            return;

        if ($url instanceof URL)
            $parts = $url;
        else
            $parts = self::parse($url, $default_scheme);

        foreach ($parts as $field => $value)
            $this->set($field, $value);
    }

    /**
     * Parse a URL
     * @param string $url The URL to parse
     * @param string $default_scheme A default scheme to use when no scheme is
     * used in the URL.
     */
    public static function parse(string $url, string $default_scheme = '')
    {
        if (empty($default_scheme) && !empty($_SERVER['REQUEST_SCHEME']))
            $default_scheme = $_SERVER['REQUEST_SCHEME'];
        elseif ($default_scheme === '')
            $default_scheme = 'http';

        $parts = parse_url($url);

        if ($parts === false)
            throw new URLException("Invalid URL: " . $url);

        if (empty($parts['scheme']) && substr($url, 0, 1) !== "/" && !empty($default_scheme))
        {
            $url = $default_scheme . "://" . $url;
            $parts = parse_url($url);
        }

        $scheme   = $parts['scheme'] ?? null;
        $username = $parts['user'] ?? null;
        $password = $parts['pass'] ?? null;
        $host     = $parts['host'] ?? null;
        $port     = $parts['port'] ?? null;
        $path     = $parts['path'] ?? '/';
        $query    = $parts['query'] ?? null;
        $fragment = $parts['fragment'] ?? null;

        $query = self::parseQuery($query);

        if (!$scheme && $host)
            $scheme = $default_scheme;

        // Scheme is case insensitive, to normalize to lowercase
        if ($scheme !== null)
            $scheme = strtolower($scheme);

        // Hostnames are case insensitive, to normalize to lowercase
        if ($host !== null)
            $host = strtolower($host);

        if (!empty($scheme) && !in_array($scheme, array('http', 'https', 'ftp')))
            throw new URLException("Unsupported scheme: '" . $scheme . "'");

        return array(
            'scheme' => $scheme,
            'username' => $username,
            'password' => $password,
            'host' => $host,
            'port' => $port,
            'path' => $path,
            'query' => $query,
            'fragment' => $fragment
        );
    }

    /**
     * Parse a query string or array
     * @param string|array $query The query to parse
     * @return array The associative array of key/value pairs. If an array was passed in,
     * it is returned as-is.
     */
    protected static function parseQuery($query)
    {
        if (is_array($query))
            return $query;

        if (empty($query))
            return null;

        if (!is_string($query))
            throw new \InvalidArgumentException('Query must be associative array or string');
        
        $query = ltrim($query, '?');
        $query = str_replace('&amp;', '&', $query);
        $parts = explode('&', $query);
        $values = [];
        foreach ($parts as $part)
        {
            $subparts = explode('=', $part, 2);
            if (count($subparts) === 1)
                $values[$part] = '1';
            else
                $values[$subparts[0]] = $subparts[1];
        }

        return empty($values) ? null : $values;
    }

    /**
     * Set the path of the URL. This will unset the currently set
     * fragment and query and overwrite them with whatever is appended in the path.
     * @param string $path The path to set. May include query and fragment
     * @return URL Provides fluent interface
     */
    public function setPath(string $path)
    {
        $path = '/' . ltrim($path, '/');
        $this->query = null;
        $this->fragment = null;
        if (preg_match('/^(.*?)(\\?([^#]*))?(#(.*))?$/u', $path, $matches))
        {
            $path = $matches[1];
            $this->query = !empty($matches[3]) ? $matches[3] : null;
            $this->fragment = !empty($matches[5]) ? $matches[5] : null;
        }

        $this->path = $path;
        return $this;
    }

    /**
     * @return string A string representation of the URL
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Convert a URL to a string.
     * @param bool $idn Whether to allow IDN names or convert them to punycode.
     * @return string A string representation of the URL
     */
    public function toString(bool $idn = false)
    {
        $o = "";
        if (!empty($this->scheme))
            $o .= $this->scheme . "://";
        
        if (!empty($this->username) && !empty($this->password))
            $o .= $this->username . ':' . $this->password . '@';

        // Check for IDN
        $host = $this->host;
        if ($idn && preg_match('/[^\x20-\x7f]/', $host))
            $host = \idn_to_ascii($host);
        $o .= $host;

        if (!empty($this->port))
        {
            if (!(
                ($this->scheme === "http"  && $this->port === 80) ||
                ($this->scheme === "https" && $this->port === 443) || 
                ($this->scheme === "ftp"   && $this->port === 21)
            ))
                $o .= ":" . $this->port;
        }

        $o .= $this->path;
        $query = $this->getQuery();

        if (!empty($query))
            $o .= '?' . $query;

        if (!empty($this->fragment))
            $o .= '#' . $this->fragment;

        return $o;
    }

    /**
     * Set the host name of the URL
     * @param string $hostname The hostname to set
     */
    public function setHost(string $hostname)
    {
        if (($ppos = strrpos($hostname, ":")) !== false)
        {
            $this->port = substr($hostname, $ppos + 1);
            $hostname = substr($hostname, 0, $ppos);
        }

        if (substr($hostname, 0, 4) === "xn--")
            $hostname = \idn_to_utf8($hostname);

        $this->host = strtolower($hostname);
        return $this;
    }

    // ArrayAccess implementation
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        return $this->set($offset, $value);
    }

    public function offsetExists($offset)
    {
        return property_exists($this, $offset);
    }

    public function offsetUnset($offset)
    {
        return $this->set($offset, null);
    }

    /**
     * Magic method accessor function
     * @param string $field The field to get
     * @return mixed The value for the field
     */
    public function __get(string $field)
    {
        return $this->get($field);
    }

    /**
     * Magic method setter function
     * @param string $field The field to set
     * @param mixed $value The value to set
     * @return URL Provides fluent interface
     */
    public function __set(string $field, $value)
    {
        return $this->set($field, $value);
    }

    /**
     * Set a field to a new value
     * @param string $field The field to set
     * @param mixed $value The value to update
     * @return URL Provides fluent interface
     */
    public function set(string $field, $value)
    {
        if ($value === null)
            $value = "";

        switch ($field)
        {
            case "scheme":
                $this->scheme = strtolower($value);
                return $this;
            case "port":
                $value = empty($value) ? null : (int)$value;
            case "username":
            case "password":
            case "fragment":
                $this->$field = $value;
                return $this;
            case "query":
                $this->query = $this->parseQuery($value);
                return $this;
            case "path":
                $this->setPath($value);
                return $this;
            case "host":
                return $this->setHost($value);
        }
        throw new \OutOfRangeException($field);
    }

    /**
     * Get the value for a field
     * @param string field The field to get
     * @return string The value for the field
     */
    public function get(string $field)
    {
        if ($field === "secure")
            return $this->scheme === "https";

        if ($field === "port" && $this->port === null)
        {
            if ($this->scheme === "http") return 80;
            if ($this->scheme === "https") return 443;
            if ($this->scheme === "ftp") return 21;
        }

        if ($field === "suffix")
            return $this->getSuffix();
                
        if ($field === "query")
            return $this->getQuery();

        if (property_exists($this, $field))
            return $this->$field;

        throw new \OutOfRangeException($field);
    }

    /**
     * @return string The query parameter. Will not be preceded by a ?
     */
    public function getQuery()
    {
        if (empty($this->query))
            return null;

        $parts = [];
        foreach ($this->query as $key => $value)
        {
            if (empty($value))
                continue;

            $parts[] = $key . '=' . urlencode($value);
        }

        return implode('&', $parts);
    }

    /** 
     * @return string Get the suffix / file extension used in the path. Null
     * when none available.
     */
    public function getSuffix()
    {
        $path = $this->path ?: " ";
        for ($i = strlen($path) - 1; $i >= 0; --$i)
        {
            $ch = substr($path, $i, 1);
            if ($ch === '/')
                break;
            
            $pch = $i > 0 ? substr($path, $i - 1, 1) : null;
            if ($ch === '.' && $pch !== '/')
                return substr($path, $i);
        }
        return null;
    }

    /**
     * Set or update a query variable
     * @param string $key The name of the query parameter
     * @param string $value The value for the parameter
     * @return URL Provides fluent interface
     */
    public function setQueryVariable(string $key, string $value)
    {
        if (empty($value))
            unset($this->query[$key]);
        else
            $this->query[$key] = $value;
        return $this;
    }

    /**
     * Get the value for a query variable
     * @param string $key The name of the query parameter
     * @return string The value for the parameter
     */
    public function getQueryVariable(string $key)
    {
        return $this->query[$key] ?? null;
    }
}
