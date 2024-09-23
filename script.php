<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Manuel Häusler (Schlumpf)
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
 
\defined('_JEXEC') or die;
 
return new class () implements ServiceProviderInterface
{
  public function register(Container $container)
  {
    $container->set(
        InstallerScriptInterface::class,
        new class($container->get(AdministratorApplication::class), $container->get(DatabaseInterface::class)) implements InstallerScriptInterface
      {
        private AdministratorApplication $app;
        private DatabaseInterface $db;

        private string $minimumJoomla = '5.0.0';
        private string $minimumPhp    = '7.4.0';

        public function __construct(AdministratorApplication $app, DatabaseInterface $db)
        {
          $this->app = $app;
          $this->db  = $db;
        }

        public function install(InstallerAdapter $parent): bool
        {
          ?>
          <div class="text-center">
            <div class="alert alert-light">
              <h3><?php echo Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_SUCCESS_INSTALL', $parent->getManifest()->version); ?></h3>
              <p><?php echo Text::_('PLG_SYSTEM_HITOBITOAUTH_SUCCESS_INSTALL_TXT'); ?></p>
              <p>
                <a title="<?php echo Text::_('PLG_SYSTEM_HITOBITOAUTH_CONFIGURE_BTN'); ?>" class="btn btn-success" onclick="location.href='index.php?option=com_plugins&view=plugins&filter[search]=hitobito'; return false;" href="#"><?php echo Text::_('PLG_SYSTEM_HITOBITOAUTH_CONFIGURE_BTN'); ?></a> 

                <a class="btn btn-outline-primary" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=C28HUM53S6EC2" target="_blank"><?php echo Text::_('PLG_SYSTEM_HITOBITOAUTH_DONATE'); ?></a>
              </p>
            </div>
          </div>
          <?php

          return true;
        }

        public function update(InstallerAdapter $parent): bool
        {
          ?>
          <div class="text-center">
            <div class="alert alert-light">
              <h3><?php echo Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_SUCCESS_UPDATE', $parent->getManifest()->version); ?></h3>
              <p><?php echo Text::_('PLG_SYSTEM_HITOBITOAUTH_SUCCESS_UPDATE_TXT'); ?></p>
              <p>
                <a title="<?php echo Text::_('PLG_SYSTEM_HITOBITOAUTH_CONFIGURE_BTN'); ?>" class="btn btn-success" onclick="location.href='index.php?option=com_plugins&view=plugins&filter[search]=hitobito'; return false;" href="#"><?php echo Text::_('PLG_SYSTEM_HITOBITOAUTH_CONFIGURE_BTN'); ?></a> 

                <a class="btn btn-outline-primary" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=C28HUM53S6EC2" target="_blank"><?php echo Text::_('PLG_SYSTEM_HITOBITOAUTH_DONATE'); ?></a>
              </p>
            </div>
          </div>
          <?php

          return true;
        }

        public function uninstall(InstallerAdapter $parent): bool
        {
          return true;
        }

        public function preflight(string $type, InstallerAdapter $adapter): bool
        {      
          if (version_compare(PHP_VERSION, $this->minimumPhp, '<'))
          {
            $this->app->enqueueMessage(sprintf(Text::_('JLIB_INSTALLER_MINIMUM_PHP'), $this->minimumPhp), 'error');

            return false;
          }

          if (version_compare(JVERSION, $this->minimumJoomla, '<'))
          {
            $this->app->enqueueMessage(sprintf(Text::_('JLIB_INSTALLER_MINIMUM_JOOMLA'), $this->minimumJoomla), 'error');

            return false;
          }

          return true;
        }

        public function postflight(string $type, InstallerAdapter $parent): bool
        {
          return true;
        }
      }
    );
  }
};