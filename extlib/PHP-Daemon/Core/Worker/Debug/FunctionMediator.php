<?php
/**
 * Adapt a supplied function to the Worker Mediator class
 *
 * Note: The logic here is a copy of Core_Worker_FunctionMediator. This class is required because we want don't have
 * multiple inheritance, and we don't have traits/mixins until we move to PHP 5.4 later in 2012. This solution isn't
 * ideal but since this and the ObjectMediator are so simple, it really isn't tragic.
 *
 * @author Shane Harter
 */
final class Core_Worker_Debug_FunctionMediator extends Core_Worker_Debug_Mediator
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
}
