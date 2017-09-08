<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Psr\Http\Message\ServerRequestInterface;

/**
 * An adaptor for psr requests.
 */
final class PsrRequestAdaptor implements Request
{
	private $request;
	private $uri;

	public function __construct(ServerRequestInterface $request)
	{
		$this->request = $request;
		$this->uri = $request->getUri();
	}

	public function getProtocolVersion(): string
	{
		return $this->request->getProtocolVersion();
	}

	public function getVerb(): string
	{
		return $this->request->getMethod();
	}

	public function getPath(): string
	{
		return $this->uri->getPath();
	}

	public function getQuery(): string
	{
		return $this->uri->getQuery();
	}

	public function getContentType(): ?string
	{
		$ct = $this->request->getHeader("Content-Type");
		return count($ct) === 1 ? array_shift($ct) : null;
	}

	public function getContent(): ?string
	{
		$body = $this->request->getBody();
		return $body ? $body->__toString() : null;
	}
}
