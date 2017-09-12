<?php declare(strict_types=1);

namespace Sturdy\Activity\Request;

/**
 * An adaptor for symfony requests.
 */
final class SymfonyAdaptor implements Request
{
	private $request;

	public function __construct(\Symfony\Component\HttpFoundation\Request $request)
	{
		$this->request = $request;
	}

	public function getProtocolVersion(): string
	{
		$version = $this->request->server->get('SERVER_PROTOCOL');
		return substr($version, strpos($version, "/") + 1);
	}

	public function getVerb(): string
	{
		return $this->request->getMethod();
	}

	public function getPath(): string
	{
		return $this->request->getBasePath().$this->request->getPathInfo();
	}

	public function getQuery(): string
	{
		return $this->request->getQueryString();
	}

	public function getContentType(): ?string
	{
		return $this->request->getContentType();
	}

	public function getContent(): ?string
	{
		return $this->request->getContent() ?: null;
	}
}
