<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Psr\Http\Message\ResponseInterface;

/**
 * An adaptor for psr responses.
 *
 * I recommend you don't use Psr though. It is not well designed. An
 * interface should only expose what the depending side actually needs,
 * there is no need to expose anything about the providing side in an
 * interface and the dependent will never implement it. This design
 * mixes the depending side and providing side, which is fine for
 * concrete objects put not for interfaces.
 */
final class PsrResponseBuilder implements ResponseBuilder
{
	private $response;

	/**
	 * Constructor
	 *
	 * @param Psr\Http\Message\ResponseInterface $response  the response
	 */
	public function __construct(Psr\Http\Message\ResponseInterface $response)
	{
		$this->response = $response;
	}

	/**
	 * Get the response.
	 *
	 * @return Psr\Http\Message\ResponseInterface  the response
	 */
	public function getResponse()
	{
		return $this->response;
	}

	/**
	 * Set the protocol version to use.
	 *
	 * @param string $version  the protocol version
	 */
	public function setProtocolVersion(string $version): void
	{
		$this->response = $this->response->withProtocolVersion($version);
	}

	/**
	 * Set the http status code.
	 *
	 * @param  int    $code  the http status code
	 * @param  string $text  the http status text
	 */
	public function setStatus(int $code, string $text): void
	{
		$this->response = $this->response->withStatus($code, $text);
	}

	/**
	 * Set the location header
	 *
	 * @param  string $location  the location header
	 */
	public function setLocation(string $location): void
	{
		$this->response = $this->response->withHeader("Location", $location);
	}

	/**
	 * Set the http content type header
	 *
	 * @param  string $contentType  the content type header
	 */
	public function setContentType(string $contentType): void
	{
		$this->response = $this->response->withHeader("Content-Type", $contentType);
	}

	/**
	 * Set content
	 *
	 * @param  string $content  the content
	 */
	public function setContent(string $content): void
	{
		// Is it me or is StreamInterface rather poorly designed, what is wrong with
		// allowing either a string or a resource instead of using this interface.
		$response = $response->withBody(new class($content) implements \Psr\Http\Message\StreamInterface {
			private $content;

			public function __construct(string $content)
			{
				$this->content = $content;
			}

			public function __toString()
			{
				return $this->content;
			}

			public function getSize()
			{
				return strlen($this->content);
			}

			/* START OF POINTLESS FUNCTIONS */

			public function close()
			{
			}

			public function detach()
			{
				return null;
			}

			public function tell()
			{
				throw new \RuntimeException("stream does not support seeking");
			}

			public function eof()
			{
				throw new \RuntimeException("stream does not support ending");
			}

			public function isSeekable()
			{
				return false;
			}

			public function seek($offset, $whence = SEEK_SET)
			{
				throw new \RuntimeException("stream does not support seeking");
			}

			public function rewind()
			{
				throw new \RuntimeException("stream does not support seeking");
			}

			public function isWritable()
			{
				return false;
			}

			public function write($string)
			{
				throw new \RuntimeException("stream does not support writing");
			}

			public function isReadable()
			{
				return false;
			}

			public function read($length)
			{
				throw new \RuntimeException("stream does not support reading, use __toString instead");
			}

			public function getContents()
			{
				throw new \RuntimeException("stream does not support reading, use __toString instead");
			}

			public function getMetadata($key = null)
			{
				return $key === null ? [] : null;
			}

			/* END OF POINTLESS FUNCTIONS */
		});
	}
}
