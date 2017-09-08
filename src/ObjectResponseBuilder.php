<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;

/**
 * An adaptor for building object responses.
 */
final class ObjectResponseBuilder implements ResponseBuilder
{
	private $response;

	public function __construct(/*?object*/ $response = null)
	{
		$this->response = $response ?? new stdClass;
	}

	/**
	 * Get the response.
	 *
	 * @return array  the response
	 */
	public function getResponse()
	{
		return $this->response;
	}

	/**
	 * Set the protocol version to use.
	 *
	 * @param string $version  the protocol version
	 */
	public function setProtocolVersion(string $version): void
	{
		$this->response->protocolVersion = $version;
	}

	/**
	 * Set the http status code.
	 *
	 * @param  int    $code  the http status code
	 * @param  string $text  the http status text
	 */
	public function setStatus(int $code, string $text): void
	{
		$this->response->statusCode = $code;
		$this->response->statusText = $text;
	}

	/**
	 * Set the location header
	 *
	 * @param  string $location  the location header
	 */
	public function setLocation(string $location): void
	{
		$this->response->headers['Location'] = $location;
	}

	/**
	 * Set the http content type header
	 *
	 * @param  string $contentType  the content type header
	 */
	public function setContentType(string $contentType): void
	{
		$this->response->headers['Content-Type'] = $contentType;
	}

	/**
	 * Set content
	 *
	 * @param  string $content  the content
	 */
	public function setContent(string $content): void
	{
		$this->response->content = $content;
	}
}
