<?php declare(strict_types=1);

namespace Sturdy\Activity\Request;

/**
 * The request interface as needed by Sturdy\Activity\HyperMedia.
 */
interface Request
{
	/**
	 * Get the protocol version
	 *
	 * @return string  the protocol version
	 */
	public function getProtocolVersion(): string;

	/**
	 * Get the HTTP verb used.
	 *
	 * @return string  the HTTP verb
	 */
	public function getVerb(): string;

	/**
	 * Get the path of the request URI.
	 *
	 * @return string  the path
	 */
	public function getPath(): string;

	/**
	 * Get the query of the request URI.
	 *
	 * @return string  the query string
	 */
	public function getQuery(): string;

	/**
	 * Get the content type of the content, if any
	 *
	 * @return ?string  the content type
	 */
	public function getContentType(): ?string;

	/**
	 * Get the content of the request, if any
	 *
	 * @return ?string  the content
	 */
	public function getContent(): ?string;
}
