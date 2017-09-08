<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Exception;

abstract class Error extends Exception implements Response
{
	/**
	 * Get content
	 *
	 * @return string
	 */
	public function getContent(): string
	{
		$error = ["message"=>$e->getMessage()];
		$code = $e->getCode();
		if ($code > 0) {
			$error["code"] = $code;
		}
		return json_encode(["error"=>$error], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
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
