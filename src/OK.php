<?php declare(strict_types=1);

namespace Sturdy\Activity;

final class OK implements Response
{
	private $content;

	/**
	 * Get the response status code
	 *
	 * @return int  the status code
	 */
	public function getStatusCode(): int
	{
		return 200;
	}

	/**
	 * Get the response status text
	 *
	 * @return string  the status text
	 */
	public function getStatusText(): string
	{
		return "OK";
	}

	/**
	 * Get content
	 *
	 * @return string
	 */
	public function getContent(): string
	{
		return json_encode($this->content, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Convert response using response builder
	 *
	 * @param ResponseBuilder $rb  the response builder
	 * @return mixed  the response
	 */
	public function convert(ResponseBuilder $rb)
	{
		$responseBuilder->setStatus($this->getStatusCode(), $this->getStatusText());
		$responseBuilder->setContentType('application/json');
		$responseBuilder->setContent($this->getContent());
		return $responseBuilder->getResponse();
	}
}
