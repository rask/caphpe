<?php
declare(strict_types=1);

namespace Caphpe\Cli;

/**
 * Class Arguments
 *
 * For handling CLI command arguments.
 *
 * @since 0.1.0
 * @package Caphpe\Cli
 */
class Arguments
{
    /**
     * Argument options used by the application.
     *
     * @since 0.1.0
     * @access protected
     * @var string[]
     */
    protected $availableOptions = [
        'verbosity' => 'v',
        'memorylimit' => 'm',
        'port' => 'p',
        'host' => 'h'
    ];

    /**
     * Parsed options that are available.
     *
     * @since 0.1.0
     * @access protected
     * @var mixed[]
     */
    protected $givenOptions = [];

    /**
     * Arguments available in $argv.
     *
     * @since 0.1.0
     * @access protected
     * @var mixed[]
     */
    protected $givenArgv;

    /**
     * Default options if not provided when app is started.
     *
     * @since 0.1.0
     * @access protected
     * @var mixed[]
     */
    protected $defaults = [
        'host' => '127.0.0.1',
        'port' => 10808,
        'memorylimit' => 8388608,
        'verbosity' => 1
    ];

    /**
     * Arguments constructor.
     *
     * @since 0.1.0
     *
     * @param array $argv CLI arguments.
     *
     * @return void
     */
    public function __construct(array $argv)
    {
        $this->givenArgv = $argv;
    }

    /**
     * Get an option value.
     *
     * @since 0.1.0
     *
     * @param string $key Option key.
     *
     * @return mixed
     */
    public function getOption(string $key)
    {
        $parsed = array_key_exists($key, $this->givenOptions);

        if ($parsed) {
            return $this->givenOptions[$key];
        }

        $available = array_key_exists($key, $this->availableOptions);

        if (!$available) {
            throw new \InvalidArgumentException('Invalid option key.');
        }

        $fullForm = '--' . $key . '=';
        $shortForm = '-' . $this->availableOptions[$key];

        $givenOption = null;

        foreach ($this->givenArgv as $k => $arg) {
            if (strpos($arg, $fullForm) === 0) {
                $givenOption = $arg;
            } elseif (strpos($arg, $shortForm) === 0) {
                if (trim($arg) === $shortForm) {
                    $givenOption = $shortForm . ' ' . $this->givenArgv[$k + 1];
                } else {
                    $givenOption = $arg;
                }
            }
        }

        if ($givenOption === null) {
            return $this->getDefaultOption($key);
        }

        $value = str_replace([$fullForm, $shortForm], '', $givenOption);

        $this->givenOptions[$key] = $value;

        return $value;
    }

    /**
     * Get default option value.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $key Option key.
     *
     * @return mixed
     */
    protected function getDefaultOption(string $key)
    {
        return $this->defaults[$key];
    }
}
