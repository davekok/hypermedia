<?php declare(strict_types=1);

namespace Sturdy\Activity;

final class BadRequest extends Error
{
	/**
	 * Get the response status code
	 *
	 * @return int  the status code
	 */
	public function getStatusCode(): int
	{
		return 400;
	}

	/**
	 * Get the response status text
	 *
	 * @return string  the status text
	 */
	public function getStatusText(): string
	{
		return "Bad Request";
	}
}
