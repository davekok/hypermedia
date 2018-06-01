<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;

/**
 * Basic http facilities
 */
final class Http
{
	private static $adaptor = "echo";
	private static $request;
	private static $mediaTypes;
	private static $logger;

	/**
	 * Abort
	 *
	 * @param ?Throwable $e  The exception or error causing the abort.
	 */
	public static function abort(?Throwable $e = null, ?string $adaptor = null)
	{
		if ($adaptor !== null) {
			self::$adaptor = $adaptor;
		}
		if ($e !== null) {
			$response = new Response\InternalServerError($e->getMessage(), $e->getCode(), $e);
		} else {
			$response = new Response\InternalServerError("abort");
		}
		return self::response($response);
	}

	/**
	 * Create a request to something sturdy understands.
	 *
	 * @param  mixed  $request  the original request
	 * @param  string $adaptor  if no adaptor is set, choose a matching one
	 * @return Request\Request  a sturdy request object
	 */
	public static function request($request, ?string $adaptor = null): Request\Request
	{
		switch (true) {
			case $request instanceof \Psr\Http\Message\ServerRequestInterface:
				self::$request = new Request\PsrAdaptor($request);
				self::$adaptor = $adaptor ?? "psr";
				break;

			case $request instanceof \Symfony\Component\HttpFoundation\Request:
				self::$request = new Request\SymfonyAdaptor($request);
				self::$adaptor = $adaptor ?? "symfony";
				break;

			case $request instanceof Request\Request:
				self::$request = $request;
				self::$adaptor = $adaptor ?? "sturdy";
				break;

			case is_array($request):
				self::$request = new Request\DefaultAdaptor($request);
				self::$adaptor = $adaptor ?? "array";
				break;

			case $request === null:
				self::$request = new Request\DefaultAdaptor($_SERVER);
				self::$adaptor = $adaptor ?? "echo";
				break;

			default:
				throw new InvalidArgumentException("\$request argument is of unsupported type");
		}
		return self::$request;
	}

	/**
	 * Create a response to something caller understands.
	 *
	 * @param  Response $response  the original response
	 * @param  string   $adaptor   the adaptor
	 * @return a response
	 */
	public static function response(Response\Response $response)
	{
		if (self::$request === null) {
			self::$request = new Request\DefaultAdaptor($_SERVER);
		}
		$response->setProtocolVersion(self::$request->getProtocolVersion());
		switch (self::$adaptor) {
			case "psr":
				$response = new Response\PsrAdaptor($response);
				break;

			case "symfony":
				$response = new Response\SymfonyAdaptor($response);
				break;

			case "sturdy":
				break;

			case "array":
				$response = new Response\ArrayAdaptor($response);
				break;

			case "echo":
				$response = new Response\EchoAdaptor($response);
				break;
		}
		return $response;
	}

	public static function setLogger($logger)
	{
		self::$logger = $logger;
	}

	public static function log(...$data)
	{
		self::$logger->log(...$data);
	}
}
