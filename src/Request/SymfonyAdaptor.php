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
		return $this->request->getQueryString()??"";
	}

	public function getContentType(): ?string
	{
		$contentType = $this->request->getContentType();
		switch ($contentType) {
			case 'json':
				return 'application/json';
			case 'html':
				return 'text/html';
			case 'txt':
				return 'text/plain';
			case 'css':
				return 'text/css';
			case 'xml':
				return 'text/xml';
			case 'rdf':
				return 'application/rdf+xml';
			case 'atom':
				return 'application/atom+xml';
			case 'rss':
				return 'application/rss+xml';
			case 'form':
				return 'application/x-www-form-urlencoded';
			default:
				return $contentType;
		}
	}

	public function getContent(): ?string
	{
		return $this->request->getContent() ?: null;
	}
}
