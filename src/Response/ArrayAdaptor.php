<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use ArrayAccess, Countable, Iterator;

/**
 * An adaptor for symfony responses.
 */
final class ArrayAdaptor implements ArrayAccess, Countable, Iterator
{
	private $keys = ["protocolVersion", "statusCode", "statusText", "headers", "content"];
	private $response;
	private $headers;
	private $i = 0;

	public function __construct(Response $response)
	{
		$this->response = $response;
		$this->headers = [];
		$this->headers["Date"] = $response->getDate()->format("r");
		if ($location = $response->getLocation()) {
			$this->headers["Location"] = $location;
		}
		if ($contentType = $response->getContentType()) {
			$this->headers["Content-Type"] = $contentType;
		}
	}

	public function setKeys(string $protocolVersionKey, string $statusCodeKey, string $statusTextKey, string $headersKey, string $contentKey): bool
	{
		$this->keys = [$protocolVersionKey, $statusCodeKey, $statusTextKey, $headersKey, $contentKey];
	}

	public function getKeys(): array
	{
		return $this->keys;
	}

	public function offsetExists($offset)
	{
		switch ($offset) {
			case $this->keys[0]: return true;
			case $this->keys[1]: return true;
			case $this->keys[2]: return true;
			case $this->keys[3]: return true;
			case $this->keys[4]: return $response->getContent() !== null;
			default: return false;
		}
	}

	public function offsetGet($offset)
	{
		switch ($offset) {
			case $this->keys[0]: return $response->getProtocolVersion();
			case $this->keys[1]: return $response->getStatusCode();
			case $this->keys[2]: return $response->getStatusText();
			case $this->keys[3]: return $this->headers;
			case $this->keys[4]: return $response->getContent();
			default: return null;
		}
	}

	public function offsetSet($offset, $value)
	{
	}

	public function offsetUnset($offset)
	{
	}

	public function count(): int
	{
		return 5;
	}

	public function current()
	{
		switch ($this->i) {
			case 0: return $response->getProtocolVersion();
			case 1: return $response->getStatusCode();
			case 2: return $response->getStatusText();
			case 3: return $this->headers;
			case 4: return $response->getContent();
			default: return null;
		}
	}

	public function key(): string
	{
		return $this->i < 5 ? $this->keys[$this->i] : null;
	}

	public function next(): void
	{
		++$this->i;
	}

	public function rewind(): void
	{
		$this->i = 0;
	}

	public function valid(): bool
	{
		return $this->i < 5;
	}
}
