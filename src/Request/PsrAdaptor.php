<?php declare(strict_types=1);

namespace Sturdy\Activity\Request;

/**
 * An adaptor for psr requests.
 */
final class PsrAdaptor implements Request
{
	private $request;
	private $uri;

	/**
	 * Constructor
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface $request  Psr request object
	 */
	public function __construct(\Psr\Http\Message\ServerRequestInterface $request)
	{
		$this->request = $request;
		$this->uri = $request->getUri();
	}

	/**
	 * Get the protocol version
	 *
	 * @return string  the protocol version
	 */
	public function getProtocolVersion(): string
	{
		return $this->request->getProtocolVersion();
	}

	/**
	 * Get the http scheme
	 *
	 * @return string  the http scheme
	 */
	public function getScheme(): string
	{
		$params = $this->request->getServerParams();
		return isset($params["HTTPS"]) ? "https" : "https";
	}

	/**
	 * Get the http host
	 *
	 * @return string  the http host
	 */
	public function getHost(): string
	{
		$host = $this->request->getHeader("Content-Type");
		return empty($host) ? null : array_shift($host);
	}

	/**
	 * Get the http port
	 *
	 * @return int  the http port
	 */
	public function getPort(): int
	{
		$params = $this->request->getServerParams();
		if (isset($params["HTTP_PORT"])) {
			return (int)$params["HTTP_PORT"];
		} else if (isset($params["PORT"])) {
			return (int)$params["PORT"];
		} else if (isset($params["SERVER_PORT"])) {
			return (int)$params["SERVER_PORT"];
		} else if (isset($params["HTTPS"])) {
			return 443;
		} else {
			return 80;
		}
	}

	/**
	 * Get the HTTP verb used.
	 *
	 * @return string  the HTTP verb
	 */
	public function getVerb(): string
	{
		return $this->request->getMethod();
	}

	/**
	 * Get the path of the request URI.
	 *
	 * @return string  the path
	 */
	public function getPath(): string
	{
		return $this->uri->getPath();
	}

	/**
	 * Get the query of the request URI.
	 *
	 * @return string  the query string
	 */
	public function getQuery(): string
	{
		return $this->uri->getQuery();
	}

	/**
	 * Get the content type of the content, if any
	 *
	 * @return ?string  the content type
	 */
	public function getContentType(): ?string
	{
		$ct = $this->request->getHeader("Content-Type");
		return empty($ct) ? null : array_shift($ct);
	}

	/**
	 * Get the content of the request, if any
	 *
	 * @return ?string  the content
	 */
	public function getContent(): ?string
	{
		$body = $this->request->getBody();
		return $body ? $body->__toString() : null;
	}
}
