<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\InputFilter;

use Zend\InputFilter\Exception;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\ConfigInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stdlib\InitializableInterface;

/**
 * Plugin manager implementation for input filters.
 */
class InputFilterPluginManager extends AbstractPluginManager
{
    /**
     * Whether or not to share by default
     *
     * @var bool
     */
    protected $shareByDefault = false;

    /**
     * @param ConfigInterface $configuration
     */
    public function __construct(ConfigInterface $configuration = null)
    {
        parent::__construct($configuration);

        $this->addInitializer(array($this, 'populateFactory'));
    }

    /**
     * Populate the factory with filter chain and validator chain
     *
     * @param $element
     */
    public function populateFactory($element)
    {
        if ($element instanceof InputFilter && $this->serviceLocator instanceof ServiceLocatorInterface) {
            $factory = $element->getFactory();
            $factory->getDefaultFilterChain()->setPluginManager($this->serviceLocator->get('FilterManager'));
            $factory->getDefaultValidatorChain()->setPluginManager($this->serviceLocator->get('ValidatorManager'));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function validatePlugin($plugin)
    {
        if ($plugin instanceof InputFilterInterface) {
            // Hook to perform various initialization, when the element is not created through the factory
            if ($plugin instanceof InitializableInterface) {
                $plugin->init();
            }

            // we're okay
            return;
        }

        throw new Exception\RuntimeException(sprintf(
            'Plugin of type %s is invalid; must implement Zend\InputFilter\InputFilterInterface',
            (is_object($plugin) ? get_class($plugin) : gettype($plugin))
        ));
    }
}
