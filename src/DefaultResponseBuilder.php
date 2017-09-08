<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;

/**
 * Default adaptor for building responses.
 */
final class DefaultResponseBuilder implements ResponseBuilder
{
	private $protocolVersion;

	/**
	 * Get the response.
	 *
	 * @return array  the response
	 */
	public function getResponse()
	{
	}

	/**
	 * Set the protocol version to use.
	 *
	 * @param string $version  the protocol version
	 */
	public function setProtocolVersion(string $version): void
	{
		$this->protocolVersion = $version;
	}

	/**
	 * Set the http status code.
	 *
	 * @param  int    $code  the http status code
	 * @param  string $text  the http status text
	 */
	public function setStatus(int $code, string $text): void
	{
		header("HTTP/{$this->protocolVersion} $code $text");
	}

	/**
	 * Set the location header
	 *
	 * @param  string $location  the location header
	 */
	public function setLocation(string $location): void
	{
		header("Location: $location");
	}

	/**
	 * Set the http content type header
	 *
	 * @param  string $contentType  the content type header
	 */
	public function setContentType(string $contentType): void
	{
		header("Content-Type: $contentType");
	}

	/**
	 * Set content
	 *
	 * @param  string $content  the content
	 */
	public function setContent(string $content): void
	{
		echo $content;
	}
}
