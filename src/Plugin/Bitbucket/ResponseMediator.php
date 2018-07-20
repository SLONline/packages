<?php

/*
 * Copyright (c) SLONline
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace SLONline\Packages\Plugin\Bitbucket;


use Buzz\Message\MessageInterface;

class ResponseMediator
{
	public static function getContent(MessageInterface $response)
	{
		$body = $response->getContent();
		if (strpos($response->getHeader('Content-Type'), 'application/json') === 0) {
			$content = json_decode($body, true);
			if (JSON_ERROR_NONE === json_last_error()) {
				return $content;
			}
		}

		return $body;
	}

	public static function getPagination(MessageInterface $response)
	{
		$body = $response->getContent();
		$pagination = array();

		if (strpos($response->getHeader('Content-Type'), 'application/json') === 0) {
			$content = json_decode($body, true);
			if (JSON_ERROR_NONE === json_last_error()) {
				unset($content['values']);

				$pagination = $content;
			}
		}

		return $pagination;
	}

	public static function getApiLimit(MessageInterface $response)
	{
		return 1;
	}
}