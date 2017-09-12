<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use DateTime;

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
	 * Get the date time of the response
	 *
	 * @return DateTime  date time object
	 */
	public function getDate(): DateTime;

	/**
	 * Get location header
	 *
	 * @return string  location
	 */
	public function getLocation(): ?string;

	/**
	 * Get the content type
	 *
	 * @return string  content type
	 */
	public function getContentType(): ?string;

	/**
	 * Get the content
	 *
	 * @return string  content
	 */
	public function getContent(): ?string;
}
