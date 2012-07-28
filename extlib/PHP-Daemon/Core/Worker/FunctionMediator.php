<?php
/**
 * Adapt a supplied function to the Worker Mediator class
 *
 * Note: Any changes here need to be duplicated in Core_Worker_Debug_FunctionMediator.
 *       That sucks and will change once we release a version targeted for PHP 5.4 where we can use traits to hold
 *       the debug logic.
 *
 * @author Shane Harter
 */
final class Core_Worker_FunctionMediator extends Core_Worker_Mediator
{
    /**
     * @var Core_IWorker
     */
    protected $function;

    /**
     * Set a function that will be executed asynchronously in the background. Given the alias "execute()" internally.
     * @param callable $f
     * @throws Exception
     */
    public function setFunction($f) {
        if (!is_callable($f)) {
            throw new Exception(__METHOD__ . " Failed. Supplied argument is not callable!");
        }
        $this->function = $f;
        $this->methods = array('execute');
    }

    protected function get_callback($method) {
        switch ($method) {
            case 'execute':
                return $this->function;
                break;

            case 'setup':
                return function() {

                };
                break;

            case 'teardown':
                $that = $this;
                return function() use ($that) {
                    $that->function = null;
                };
                break;

            default:
                throw new Exception("$method() is Not Callable.");
        }
    }

    public function inline() {
        return call_user_func_array($this->function, func_get_args());
    }
}
