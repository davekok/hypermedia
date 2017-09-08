<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Symfony\Component\HttpFoundation\Response;

/**
 * An adaptor for building symfony responses.
 */
final class SymfonyResponseBuilder implements ResponseBuilder
{
	private $response;

	public function __construct(Response $response)
	{
		$this->response = $response;
	}

	/**
	 * Get the response.
	 *
	 * @return Symfony\Component\HttpFoundation\Response  the response
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
		$this->response->setProtocolVersion($version);
	}

	/**
	 * Set the http status code.
	 *
	 * @param  int    $code  the http status code
	 * @param  string $text  the http status text
	 */
	public function setStatus(int $code, string $text): void
	{
		$this->response->setStatusCode($status, $text);
	}

	/**
	 * Set the location header
	 *
	 * @param  string $location  the location header
	 */
	public function setLocation(string $location): void
	{
		$this->response->headers->set("Location", $location);
	}

	/**
	 * Set the http content type header
	 *
	 * @param  string $contentType  the content type header
	 */
	public function setContentType(string $contentType): void
	{
		$this->response->headers->set("Content-Type", $contentType);
	}

	/**
	 * Set content
	 *
	 * @param  string $content  the content
	 */
	public function setContent(string $content): void
	{
		$this->response->setContent($content);
	}
}
