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

use Wedeto\Util\Type;
use Wedeto\Util\Dictionary;
use Wedeto\Util\Functions as WF;

use InvalidArgumentException;

class Nonce
{
    protected static $nonce_timeout = 300;
    protected static $nonce_parameter = "_nonce";

    /**
     * Change the amount of time before nonces expire
     * @param int $seconds The number of seconds before a nonce expires
     */
    public static function setNonceExpiresInSeconds(int $seconds)
    {
        self::$nonce_timeout = $seconds;
    }

    /**
     * @return int The amount of seconds before nonces time out
     */
    public static function getNonceExpiresInSeconds()
    {
        return self::$nonce_timeout;
    }

    /**
     * @param string $name The name for the nonce parameter, used by validateNonce
     */
    public static function setParameterName(string $name)
    {
        self::$nonce_parameter = $name;
    }

    /**
     * @return string The name used for the nonce parameter
     */
    public static function getParameterName()
    {
        return self::$nonce_parameter;
    }

    /**
     * Get a nonce for the specified action optionally including context
     * @param string $action The action to tie the nonce to
     * @param Session $session The session to get the session salt from
     * @param array $context The context required for the nonce to be validated
     * @return string The nonce that should be submitted as parameter nonce
     */
    public static function getNonce(string $action, Session $session, array $context = [], int $timestamp = null)
    {
        // When nonce are not allowed to be stored, the nonce is not actually 
        // used only once since it needs to be reproducable to be verifiable.
        // Therefore, it sent timestamped to the client.
        $timestamp = $timestamp ?? time();
        $hashable = $action . $timestamp . $session->getSessionSalt();
        foreach ($context as $key => $value)
        {
            if ($value === null)
                $value = "NULL";
            if (!is_scalar($value))
                throw new InvalidArgumentException("Context variables must be scalar");
            $hashable .= '&' . $key . '=' . $value;
        }

        $nonce = sha1($hashable) . '$' . $timestamp;
        return $nonce;
    }

    /**
     * Check if a nonce was posted and if it matches the data
     * @param Dictionary $arguments The POST arguments.
     * @param Session $session The session where the nonce data was stored
     * @return bool True when a nonce was posted and validated, false if a nonce was posted and rejected,
     *              null when no nonce was posted.
     */
    public static function validateNonce(string $action, Session $session, Dictionary $arguments, array $context = [])
    {
        // Check if a nonce was posted at all
        if (!$arguments->has(self::$nonce_parameter, Type::STRING))
        {
            // No nonce submitted, indeterminate outcome
            return null;
        }
        
        $context_values = [];
        foreach ($context as $key => $value)
        {
            if (is_int($key)) 
            {
                $key = $value;
                $value = $arguments->get($key);
            }
            $context_values[$key] = $value;
        }

        $nonce = $arguments->getString(self::$nonce_parameter);

        $parts = explode('$', $nonce);
        if (count($parts) !== 2)
            return false;

        list($hash, $timestamp) = $parts;

        // Check that the nonce has not expired yet
        $now = time();
        if ($timestamp > $now || $now - $timestamp  > self::$nonce_timeout)
            return false;

        // Generate the expected nonce
        $expected_nonce = self::getNonce($action, $session, $context_values, $timestamp);

        // Compare the generated nonce with the submitted nonce
        return $expected_nonce === $nonce;
    }
}
