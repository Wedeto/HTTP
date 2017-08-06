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
use Wedeot\Util\Dictionary;
use Wedeto\Util\Functions as WF;

use InvalidArgumentException;

class Nonce
{
    protected static $nonce_timeout = 300000000;
    protected static $gc_executed = false;

    /**
     * Change the amount of time before nonces expire
     * @param int $seconds The number of seconds before a nonce expires
     */
    public static function setNonceExpiresInSeconds(int $seconds)
    {
        self::$nonce_timeout = $seconds * 1000000;
    }

    /**
     * Generate a nonce for the specified action, store it in the session and return the nonce.
     * @param string $action The action to tie the nonce to
     * @param Session $session The session to store the nonce data in
     * @param array $context The context required for the nonce to be validated
     * @return array Associative array 'nonce' and 'nonce_action' keys that should be added to a form
     */
    public static function getNonceValues(string $action, Session $session, array $context = [])
    {
        // Remove expired nonces
        self::gc();

        $now = microtime();
        $hashable = $action . $now . $session->getSessionID();
        foreach ($context as $key => &$val)
        {
            // Turn NULL into a scalar
            if ($val === null)
                $val = "";

            // Only accept scalar values
            if (!is_scalar($val))
                throw new InvalidArgumentException("Context variables must be scalar");

            // Avoid storing long context strings, reduce to first 16 characters
            if (is_string($val) && strlen($val) > 16)
                $val = substr($val, 0, 16);

            $hashable .= $key . '=' . $val;
        }
        $hash = sha1($hashable);

        if (!$session->has('nonce', $action, Type::ARRAY))
            $session->put('nonce', $action, []);

        $section = $session->get('nonce', $action);

        $nonce = [
            'timestamp' => $now,
            'action' => $action,
            'context' => $context
        ];

        $section->put($hash, $nonce);

        return [
            'nonce' => $hash,
            'nonce_action' => $action
        ];
    }

    /**
     * Check if a nonce was posted and if it matches the data
     * @param Dictionary $arguments The POST arguments.
     * @param Session $session The session where the nonce data was stored
     * @return bool True when a nonce was posted and validated, false if a nonce was posted and rejected,
     *              null when no nonce was posted.
     */
    public static function validateNonce(string $action, Dictionary $arguments, Session $session)
    {
        // Check if a nonce was posted at all
        if (
            !$arguments->has('nonce', Type::STRING) || 
            !$arguments->has('nonce_action', Type::STRING)
        )
        {
            // No nonce submitted, indeterminate outcome
            return null;
        }
        
        // Remove expired nonces
        self::gc();

        $nonce = $arguments->get('nonce');

        // Validate hash
        if (!$session->has('nonce', $nonce, Type::ARRAY))
            return false;
        
        $elements = $session->getSection('nonce', $nonce);
        // As the nonce is accessed now, it will expire directly, as
        // its either being used legitimatally or is being abused.
        unset($session['nonce'][$nonce]);

        // Validate action
        $nonce_action = $arguments->get('action');
        if ($nonce_action !== $action || $elements['action'] !== $action)
            return false; // Invalid action

        // Validate context
        foreach ($elements['context'] as $key => $value)
        {
            if (!$arguments[$key] !== $value)
                return false;
        }

        return true;
    }

    /**
     * Check all nonces stored in the session and remove nonces that
     * have expired.
     * @param Session $session The session where to remove expired nonces from
     */
    public static function gc(Session $session)
    {
        if (self::$gc_executed)
            return;

        $now = microtime();
        $threshold = $now - self::$nonce_timeout * 1000000;
        self::$gc_executed = true;
        $section = $session->getSection('nonce');
        foreach ($section as $hash => $nonce)
        {
            if ($nonce['timestamp'] < $threshold)
                unset($section[$hash]);
        }
    }
}
