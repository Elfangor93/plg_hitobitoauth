<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Schlumpf
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
?>
var getOAuthToken = function(element, client)
{
    let winprops = "height=500,width=400,top=100,left=100,scrollbars=1,resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,directories=no,status=no";
    let url = "<?php echo JUri::root(); ?>?task=oauth&app=hitobito&from="+client;
    let popupWin = window.open(url, "Hitobito OAuth2", winprops);
};