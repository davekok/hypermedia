<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Default adaptor for requests.
 */
final class DefaultRequestAdaptor implements Request
{
	public function getProtocolVersion(): string
	{
		$version = $_SERVER['SERVER_PROTOCOL'];
		return substr($version, strpos($version, "/") + 1);
	}

	public function getVerb(): string
	{
		return $_SERVER['REQUEST_METHOD'];
	}

	public function getPath(): string
	{
		$uri = $_SERVER['REQUEST_URI'];
		$p = strpos($uri, "?");
		if ($p !== false) {
			return substr($uri, 0, $p);
		} else {
			return $uri;
		}
	}

	public function getQuery(): string
	{
		$uri = $_SERVER['REQUEST_URI'];
		$p = strpos($uri, "?");
		if ($p !== false) {
			return substr($uri, $p+1);
		} else {
			return "";
		}
	}

	public function getContentType(): ?string
	{
		return $_SERVER['HTTP_CONTENT_TYPE'] ?? null;
	}

	public function getContent(): ?string
	{
		return file_get_content(fopen("php://input"));
	}
}
