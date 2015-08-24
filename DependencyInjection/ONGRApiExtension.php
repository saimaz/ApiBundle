<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\ApiBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads and manages your bundle configuration.
 */
class ONGRApiExtension extends Extension
{
    /**
     * @var array
     */
    private $configuration;

    /**
     * @var string
     */
    private $version = '';

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $this->collectRoutes($config['versions'], $container);
        $this->registerAuthenticationListener($config, $container);
        $container->setParameter('ongr_api.default_encoding', $config['default_encoding']);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    private function registerAuthenticationListener(array $config, ContainerBuilder $container)
    {
        $definition = new Definition(
            $container->getParameter('ongr_api.event_listener.authentication.class'),
            [
                new Reference('service_container'),
                $config['secret']
            ]
        );
        $definition->setTags(
            [
                'kernel.event_listener' => [
                    ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 10]
                ]
            ]
        );

        $container->setDefinition('ongr_api.event_listener.authentication', $definition);
    }

    /**
     * Populates endpoints into route collection
     *
     * @param array            $endpoints
     * @param string           $version
     * @param ContainerBuilder $builder
     */
    private function collectRoutes(array $config, ContainerBuilder $builder)
    {
        $collection = new Definition('Symfony\Component\Routing\RouteCollection');

        foreach ($config as $version => $endpoints) {
            $this
                ->setEndpointConfig($endpoints['endpoints'])
                ->setVersion($version);

            foreach ($this->generate() as $name => $config) {
                $route = new Definition('Symfony\Component\Routing\Route', $config);
                $collection->addMethodCall('add', [$name, $route]);
            }
        }

        $collection->setPublic(false);
        $builder->setDefinition('ongr_api.route_collection', $collection);
    }

    /**
     * Generates configuration for each route.
     *
     * @return \Generator
     */
    public function generate()
    {
        foreach ($this->getEndpointConfig() as $name => $config) {
            foreach ($config['documents'] as $docConfig) {
                list(,$type) = explode(':', $docConfig['name'], 2);
                $c = [
                    'url' => $this->formatUrl($name, $type),
                    'defaults' => [
                        'id' => null,
                        'type' => strtolower($type),
                        'manager' => $config['manager'],
                        'repository' => $docConfig['name'],
                    ]
                ];

                foreach ($docConfig['methods'] as $method) {
                    $c['defaults']['_controller'] = $docConfig['controller'] . ':' . strtolower($method);
                    $c['requirements']['_method'] = $method;

                    yield $this->formatName($name, $type, $method) => $c;
                }
            }
        }
    }

    /**
     * Formats url for endpoint
     *
     * @param string $endpoint
     * @param string $type
     *
     * @return string
     */
    protected function formatUrl($endpoint, $type)
    {
        return sprintf(
            "%s%s%s/{id}",
            $this->getVersion() . '/',
            $endpoint === 'default' ? '' : strtolower($endpoint) . '/',
            strtolower($type)
        );
    }

    /**
     * Formats route name.
     *
     * @param string $endpoint
     * @param string $type
     * @param string $method
     *
     * @return string
     */
    protected function formatName($endpoint, $type, $method)
    {
        return strtolower(sprintf('ongr_api_%s_%s_%s_%s', $this->getVersion(), $endpoint, $type, $method));
    }

    /**
     * @param array $configuration
     *
     * @return ONGRApiExtension
     */
    protected function setEndpointConfig($configuration)
    {
        $this->configuration = $configuration;

        return $this;
    }

    /**
     * @param string $version
     *
     * @return ONGRApiExtension
     */
    protected function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return array
     */
    protected function getEndpointConfig()
    {
        return $this->configuration;
    }

    /**
     * @return string
     */
    protected function getVersion()
    {
        return $this->version;
    }
}
