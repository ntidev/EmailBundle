<?php

namespace NTI\EmailBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('nti_email');
        $rootNode
            ->children()
            ->scalarNode("spool_dir")->defaultValue("/tmp")->end()
            ->arrayNode("dev_mode")
                ->children()
                    ->scalarNode("enabled")->defaultTrue()->end()
                    ->scalarNode("to")->defaultValue("")->end()
                    ->scalarNode("cc")->defaultValue(null)->end()
                    ->scalarNode("bcc")->defaultValue(null)->end()
                ->end()
            ->end();

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }
}
