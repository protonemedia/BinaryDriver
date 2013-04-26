<?php

/*
 * This file is part of Alchemy\BinaryDriver.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\BinaryDriver;

use Alchemy\BinaryDriver\Exception\ExecutableNotFoundException;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\ExecutableFinder;

class AbstractBinary implements BinaryInterface
{
    /** @var ConfigurationInterface */
    protected $configuration;

    /** @var ProcessBuilderFactoryInterface */
    protected $factory;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(ProcessBuilderFactoryInterface $factory, LoggerInterface $logger, ConfigurationInterface $configuration)
    {
        $this->factory = $factory;
        $this->logger = $logger;
        $this->configuration = $configuration;
        $this->applyProcessConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     *
     * @return BinaryInterface
     */
    public function setConfiguration(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
        $this->applyProcessConfiguration();

        return $this;
    }

    /**
     * Get the current logger.
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * {@inheritdoc}
     *
     * @return BinaryInterface
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getProcessBuilderFactory()
    {
        return $this->factory;
    }

    /**
     * {@inheritdoc}
     *
     * @return BinaryInterface
     */
    public function setProcessBuilderFactory(ProcessBuilderFactoryInterface $factory)
    {
        $this->factory = $factory;
        $this->applyProcessConfiguration();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function load($binaries, LoggerInterface $logger = null, $configuration = array())
    {
        $finder = new ExecutableFinder();
        $binary = null;
        $binaries = is_array($binaries) ? $binaries : array($binaries);

        foreach ($binaries as $candidate) {
            if (file_exists($candidate) && is_executable($candidate)) {
                $binary = $candidate;
                break;
            }
            if (null !== $binary = $finder->find($candidate)) {
                break;
            }
        }

        if (null === $binary) {
            throw new ExecutableNotFoundException(sprintf(
                'Executable not found, proposed : %s', implode(', ', $binaries)
            ));
        }

        if (null === $logger) {
            $logger = new Logger(__NAMESPACE__ . ' logger');
            $logger->pushHandler(new NullHandler());
        }

        $configuration = $configuration instanceof ConfigurationInterface ? $configuration : new Configuration($configuration);

        return new static(new ProcessBuilderFactory($binary), $logger, $configuration);
    }

    private function applyProcessConfiguration()
    {
        if ($this->configuration->has('timeout')) {
            $this->factory->setTimeout($this->configuration->get('timeout'));
        }

        return $this;
    }
}