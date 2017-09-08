<?php declare(strict_types=1);

namespace Sturdy\Activity;

final class NoContent implements Response
{
	/**
	 * Get the response status code
	 *
	 * @return int  the status code
	 */
	public function getStatusCode(): int
	{
		return 204;
	}

	/**
	 * Get the response status text
	 *
	 * @return string  the status text
	 */
	public function getStatusText(): string
	{
		return "No Content";
	}

	/**
	 * Convert response using response builder
	 *
	 * @param ResponseBuilder $rb  the response builder
	 * @return mixed  the response
	 */
	public function convert(ResponseBuilder $rb)
	{
		$responseBuilder->setStatus($this->getStatusCode(), $this->getStatusText());
		return $responseBuilder->getResponse();
	}
}
