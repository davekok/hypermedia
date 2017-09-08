<?php declare(strict_types=1);

namespace Sturdy\Activity;

final class Created implements Response
{
	private $location;

	public function __construct(string $location) {
		$this->location = $location;
	}

	/**
	 * Get the response status code
	 *
	 * @return int  the status code
	 */
	public function getStatusCode(): int
	{
		return 201;
	}

	/**
	 * Get the response status text
	 *
	 * @return string  the status text
	 */
	public function getStatusText(): string
	{
		return "Created";
	}

	/**
	 * Get location
	 *
	 * @return string  the location on which the resource has been created
	 */
	public function getLocation(): string
	{
		return $this->location;
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
		$responseBuilder->setLocation($this->getLocation());
		return $responseBuilder->getResponse();
	}
}
