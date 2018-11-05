<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use Throwable;

class BadRequest extends Error
{
	/**
	 * Error constructor.
	 */
	public function __construct(string $resource, array $messages)
	{
		mb_substitute_character(0xFFFD);
		parent::__construct(mb_convert_encoding("$resource:\n".implode("\n", $messages), 'UTF-8', 'UTF-8'));
	}

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
