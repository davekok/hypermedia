<?php declare(strict_types=1);

namespace Sturdy\Activity;

interface Response
{
	/**
	 * Get the response status code
	 *
	 * @return int  the status code
	 */
	public function getStatusCode(): int;

	/**
	 * Get the response status text
	 *
	 * @return string  the status text
	 */
	public function getStatusText(): string;

	/**
	 * Set the fields of this response.
	 *
	 * @param array $fields
	 */
	public function setFields(array $fields): void;

	/**
	 * Convert response using response builder
	 *
	 * @param ResponseBuilder $rb  the response builder
	 * @return mixed  the response
	 */
	public function convert(ResponseBuilder $rb);
}
