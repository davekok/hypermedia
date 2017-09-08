<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * An adaptor for array requests.
 */
final class ArrayRequestAdaptor implements Request
{
	private $request;

	public function __construct(array $request)
	{
		$this->request = $request;
	}

	public function getProtocolVersion(): string
	{
		return $this->request['protocolVersion'] ?? $this->request['version'] ?? "1.1";
	}

	public function getVerb(): string
	{
		return $this->request['method'] ?? $this->request['verb'] ?? "GET";
	}

	public function getPath(): string
	{
		return $this->request['path'] ?? "/";
	}

	public function getQuery(): string
	{
		return $this->request['query'] ?? "";
	}

	public function getContentType(): ?string
	{
		return $this->request['contentType'] ?? "";
	}

	public function getContent(): ?string
	{
		return $this->request['content'] ?? "";
	}
}
