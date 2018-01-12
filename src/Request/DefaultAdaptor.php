<?php declare(strict_types=1);

namespace Sturdy\Activity\Request;

/**
 * Adaptor for PHP's builtin $_SERVER array or similar data structure
 */
final class DefaultAdaptor implements Request
{
	private $server;

	/**
	 * Constructor
	 *
	 * @param array $server  the $_SERVER array or similar
	 */
	public function __construct(array $server)
	{
		$this->server = $server;
	}

	/**
	 * Get protocol version
	 *
	 * @return string
	 */
	public function getProtocolVersion(): string
	{
		return substr($this->server['SERVER_PROTOCOL'], strpos($this->server['SERVER_PROTOCOL'], "/") + 1);
	}

	/**
	 * Get verb
	 *
	 * @return string
	 */
	public function getVerb(): string
	{
		return $this->server['REQUEST_METHOD'];
	}

	/**
	 * Get path
	 *
	 * @return string
	 */
	public function getPath(): string
	{
		$p = strpos($this->server['REQUEST_URI'], "?");
		if ($p !== false) {
			return substr($this->server['REQUEST_URI'], $path, 0, $p);
		} else {
			return $this->server['REQUEST_URI'];
		}
	}

	/**
	 * Get query
	 *
	 * @return string
	 */
	public function getQuery(): string
	{
		$p = strpos($this->server['REQUEST_URI'], "?");
		if ($p !== false) {
			return substr($this->server['REQUEST_URI'], $path, $p+1);
		} else {
			return "";
		}
	}

	/**
	 * Get content type
	 *
	 * @return ?string
	 */
	public function getContentType(): ?string
	{
		return $this->server['CONTENT_TYPE'] ?? $this->server['HTTP_CONTENT_TYPE'] ?? null;
	}

	/**
	 * Get accept
	 *
	 * @return ?string
	 */
	public function getAccept(): ?string
	{
		return $this->server['HTTP_ACCEPT'] ?? $this->server['ACCEPT'] ?? null;
	}

	/**
	 * Get content
	 *
	 * @return ?string
	 */
	public function getContent(): ?string
	{
		return file_get_contents("php://input") ?: null;
	}
}
