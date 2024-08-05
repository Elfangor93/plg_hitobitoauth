<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Manuel Häusler (Schlumpf)
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') || die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Schlumpf\Plugin\System\Hitobitoauth\Extension\Hitobitoauth;

return new class implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container)
            {
                $config  = (array)PluginHelper::getPlugin('system', 'hitobitoauth');
                $subject = $container->get(DispatcherInterface::class);

                /** @var \Joomla\CMS\Plugin\CMSPlugin $plugin */
                $plugin = new Hitobitoauth($subject, $config);
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
