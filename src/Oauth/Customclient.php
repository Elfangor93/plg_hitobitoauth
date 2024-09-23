<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Manuel Häusler (Schlumpf)
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Schlumpf\Plugin\System\Hitobitoauth\Oauth;

\defined('JPATH_PLATFORM') or die;

use \Joomla\OAuth2\Client as OAuth2Client;

use \Joomla\Application\WebApplicationInterface;
use \Joomla\Http\Exception\UnexpectedResponseException;

/**
 * Joomla Framework class for interacting with an OAuth 2.0 server.
 *
 * @since  1.0
 */
class Customclient extends OAuth2Client
{
	/**
	 * Get the access token or redirect to the authentication URL.
	 *
	 * @return  array|boolean  The access token or false on failure
	 *
	 * @since   1.0
	 * @throws  UnexpectedResponseException
	 * @throws  \RuntimeException
	 */
	public function authenticate()
	{
		if ($data['code'] = $this->input->get('code', false, 'raw'))
		{
			$data['grant_type'] = 'authorization_code';
			$data['redirect_uri'] = $this->getOption('redirecturi');
			$data['client_id'] = $this->getOption('clientid');
			$data['client_secret'] = $this->getOption('clientsecret');
			$response = $this->http->post($this->getOption('tokenurl'), $data);

			if (!($response->code >= 200 && $response->code < 400))
			{
				throw new UnexpectedResponseException(
					$response,
					sprintf(
						'Error code %s received requesting access token: %s.',
						$response->code,
						$response->body
					)
				);
			}

			// Make sure all headers are lowercase
			//$response->headers = array_change_key_case($response->headers, CASE_LOWER);
			$key = (array_key_exists('Content-Type', $response->headers)) ? 'Content-Type' : 'content-type';

			if (strpos($response->headers[$key][0], 'application/json') !== false)
			{
				$token = array_merge(json_decode($response->body, true), ['created' => time()]);
			}
			else
			{
				parse_str($response->body, $token);
				$token = array_merge($token, ['created' => time()]);
			}

			$this->setToken($token);

			return $token;
		}

		if ($this->getOption('sendheaders'))
		{
			if (!($this->application instanceof WebApplicationInterface))
			{
				throw new \RuntimeException(
					\sprintf('A "%s" implementation is required to process authentication.', WebApplicationInterface::class)
				);
			}

			$this->application->redirect($this->createUrl());
		}

		return false;
	}
}
