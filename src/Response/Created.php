<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

final class Created implements Response
{
	use ProtocolVersionTrait;
	use DateTrait;
	use NoContentTrait;

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
}
