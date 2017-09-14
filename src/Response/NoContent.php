<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

final class NoContent implements Response
{
	use ProtocolVersionTrait;
	use DateTrait;
	use NoLocationTrait;
	use NoContentTrait;

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
}
