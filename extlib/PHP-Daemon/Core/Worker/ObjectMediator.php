<?php
/**
 * Adapt a supplied object to the Worker Mediator
 *
 * Note: Any changes here need to be duplicated in Core_Worker_Debug_ObjectMediator.
 *       That sucks and will change once we release a version targeted for PHP 5.4 where we can use traits to hold
 *       the debug logic.
 *
 * @author Shane Harter
 */
final class Core_Worker_ObjectMediator extends Core_Worker_Mediator
{

    /**
     * @var Core_IWorker
     */
    protected $object;

    /**
     * The mediated $object's class
     * @var
     */
    protected $class;


    public function __destruct() {
        if (is_object($this->object))
            $this->object->teardown();
    }

    public function setObject($o) {
        if (!($o instanceof Core_IWorker)) {
            throw new Exception(__METHOD__ . " Failed. Worker objects must implement Core_IWorker");
        }
        $this->object = $o;
        $this->object->mediator = $this;
        $this->class = get_class($o);
        $this->methods = get_class_methods($this->class);
    }

    public function setup() {

        if (!$this->is_parent) {
            $this->object->setup();
        }
        parent::setup();
    }

    public function check_environment() {
        $errors = array();

        if (!is_object($this->object) || !$this->object instanceof Core_IWorker)
            $errors[] = 'Invalid worker object. Workers must implement Core_IWorker';

        $object_errors = $this->object->check_environment();
        if (is_array($object_errors))
            $errors = array_merge($errors, $object_errors);

        return parent::check_environment($errors);
    }

    protected function get_callback($method) {
        $cb = array($this->object, $method);
        if (is_callable($cb)) {
            return $cb;
        }

        throw new Exception("$method() is Not Callable.");
    }


    /**
     * Return an instance of $object, allowing inline (synchronous) calls that bypass the mediator.
     * Useful if you want to call methods in-process for some reason.
     * Note: Timeouts will not be enforced
     * Note: Your daemon event loop will be blocked until your method calls return.frr
     * @example Your worker object returns data from a webservice, you can put methods in the class to format the data.
     *          In that case you can call it in-process for brevity and convenience.
     * @example $this->DataService->inline()->pretty_print($result);
     * @return Core_IWorker
     */
    public function inline() {
        return $this->object;
    }
}

