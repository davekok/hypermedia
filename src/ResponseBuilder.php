<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * An interface to build a response.
 */
interface ResponseBuilder
{
	/**
	 * Get the builded response.
	 *
	 * @return the response
	 */
	public function getResponse();

	/**
	 * Set the protocol to use.
	 *
	 * @param string $protocol  the protocol
	 */
	public function setProtocol(string $protocol): void;

	/**
	 * Set the http status code.
	 *
	 * @param  int    $code  the http status code
	 * @param  string $text  the http status text
	 */
	public function setStatus(int $code, string $text): void;

	/**
	 * Set the location header
	 *
	 * @param  string $location  the location header
	 */
	public function setLocation(string $location): void;

	/**
	 * Set the http content type header
	 *
	 * @param  string $contentType  the content type header
	 */
	public function setContentType(string $contentType): void;

	/**
	 * Set content
	 *
	 * @param  string $content  the content
	 */
	public function setContent(string $content): void;
}
