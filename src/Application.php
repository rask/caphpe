<?php

namespace Caphpe;

use Caphpe\Cache\Pool;

/**
 * Class Application
 *
 * @since 0.1.0
 * @package Caphpe
 */
class Application
{
    /**
     * CLI arguments handler.
     *
     * @since 0.1.0
     * @access protected
     * @var \Caphpe\Cli\Arguments
     */
    public $configuration;

    /**
     * Cache pools.
     *
     * @since 0.1.0
     * @access protected
     * @var \Caphpe\Cache\Pool[]
     */
    protected $pools = [];

    /**
     * Application constructor.
     *
     * Create default cache pool.
     *
     * @since 0.1.0
     * @return void
     */
    public function __construct(Cli\Arguments $arguments)
    {
        $this->configuration = $arguments;
        $this->pools['default'] = new Pool();

        $startupMsg = vsprintf(
            'Started new Caphpe (version @package_version@) instance on %s:%s',
            [
                $this->configuration->getOption('host'),
                $this->configuration->getOption('port')
            ]
        );

        $this->stdout($startupMsg);
    }

    /**
     * Get pool by key, defaults to `default` pool.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $key Pool key.
     *
     * @return \Caphpe\Cache\Pool
     */
    protected function getPool($key = 'default')
    {
        return $this->pools[$key];
    }

    /**
     * Handle a request which was made against the application socket.
     *
     * @since 0.1.0
     *
     * @param string $request The request data.
     *
     * @return string
     */
    public function handleRequest($request)
    {
        $this->stdout('Doing request: ' . $request, 3);

        $valid = $this->validateCommand($request);

        if (!$valid) {
            return 'Invalid command';
        }

        return $this->doCommand($request);
    }

