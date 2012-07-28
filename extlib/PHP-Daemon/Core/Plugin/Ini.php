<?php

/**
 * Integrate an INI file into your daemon.
 * - Ensure daemon integrity by using the integrated validation: Pass in an array of required_sections that will be validated when the daemon starts.
 * - Implements the ArrayAccess interface so you can read and write to it as an array.
 * - Overload settings at runtime without changing the underlying Ini file.
 *
 * Pulling this functionality out of the base class for simplicity.
 *
 * @author Shane Harter
 * @since 2011/7/30
 *
 */
class Core_Plugin_Ini implements Core_IPlugin, ArrayAccess
{
    /**
     * This is the config file accessed by self::__construct
     * @var string
     */
    public $filename = 'settings.ini';

    /**
     * The existence of these sections in the Ini file will be validated at daemon startup.
     * @var Array
     */
    public $required_sections = array();

    /**
     * The contents of the Ini file
     * @var array
     */
    private $contents = array();

    /**
     * Called on Construct or Init
     * @return void
     */
    public function setup()
    {
        $this->contents = parse_ini_file($this->filename, true);

        $missing_sections = array();
        foreach ($this->required_sections as $section)
            if (isset($this->contents[$section]) && is_array($this->contents[$section]) && count($this->contents[$section]) > 0)
                continue;
            else
                $missing_sections[] = $section;

        if (count($missing_sections))
            throw new Exception(__METHOD__ . ' Failed: Seems the config file is missing required sections: ' . implode(',', $missing_sections));
    }

    /**
     * Called on Destruct
     * @return void
     */
    public function teardown()
    {
        $this->contents = null;
    }

    /**
     * This is called during object construction to validate any dependencies
     * @return Array    Return array of error messages (Think stuff like "GD Library Extension Required" or "Cannot open /tmp for Writing") or an empty array
     */
    public function check_environment()
    {
        $errors = array();

        if (file_exists($this->filename) == false)
            $errors[] = 'The Configured Ini file does not exist: ' . $this->filename;

        if (file_exists($this->filename) && is_readable($this->filename) == false)
            $errors[] = 'The Configured Ini file exists but is not readable: ' . $this->filename;

        return $errors;
    }

    /**
     * This is called after execute() is called each interval. Be cautious that anything coded here does not use up too much time in the interval.
     * @return void
     */
    public function run()
    {
        // Implement Plugin Interface
    }

    /**
     * @see ArrayAccess::offsetSet()
     * @param $offset
     * @param $value
     * @throws Exception
     */
    public function offsetSet($offset, $value)
    {
        if (is_scalar($offset))
            $this->contents[$offset] = $value;

        throw new Exception('Could not set INI value: $offset must be a scalar');
    }

    /**
     * @see ArrayAccess::offsetExists()
     * @param $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->contents[$offset]);
    }

    /**
     * @see ArrayAccess::offsetUnset()
     * @param $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->contents[$offset]);
    }

    /**
     * @see ArrayAccess::offsetGet()
     * @param $offset
     * @return null
     */
    public function offsetGet($offset)
    {
        return isset($this->contents[$offset]) ? $this->contents[$offset] : null;
    }
}