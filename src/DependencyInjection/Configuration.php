<?php

namespace LeNats\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /** @var bool */
    private $debug;

    /**
     * @param bool $debug
     */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        if (method_exists(TreeBuilder::class, 'getRootNode')) {
            $tb = new TreeBuilder('le_nats');
            $rootNode = $tb->getRootNode();
        } else {
            $tb = new TreeBuilder();
            $rootNode = $tb->root('le_nats');
        }

        $rootNode->children()
            ->arrayNode('connection')
            ->children()
            ->scalarNode('dsn')->isRequired()->end()
            ->scalarNode('client_id')->isRequired()->end()
            ->scalarNode('cluster_id')->isRequired()->end()
            ->scalarNode('user')->defaultValue(null)->end()
            ->scalarNode('pass')->defaultValue(null)->end()
            ->booleanNode('verbose')->defaultFalse()->end()
            ->integerNode('connection_timeout')->defaultValue(30)->end()
            ->integerNode('write_timeout')->defaultValue(5)->end()
            ->booleanNode('debug')->defaultValue(false)->end()
            ->booleanNode('is_random_client_id')->defaultValue(false)->end()
            ->arrayNode('context')
            ->children()
            ->arrayNode('tls')
            ->children()
            ->scalarNode('protocol')->end()
            ->scalarNode('ciphers')->end()
            ->scalarNode('peer_name')->end()
            ->booleanNode('verify_peer')->end()
            ->booleanNode('verify_peer_name')->end()
            ->booleanNode('allow_self_signed')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->arrayNode('event_suffixes')
            ->defaultValue(['.created', '.updated', '.deleted'])
            ->scalarPrototype()->end()
            ->end()
            ->arrayNode('accept_events')
            ->defaultValue([])
            ->useAttributeAsKey('event_type')
            ->scalarPrototype()->end()
            ->end();

        return $tb;
    }
}