    /**
     * Validate that a request is a valid command to use in the application.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $request Request to validate.
     *
     * @return bool
     */
    protected function validateCommand($request)
    {
        $available = [
            'add ',
            'set ',
            'delete ',
            'replace ',
            'increment ',
            'decrement ',
            'get ',
            'has ',
            'flush',
            'status'
        ];

        foreach ($available as $cmd) {
            if (stripos($request, $cmd) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that arguments given to a command are formatted correctly.
     *
     * @param string $command The command issued.
     * @param string $arguments Given arguments for command.
     *
     * @return bool
     */
    protected function validateArguments($command, $arguments)
    {
        $arguments = trim($arguments);

        $map = [
            'get'       => '%^[^ ]+$%sui', // <key>
            'has'       => '%^[^ ]+$%sui', // <key>
            'delete'    => '%^[^ ]+$%sui', // <key>
            'set'       => '%^[^ ]+ +((s|b|i)\|)?.+( +[0-9]+)?$%sui', // <key> <type>?<value> <timeout>?
            'add'       => '%^[^ ]+ +((s|b|i)\|)?.+( +[0-9]+)?$%sui', // <key> <type>?<value> <timeout>?
            'replace'   => '%^[^ ]+ +((s|b|i)\|)?.+( +[0-9]+)?$%sui', // <key> <type>?<value> <timeout>?
            'increment' => '%^[^ ]+( +[0-9]+)?$%sui', // <key> <timeout>?
            'decrement' => '%^[^ ]+( +[0-9]+)?$%sui', // <key> <timeout>?
            'flush'     => '%^$%sui', // none
            'status'    => '%^$%sui' // none
        ];

        return (bool) preg_match($map[$command] . 'imu', $arguments);
    }

    /**
     * Execute a valid command.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $request Request command to execute.
     *
     * @return bool
     */
    protected function doCommand($request)
    {
        $request = trim($request);

        if ($request === 'flush') {
            $command = 'flush';
            $args = '';
        } elseif ($request === 'status') {
            $command = 'status';
            $args = '';
        } else {
            $command = strtolower(mb_substr($request, 0, stripos($request, ' ')));
            $args = trim(mb_substr($request, stripos($request, ' ')));
        }

        $validArgs = $this->validateArguments($command, $args);

        if ($validArgs !== true) {
            return 'Invalid arguments';
        }

        $method = 'command' . ucfirst($command);

        $result = $this->$method(trim($args));

        return $result;
    }

    /**
     * Add a new cache value. Don't overwrite existing.
     *
     * Format as:
     *
     *     add <key> <type>?<value> <timeout>?
     *
     * Example:
     *
     *     add mykey s|myvalue 60
     *     add mykey b|1 3600
     *     add mykey i|1024
     *     add mykey some_value
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $args Args the command was called with.
     *
     * @return mixed
     */
    protected function commandAdd($args)
    {
        $this->stdout('Adding with ' . $args, 3);

        $arguments = [];

        preg_match(
            //'%^([^\s]+)\s+((s|b|i)\|)?(.+)(\s+([0-9]+))?$%',
            '%^(?<key>[^ ]+) ((?<type>b|s|i)\|)?(?<value>.*?)( +(?<timeout>[0-9]+))?$%isu',
            $args,
            $arguments
        );

        $key = $arguments['key'];
        $value = $arguments['value'];
        $type = isset($arguments['type']) ? $arguments['type'] : 's';
        $timeout = isset($arguments['timeout']) ? (int) $arguments['timeout'] : 0;

        $value = $this->castValue($value, $type);

        return $this->getPool()->add($key, $value, $timeout);
    }

    /**
     * Set a cache value. Create and override.
     *
     * Format as:
     *
     *     set <key> <type>?<value> <timeout>?
     *
     * Example:
     *
     *     set mykey s|myvalue 60
     *     set mykey b|1 3600
     *     set mykey i|1024
     *     set mykey some_value
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $args Args the command was called with.
     *
     * @return mixed
     */
    protected function commandSet($args)
    {
        $this->stdout('Setting with ' . $args, 3);

        $arguments = [];

        preg_match(
        //'%^([^\s]+)\s+((s|b|i)\|)?(.+)(\s+([0-9]+))?$%',
            '%^(?<key>[^ ]+) ((?<type>b|s|i)\|)?(?<value>.*?)( +(?<timeout>[0-9]+))?$%isu',
            $args,
            $arguments
        );

        $key = $arguments['key'];
        $value = $arguments['value'];
        $type = isset($arguments['type']) ? $arguments['type'] : 's';
        $timeout = isset($arguments['timeout']) ? (int) $arguments['timeout'] : 0;

        $value = $this->castValue($value, $type);

        return $this->getPool()->set($key, $value, $timeout);
    }

    /**
     * Replace a cache value. Don't create if not available.
     *
     * Format as:
     *
     *     replace <key> <type>?<value> <timeout>?
     *
     * Example:
     *
     *     replace mykey s|myvalue 60
     *     replace mykey b|1 3600
     *     replace mykey i|1024
     *     replace mykey some_value
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $args Args the command was called with.
     *
     * @return mixed
     */
    protected function commandReplace($args)
    {
        $this->stdout('Replacing with ' . $args, 3);

        $arguments = [];

        preg_match(
        //'%^([^\s]+)\s+((s|b|i)\|)?(.+)(\s+([0-9]+))?$%',
            '%^(?<key>[^ ]+) ((?<type>b|s|i)\|)?(?<value>.*?)( +(?<timeout>[0-9]+))?$%isu',
            $args,
            $arguments
        );

        $key = $arguments['key'];
        $value = $arguments['value'];
        $type = isset($arguments['type']) ? $arguments['type'] : 's';
        $timeout = isset($arguments['timeout']) ? (int) $arguments['timeout'] : 0;

        $value = $this->castValue($value, $type);

        return $this->getPool()->replace($key, $value, $timeout);
    }

    /**
     * Delete a cached value.
     *
     * Format as:
     *
     *     delete <key>
     *
     * Example:
     *
     *     delete mykey
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $args Args the command was called with.
     *
     * @return mixed
     */
    protected function commandDelete($args)
    {
        $this->stdout('Deleting with ' . $args, 3);

        $key = $args;

        return $this->getPool()->delete($key);
    }

    /**
     * Increment a numeric cached value. Works only on integers and values that can
     * be casted to integers.
     *
     * Format as:
     *
     *     increment <key> <timeout>?
     *
     * Example:
     *
     *     increment mykey 60
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $args Args the command was called with.
     *
     * @return mixed
     */
    protected function commandIncrement($args)
    {
        $this->stdout('Incrementing with ' . $args, 3);

        $arguments = [];

        preg_match('%^(?<key>[^\s]+)(\s+(?<timeout>[0-9]+))?$%isu', $args, $arguments);

        $key = $arguments['key'];
        $timeout = isset($arguments['timeout']) ? (int) $arguments['timeout'] : 0;

        return $this->getPool()->increment($key, $timeout);
    }

    /**
     * Decrement a numeric cached value. Works only on integers and values that can
     * be casted to integers.
     *
     * Format as:
     *
     *     decrement <key> <timeout>?
     *
     * Example:
     *
     *     decrement mykey 60
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $args Args the command was called with.
     *
     * @return mixed
     */
    protected function commandDecrement($args)
    {
        $this->stdout('Decrementing with ' . $args, 2);

        $arguments = [];

        preg_match('%^(?<key>[^\s]+)(\s+(?<timeout>[0-9]+))?$%isu', $args, $arguments);

        $key = $arguments['key'];
        $timeout = isset($arguments['timeout']) ? (int) $arguments['timeout'] : 0;

        return $this->getPool()->decrement($key, $timeout);
    }

    /**
     * Get a cached value.
     *
     * Format as:
     *
     *     get <key>
     *
     * Example:
     *
     *     get mykey
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $args Args the command was called with.
     *
     * @return mixed
     */
    protected function commandGet($args)
    {
        $this->stdout('Getting with ' . $args, 3);

        $key = $args;

        return $this->getPool()->get($key);
    }

    /**
     * Check whether a value has been cached.
     *
     * Format as:
     *
     *     has <key>
     *
     * Example:
     *
     *     has mykey
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $args Args the command was called with.
     *
     * @return mixed
     */
    protected function commandHas($args)
    {
        $this->stdout('Checking (has) with ' . $args, 3);

        $key = $args;

        return $this->getPool()->has($key);
    }

    /**
     * Flush all cached values.
     *
     * Format as:
     *
     *     flush
     *
     * Example:
     *
     *     flush
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $args Args the command was called with.
     *
     * @return mixed
     */
    protected function commandFlush($args)
    {
        $this->stdout('Flushing with ' . $args, 3);

        return $this->getPool()->flush();
    }

    protected function commandStatus($args)
    {
        $this->stdout('Fetching status with ' . $args, 3);

        return $this->getPool()->getStatus();
    }

    /**
     * Event loop timer event.
     *
     * @since 0.1.0
     * @return void
     */
    public function tickEvent()
    {
        $memUsage = memory_get_usage();
        $memLimit = $this->configuration->getOption('memorylimit');

        $limitKB = $memLimit * 1024;

        $limitMB = $memLimit . 'MB';
        $memKB = $memUsage / 1024 . 'KB';

        $this->stdout('Memory usage: ' . $memKB . '/' . $limitMB, 2);

        $hardMemoryLimit = $limitKB;
        $softMemoryLimit = $hardMemoryLimit * 0.75;

        $this->stdout('Items in cache: ' . $this->getPool()->itemCount(), 2);

        if ($memUsage >= $hardMemoryLimit) {
            $this->stdout('Flushing all cache, reached hard memory limit');
            $this->getPool()->flush();
        } elseif ($memUsage >= $softMemoryLimit) {
            $this->stdout('Flushing LRU cache, reached soft memory limit');
            $this->getPool()->clearLeastRecentlyUsed();
        }

        $cleared = $this->getPool()->clearStaleCache();

        if ($cleared) {
            $this->stdout('Cleared ' . $cleared . ' stale cache values');
        }
    }

    /**
     * Send a message to STDOUT.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $message The message.
     * @param integer $verbosity Verbosity level to show this in. Higher level needs
     *                           higher config option to be setup.
     *
     * @return void
     */
    protected function stdout($message, $verbosity = 1)
    {
        if ((int) $verbosity > (int) $this->configuration->getOption('verbosity')) {
            return;
        }

        file_put_contents('php://stdout', $message . "\n", FILE_APPEND);
    }

    /**
     * Send a message to STDERR.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $message The message.
     * @param integer $verbosity Verbosity level to show this in. Higher level needs
     *                           higher config option to be setup.
     *
     * @return void
     */
    protected function stderr($message, $verbosity = 1)
    {
        if ((int) $verbosity > (int) $this->configuration->getOption('verbosity')) {
            return;
        }

        file_put_contents('php://stderr', $message . "\n", FILE_APPEND);
    }

    /**
     * Cast a value for internal cache saving.
     *
     * s -> string
     * b -> boolean
     * i -> integer
     *
     * Defaults to string.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param mixed $value Value to cast.
     * @param string $type Type to cast to. Either 's', 'b', or 'i'.
     *
     * @return integer|boolean|string
     */
    protected function castValue($value, $type = 's')
    {
        try {
            switch ($type) {
                case 'i':
                    $value = (int) $value;
                    break;
                case 'b':
                    $value = (bool) $value;
                    break;
                case 's':
                default:
                    $value = (string) $value;
                    break;
            }
        } catch (\Exception $e) {
            $this->stderr('ERROR: Could not cast value for caching: ' . $e->getMessage());
        }

        return $value;
    }
}
