<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2017-2018, Egbert van der Wal

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

namespace Wedeto\HTTP\Forms;

use Wedeto\Util\Dictionary;
use Wedeto\Util\Hook;
use Wedeto\Util\Functions as WF;
use Wedeto\HTTP\Nonce;
use Wedeto\Util\Validation\Type;

/**
 * A class that hooks into form preparation and validation to add a nonce to
 * every form created and submitted.
 */
class AddNonceToForm
{
    const WDI_REUSABLE = true;

    protected $prepare_hook = null;
    protected $validate_hook = null;

    /**
     * Construct the hook and register it
     */
    public function __construct()
    {
        $this->register();
    }

    /**
     * Subscribe to two hooks, prepare and isValid
     */
    public function register()
    {
        $this->prepare_hook = $this->prepare_hook ?: Hook::subscribe('Wedeto.HTTP.Forms.Form.prepare', [$this, 'hookPrepareForm']);
        $this->validate_hook = $this->validate_hook ?: Hook::subscribe('Wedeto.HTTP.Forms.Form.isValid', [$this, 'hookIsValid']);
    }

    /**
     * Unsubscribe from the two hooks
     */
    public function unregister()
    {
        if ($this->prepare_hook)
        {
            Hook::unsubscribe('Wedeto.HTTP.Forms.Form.prepare', $this->prepare_hook);
            $this->prepare_hook = null;
        }

        if ($this->validate_hook)
        {
            Hook::unsubscribe('Wedeto.HTTP.Forms.Form.isValid', $this->validate_hook);
            $this->validate_hook = null;
        }
    }

    /**
     * The hook called when the form is being prepared. Is used to
     * add the nonce field to the form.
     */
    public function hookPrepareForm(Dictionary $params)
    {
        $session = $params['session'];
        $form = $params['form'];
        if ($session !== null)
        {
            $nonce_name = Nonce::getParameterName();
            if (!isset($form[$nonce_name]))
            {
                $context = [];
                $nonce = Nonce::getNonce($form->getName(), $session, $context);
                $form->add(new FormField($nonce_name, Type::STRING, "hidden", $nonce));
            }
        }
    }

    /** 
     * The hook called when the form is validated. Is used to validate that the
     * received nonce is valid.
     */
    public function hookIsValid(Dictionary $params)
    {
        // Validate nonce
        $form = $params['form'];
        $request = $params['request'];
        $arguments = $params['arguments'];

        $result = Nonce::validateNonce($form->getName(), $request->session, $arguments);
        $nonce_name = Nonce::getParameterName();
        if ($result === null)
        {
            $params['errors'] = [$nonce_name => [[
                'msg' => 'Nonce was not submitted for form {form}',
                'context' => ['form' => $form->getName()]
            ]]];
            $params['valid'] = false;
        }
        elseif ($result === false)
        {
            $params['errors'] = [$nonce_name => [[
                'msg' => 'Nonce was invalid for form {form}',
                'context' => ['form' => $form->getName()] 
            ]]];
            $params['valid'] = false;
        }
    }
}
