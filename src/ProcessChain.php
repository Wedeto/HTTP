<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2018, Egbert van der Wal

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
 * The HTTP request processing chain. A HTTP processing chain consists of three stages,
 * a pre-processing stage, a processing stage and a post-processing stage.
 *
 * STAGE_FILTER      - This is meant to manipulate the request in some way, for example to
 *                     reroute a request or perform authentication.
 * STAGE_PROCESS     - This is meant to produce a response based on the request
 * STAGE_POSTPROCESS - This is meant to adjust the response slightly
 *
 * While each of the stages have a envisioned purpose, nothing is preventing any of them
 * to do anything else. The main thing to keep in mind is that any pre-processing step or
 * processing step is able to throw a response rather than changing it. This is interpreted
 * as a end-of-processing signal in which case the stage is directly moved to postprocessing.
 *
 * Each processor of any stage must implement the Processor interface which requires one method:
 *
 * process(Request $request, Result $result);
 *
 * This method is expected to return nothing and make changes to either the request or the result,
 * or both.
 */
class ProcessChain
{
    const RUN_FIRST = -127;
    const RUN_DEFAULT = 0;
    const RUN_LAST = 127;

    const STAGE_FILTER = 0;
    const STAGE_PROCESS = 50;
    const STAGE_POSTPROCESS = 100;

    private $processors = [];
    private $seq = 0;

    /**
     * Add a filter to the chain in the PROCESS stage. Any operator in this stage is expected
     * to verify and/or alter the request in some way. If necessary, the filter may produce
     * a response by throwing it.
     *
     * @param Processor $processor The processor to add
     * @param int $precedence The precedence - the lower this value, the higher it will be put in the chain
     * @return $this Provides fluent interface.
     */
    public function addFilter(Processor $processor, int $precedence = ProcessChain::RUN_DEFAULT)
    {
        return $this->appendProcessor($processor, static::STAGE_FILTER, $precedence);
    }

    /**
     * Add a processor to the chain in the PROCESS stage. Any operator in this stage is expected
     * to produce a response.
     *
     * @param Processor $processor The processor to add
     * @param int $precedence The precedence - the lower this value, the higher it will be put in the chain
     * @return $this Provides fluent interface.
     */
    public function addProcessor(Processor $processor, int $precedence = ProcessChain::RUN_DEFAULT)
    {
        return $this->appendProcessor($processor, static::STAGE_PROCESS, $precedence);
    }

    /**
     * Adds a post processor to the pipeline. A postprocessor is a filter that
     * operates on the response rather than producing it. 
     *
     * @param Processor $processor The Processor to add
     * @param int $precedence The precedence - the lower this value, the higher it will be put in the chain
     * @return $this Provides fluent interface
     */
    public function addPostProcessor(Processor $processor, int $precedence = ProcessChain::RUN_DEFAULT)
    {
        return $this->appendProcessor($processor, static::STAGE_POSTPROCESS, $precedence);
    }

    /**
     * Helper to add a processor to the pipeline
     *
     * @param Wedeto\HTTP\Processor $processor The processor to add
     * @param int $stage The stage - STAGE_PREPROCESS, STAGE_PROCESS or STAGE_POSTPROCESS
     * @param int $precedence The precedence of the processor - the lower, the sooner it will be placed
     *                        in its stage.
     * @return $this Provides fluent interface
     */
    protected function appendProcessor(Processor $processor, int $stage, int $precedence = ProcessChain::RUN_DEFAULT)
    {
        $stage = WF::clamp($stage, static::STAGE_FILTER, static::STAGE_POSTPROCESS);
        $precedence = WF::clamp($precedence, static::RUN_FIRST, static::RUN_LAST);
        $this->processors[] = [
            'precedence' => $precedence, 
            'stage' => $stage, 
            'seq' => ++$this->seq, 
            'processor' => $processor
        ];

        // Sort the processors, first on stage, then on precedence, finally on order of appending
        usort($this->processors, function ($l, $r) {
            if ($l['stage'] != $r['stage'])
                return $l['stage'] - $r['stage'];
            if ($l['precedence'] != $r['precedence'])
                return $l['precedence'] - $r['precedence'];
            return $l['seq'] - $r['seq'];
        });
        return $this;
    }

    /**
     * Get a list of processor for a stage
     * @param int $stage The stage for which to get a list of processor
     * @return array The list of processor for the stage
     */
    public function getProcessors(int $stage)
    {
        $list = [];
        foreach ($this->processors as $proc)
        {
            if ($proc['stage'] === $stage)
                $list[] = $proc['processor'];
        }
        return $list;
    }

    /**
     * Process a request - put it through the pipeline and return the response. The request
     * will be routed through each processor until one throws an exception at which point the
     * stage is advanced to the post processing stage.
     *
     * @param Request $request The request to process
     * @return Result The produced response
     */
    public function process(Request $request)
    {
        $result = new Result;

        $stage = static::STAGE_FILTER;

        foreach ($this->processors as $processor)
        {
            if ($processor['stage'] < $stage)
                continue;

            $stage = $processor['stage'];
            $processor = $processor['processor'];
            try
            {
                $processor->process($request, $result);
            }
            catch (Response\Response $response)
            {
                $result->setResponse($response);
                $stage = static::STAGE_POSTPROCESS;
            }
        }

        return $result;
    }
}
