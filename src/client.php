<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Schlumpf
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_PLATFORM') or die;

/**
 * Joomla Platform class for interacting with an OAuth 2.0 server.
 *
 * @since       3.1.4
 * @deprecated  4.0  Use the `joomla/oauth2` framework package that will be bundled instead
 */
class JOAuth2ClientCustom extends JOAuth2Client
{
	/**
	 * Get the access token or redict to the authentication URL.
	 *
	 * @return  string  The access token
	 *
	 * @since   3.1.4
	 * @throws  RuntimeException 
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

			if ($response->code >= 200 && $response->code < 400)
			{
				$key = (array_key_exists('Content-Type', $response->headers)) ? 'Content-Type' : 'content-type';

				if (strpos($response->headers[$key], 'application/json') === 0)
				{
					$token = array_merge(json_decode($response->body, true), array('created' => time()));
				}
				else
				{
					parse_str($response->body, $token);
					$token = array_merge($token, array('created' => time()));
				}

				$this->setToken($token);

				return $token;
			}
			else
			{
				throw new RuntimeException('Error code ' . $response->code . ' received requesting access token: ' . $response->body . '.');
			}
		}

		if ($this->getOption('sendheaders'))
		{
			$this->application->redirect($this->createUrl());
		}

		return false;
	}
}
