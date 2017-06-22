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

use Wedeto\Util\Functions as WF;

/**
 * Parse accept headers and select appropriate response types
 */
class Accept
{
    const ACCEPT_MIME = "type";
    const ACCEPT_LANGUAGE = "lang";

    const JSON = "application/json";
    const CSS = "text/css";
    const JAVASCRIPT = "application/javascript";
    const JS = "application/javascript";
    const HTML = "text/html";
    const PLAIN = "text/plain";
    const TEXT = "text/plain";
    const XML = "application/xml";

    /** Accepted response types indicated by client */
	protected $accept = array();

    /**
     * Construct the accept header, parsing the provided string
     */
    public function __construct(string $accept, string $type = Accept::ACCEPT_MIME)
    {
        if ($type !== Accept::ACCEPT_MIME && $type !== Accept::ACCEPT_LANGUAGE)
            throw new \InvalidArgumentException("Invalid accept header type: " . $type);

        $this->type = $type;
        $this->accept = $this->parseAccept($accept, $this->type === Accept::ACCEPT_LANGUAGE);
    }

    /**
     * @return array The accepted response types. Keys of the array are 
     * types, the values are the quality indicator, between 0 and 1, 1 being
     * the most preferred.
     */
    public function getAccepted()
    {
        return $this->accept;
    }

    /**
     * Parse the HTTP Accept headers into an array of Type => Priority pairs.
     *
     * @param string $accept The accept header to parse
     * @return The parsed accept list
     */
    public static function parseAccept(string $accept, bool $is_lang)
    {
        // By default accept everything but prefer HTML
        if (empty($accept))
            $accept = "text/html;q=1.0,*/*;q=0.9";

        // Normalize locales when the intl extension is available
        $is_lang = $is_lang && class_exists('Locale', false);

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

            if ($is_lang)
                $type = \Locale::canonicalize($type);

			$accepted[$type] = $prio;
		}

        return $accepted;
    }

    /**
     * Check if a specified response type is accepted by the client. When
     * no types are specified, everything is accepted.
     *
     * @param string $type The type of the response that is to be checked
     * @return float Returns 0 if the type is not accepted, and otherwise the priority
     */
    public function accepts($type)
    {
        foreach ($this->accept as $accept_type => $priority)
        {
            if (strpos($accept_type, "*") !== false)
            {
                $regexp = "/" . str_replace("WC", ".*", preg_quote(str_replace("*", "WC", $accept_type), "/")) . "/i";
                if (preg_match($regexp, $type))
                    return $priority;
            }
            elseif (strtolower($type) === strtolower($accept_type))
                return $priority;
        }

        return 0;
    }

    /**
     * Compare the types based on the priorty in the accept header. Aliases
     * for common types are supported.
     *
     * @param string $l The left-hand side of the comparison. Should be a type
     * @param string $r The right-hand side of the comparison. Should be a type
     */
    public function compareTypes(string $l, string $r)
    {
        $lq = $this->accept[$l] ?? 0;
        $rq = $this->accept[$r] ?? 0;

        return $lq === $rq ? -1 : ($lq < $rq ? 1 : -1);
    }

    /** 
     * Sort a list by their accept priority
     * @param array $types The list to sort. For arrays with numeric keys, the
     *                     values are expected to be types. Otherwise,
     *                     the keys are expected to be types. The array is
     *                     passed by reference.
     * @return bool True on success, false on failure
     */
    public function sortTypes(array &$types)
    {
        if (WF::is_numeric_array($types))
            return usort($types, [$this, 'compareTypes']);

        return uksort($types, [$this, 'compareTypes']);
    }

    /**
     * Select the best response type to return from a list of available types.
     *
     * @param array $types The types that the script is willing to return
     * @return string The type preferred by the client
     */
    public function getBestResponseType(array $types)
    {
        if (empty($types))
            return "";

        $this->sortTypes($types);
        $type = reset($types);
        return $this->accepts($type) ? current($types) : null;
    }

    /**
     * Select the preferred reponse type based on a list of response types.
     * The response types should be provided in preference of the script, if any.
     * These are then matched with the accepted response types by the client,
     * and the most preferred one is selected. If more than one type is equally
     * desired by the client, the first one is selected.
     *
     * @param array $types The list of response types offered
     * @return string The preferred response
     * @see Wedeto\HTTP\Request::acept
     */
    public function chooseResponse(array $types)
    {
        if (empty($types))
            return null;

        $this->sortTypes($types);
        $first = reset($types);
        $type = key($types);

        return $this->accepts($type) ? $first : null;
    }


    /**
     * Wrapper to quickly access the accepts() function.  A call to Accept#xml()
     * will be translated to Accept#accepts('application/xml') based on the
     * defined class constants.
     * @param string $name The name of the method
     * @param array $arguments The arguments passed
     */
    public function __call(string $name, array $arguments)
    {
        $name = strtoupper($name);
        if (defined(static::class . '::' . $name))
            return $this->accepts(constant(static::class . '::' . $name));
        throw new \BadMethodCallException("Invalid response type: " . $name);
    }

    /**
     * @return string The string header of this Accept instance
     */
    public function __toString()
    {
        $parts = [];
        foreach ($this->accept as $type => $q)
        {
            if ($q < 1.0)
                $parts[] = sprintf("%s;q=%.1f", $type, $q);
            else
                $parts[] = $type;
        }
        return implode(",", $parts);
    }
};
