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
		$dataCode = $this->input->get('code', false, 'raw');

		if($dataCode)
		{
			$data = [
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => $this->getOption('redirecturi'),
				'client_id'     => $this->getOption('clientid'),
				'client_secret' => $this->getOption('clientsecret'),
				'code'          => $dataCode,
			];

			$response = $this->http->post($this->getOption('tokenurl'), $data);

			if($response->getStatusCode() < 200 || $response->getStatusCode() >= 400)
			{
				throw new UnexpectedResponseException(
					$response,
					sprintf(
						'Error code %s received requesting access token: %s.',
						$response->getStatusCode(),
						(string) $response->getBody()
					)
				);
			}

			if(array_filter($response->getHeader('Content-Type'), fn($v) => str_contains($v, "application/json")))
			{
				$token = array_merge(json_decode((string) $response->getBody(), true), ['created' => time()]);
			}
			else
			{
				parse_str((string) $response->getBody(), $token);
				$token = array_merge($token, ['created' => time()]);
			}

			$this->setToken($token);

			return $token;
		}

		if($this->getOption('sendheaders'))
		{
			if(!($this->application instanceof WebApplicationInterface))
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
