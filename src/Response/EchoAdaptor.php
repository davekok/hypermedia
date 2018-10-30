<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use ArrayAccess, Countable, Iterator;

/**
 * An adaptor for symfony responses.
 */
final class EchoAdaptor
{
	private $response;

	public function __construct(Response $response)
	{
		$this->response = $response;

	}
	public function send()
	{
		if (headers_sent()) return;
		header("HTTP/".$this->response->getProtocolVersion()." ".$this->response->getStatusCode()." ".$this->response->getStatusText());
		header("Date: ".$this->response->getDate()->format("r"));
		if ($location = $this->response->getLocation()) {
			header("Location: ".$location);
		}
		if ($contentType = $this->response->getContentType()) {
			header("Content-Type: ".$contentType);
		}
		if ($content = $this->response->getContent()) {
			echo $content;
		}
	}
}
