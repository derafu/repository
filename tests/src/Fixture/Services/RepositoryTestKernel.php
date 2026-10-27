<?php

declare(strict_types=1);

/**
 * Derafu: Repository - Lightweight Data Source Management for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsRepository\Fixture\Services;

use Derafu\Kernel\Environment;
use Derafu\Kernel\MicroKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class RepositoryTestKernel extends MicroKernel
{
    protected const CONFIG_LOADERS = [...parent::CONFIG_LOADERS, YamlFileLoader::class];

    public function __construct(string $environment)
    {
        $root = dirname(__DIR__, 4);
        parent::__construct(
            new Environment($environment, true, directories: [
                'project' => $root,
                'config' => $root . '/tests/fixtures/services',
                'cache' => $root . '/var/cache/repository-services/' . $environment,
            ]),
            kernelId: 'repository_services_' . $environment,
        );
    }

    public function getContainer(): ContainerInterface
    {
        return parent::getContainer();
    }

    protected function configure(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import($this->environment->getConfigDir() . '/' . $this->environment->getName() . '.yaml');
    }
}
