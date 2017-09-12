<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

/**
 * An adaptor for psr responses.
 */
final class PsrAdaptor implements \Psr\Http\Message\ResponseInterface
{
	private $protocolVersion;
	private $statusCode;
	private $statusText;
	private $headers;
	private $body;

	/**
	 * Constructor
	 *
	 * @param Response $response  the response
	 */
	public function __construct(Response $response)
	{
		$this->protocolVersion = $this->response->getProtocolVersion();
		$this->statusCode = $this->response->getStatusCode();
		$this->statusText = $this->response->getStatusText();
		$this->headers = [];
		$this->headers["Date"][] = $this->response->getDate();
		if ($l = $this->response->getLocation()) {
			$this->headers["Location"][] = $l;
		}
		if ($ct = $this->response->getContentType()) {
			$this->headers["Content-Type"][] = $ct;
		}
		if ($content = $this->response->getContent()) {
			$this->body = new class($content) implements \Psr\Http\Message\StreamInterface {
				private $content;
				private $stream;

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

				public function close()
				{
					if ($this->stream !== null) {
						fclose($this->stream);
						$this->stream = null;
					}
				}

				public function detach()
				{
					$stream = $this->stream;
					$this->stream = null;
					return $stream;
				}

				public function tell()
				{
					if (false === ($tell = ftell($this->getStream()))) {
						throw new \RuntimeException("tell failed");
					}
					return $tell;
				}

				public function eof()
				{
					return feof($this->getStream());
				}

				public function isSeekable()
				{
					return true;
				}

				public function seek($offset, $whence = SEEK_SET)
				{
					if (fseek($this->getStream(), $offset, $whence) === -1) {
						throw new \RuntimeException("seek failed");
					}
				}

				public function rewind()
				{
					if (rewind($this->getStream(), 0) === false) {
						throw new \RuntimeException("rewind failed");
					}
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
					return true;
				}

				public function read($length)
				{
					return fread($this->getStream(), $length);
				}

				public function getContents()
				{
					return stream_get_contents($this->getStream());
				}

				public function getMetadata($key = null)
				{
					return $key === null ? [] : null;
				}

				private function getStream()
				{
					if ($this->stream === null) {
						$this->stream = fopen("php://tmp", "r+");
						fwrite($this->stream, $this->content);
						fseek($this->stream, 0);
					}
					return $this->stream;
				}

				/* END OF POINTLESS FUNCTIONS */
			};
		}
	}

	/**
	 * Retrieves the HTTP protocol version as a string.
	 *
	 * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
	 *
	 * @return string HTTP protocol version.
	 */
	public function getProtocolVersion()
	{
		return $this->protocolVersion;
	}

	/**
	 * Return an instance with the specified HTTP protocol version.
	 *
	 * The version string MUST contain only the HTTP version number (e.g.,
	 * "1.1", "1.0").
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new protocol version.
	 *
	 * @param string $version HTTP protocol version
	 * @return static
	 */
	public function withProtocolVersion($version)
	{
		$self = clone $this;
		$self->protocolVersion = $version;
		return $self;
	}

	/**
	 * Retrieves all message header values.
	 *
	 * The keys represent the header name as it will be sent over the wire, and
	 * each value is an array of strings associated with the header.
	 *
	 *     // Represent the headers as a string
	 *     foreach ($message->getHeaders() as $name => $values) {
	 *         echo $name . ': ' . implode(', ', $values);
	 *     }
	 *
	 *     // Emit headers iteratively:
	 *     foreach ($message->getHeaders() as $name => $values) {
	 *         foreach ($values as $value) {
	 *             header(sprintf('%s: %s', $name, $value), false);
	 *         }
	 *     }
	 *
	 * While header names are not case-sensitive, getHeaders() will preserve the
	 * exact case in which headers were originally specified.
	 *
	 * @return string[][] Returns an associative array of the message's headers.
	 *     Each key MUST be a header name, and each value MUST be an array of
	 *     strings for that header.
	 */
	public function getHeaders()
	{
		return $this->headers();
	}

	/**
	 * Checks if a header exists by the given case-insensitive name.
	 *
	 * @param string $name Case-insensitive header field name.
	 * @return bool Returns true if any header names match the given header
	 *     name using a case-insensitive string comparison. Returns false if
	 *     no matching header name is found in the message.
	 */
	public function hasHeader($name)
	{
		foreach ($this->headers as $key => $lines) {
			if (strcasecmp($key, $name) === 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Retrieves a message header value by the given case-insensitive name.
	 *
	 * This method returns an array of all the header values of the given
	 * case-insensitive header name.
	 *
	 * If the header does not appear in the message, this method MUST return an
	 * empty array.
	 *
	 * @param string $name Case-insensitive header field name.
	 * @return string[] An array of string values as provided for the given
	 *    header. If the header does not appear in the message, this method MUST
	 *    return an empty array.
	 */
	public function getHeader($name)
	{
		foreach ($this->headers as $key => $lines) {
			if (strcasecmp($key, $name) === 0) {
				return $lines;
			}
		}
		return [];
	}

	/**
	 * Retrieves a comma-separated string of the values for a single header.
	 *
	 * This method returns all of the header values of the given
	 * case-insensitive header name as a string concatenated together using
	 * a comma.
	 *
	 * NOTE: Not all header values may be appropriately represented using
	 * comma concatenation. For such headers, use getHeader() instead
	 * and supply your own delimiter when concatenating.
	 *
	 * If the header does not appear in the message, this method MUST return
	 * an empty string.
	 *
	 * @param string $name Case-insensitive header field name.
	 * @return string A string of values as provided for the given header
	 *    concatenated together using a comma. If the header does not appear in
	 *    the message, this method MUST return an empty string.
	 */
	public function getHeaderLine($name)
	{
		foreach ($this->headers as $key => $lines) {
			if (strcasecmp($key, $name) === 0) {
				return implode(",", $lines);
			}
		}
		return "";
	}

	/**
	 * Return an instance with the provided value replacing the specified header.
	 *
	 * While header names are case-insensitive, the casing of the header will
	 * be preserved by this function, and returned from getHeaders().
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new and/or updated header and value.
	 *
	 * @param string $name Case-insensitive header field name.
	 * @param string|string[] $value Header value(s).
	 * @return static
	 * @throws \InvalidArgumentException for invalid header names or values.
	 */
	public function withHeader($name, $value)
	{
		$self = clone $this;
		foreach ($self->headers as $key => $lines) {
			if (strcasecmp($key, $name) === 0) {
				$name = $key;
				break;
			}
		}
		if (is_array($value)) {
			$self->headers[$name] = $value;
		} else {
			$self->headers[$name] = [$value];
		}
		return $self;
	}

	/**
	 * Return an instance with the specified header appended with the given value.
	 *
	 * Existing values for the specified header will be maintained. The new
	 * value(s) will be appended to the existing list. If the header did not
	 * exist previously, it will be added.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new header and/or value.
	 *
	 * @param string $name Case-insensitive header field name to add.
	 * @param string|string[] $value Header value(s).
	 * @return static
	 * @throws \InvalidArgumentException for invalid header names.
	 * @throws \InvalidArgumentException for invalid header values.
	 */
	public function withAddedHeader($name, $value)
	{
		$self = clone $this;
		foreach ($self->headers as $key => $lines) {
			if (strcasecmp($key, $name) === 0) {
				$name = $key;
				break;
			}
		}
		if (is_array($value)) {
			$self->headers[$name] = array_merge($self->headers[$name], $value);
		} else {
			$self->headers[$name][] = $value;
		}
		return $self;
	}

	/**
	 * Return an instance without the specified header.
	 *
	 * Header resolution MUST be done without case-sensitivity.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that removes
	 * the named header.
	 *
	 * @param string $name Case-insensitive header field name to remove.
	 * @return static
	 */
	public function withoutHeader($name)
	{
		$self = clone $this;
		foreach ($self->headers as $key => $lines) {
			if (strcasecmp($key, $name) === 0) {
				unset($self->headers[$key]);
				break;
			}
		}
		return $self;
	}

	/**
	 * Gets the body of the message.
	 *
	 * @return StreamInterface Returns the body as a stream.
	 */
	public function getBody()
	{
		return $this->body;
	}

	/**
	 * Return an instance with the specified message body.
	 *
	 * The body MUST be a StreamInterface object.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return a new instance that has the
	 * new body stream.
	 *
	 * @param StreamInterface $body Body.
	 * @return static
	 * @throws \InvalidArgumentException When the body is not valid.
	 */
	public function withBody(StreamInterface $body)
	{
		$self = clone $this;
		$self->body = $this->body;
		return $self;
	}

	/**
	 * Gets the response status code.
	 *
	 * The status code is a 3-digit integer result code of the server's attempt
	 * to understand and satisfy the request.
	 *
	 * @return int Status code.
	 */
	public function getStatusCode()
	{
		return $this->statusCode;
	}

	/**
	 * Return an instance with the specified status code and, optionally, reason phrase.
	 *
	 * If no reason phrase is specified, implementations MAY choose to default
	 * to the RFC 7231 or IANA recommended reason phrase for the response's
	 * status code.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated status and reason phrase.
	 *
	 * @see http://tools.ietf.org/html/rfc7231#section-6
	 * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
	 * @param int $code The 3-digit integer result code to set.
	 * @param string $reasonPhrase The reason phrase to use with the
	 *     provided status code; if none is provided, implementations MAY
	 *     use the defaults as suggested in the HTTP specification.
	 * @return static
	 * @throws \InvalidArgumentException For invalid status code arguments.
	 */
	public function withStatus($code, $reasonPhrase = '')
	{
		switch ($code) {
			case 100:
				$reasonPhrase = "Continue";
				break;
			case 101:
				$reasonPhrase = "Switching Protocols";
				break;
			case 102:
				$reasonPhrase = "Processing";
				break;
			case 200:
				$reasonPhrase = "OK";
				break;
			case 201:
				$reasonPhrase = "Created";
				break;
			case 202:
				$reasonPhrase = "Accepted";
				break;
			case 203:
				$reasonPhrase = "Non-Authoritative Information";
				break;
			case 204:
				$reasonPhrase = "No Content";
				break;
			case 205:
				$reasonPhrase = "Reset Content";
				break;
			case 206:
				$reasonPhrase = "Partial Content";
				break;
			case 207:
				$reasonPhrase = "Multi-Status";
				break;
			case 208:
				$reasonPhrase = "Already Reported";
				break;
			case 226:
				$reasonPhrase = "IM Used";
				break;
			case 300:
				$reasonPhrase = "Multiple Choices";
				break;
			case 301:
				$reasonPhrase = "Moved Permanently";
				break;
			case 302:
				if ($this->protocolVerion === "1.0") {
					$reasonPhrase = "Moved Temporarily";
				} else {
					$reasonPhrase = "Found";
				}
				break;
			case 303:
				$reasonPhrase = "See Other";
				break;
			case 304:
				$reasonPhrase = "Not Modified";
				break;
			case 305:
				$reasonPhrase = "Use Proxy";
				break;
			case 306:
				throw new \InvalidArgumentException("306 status code is reserved and should not be used.");
				break;
			case 307:
				$reasonPhrase = "Temporary Redirect";
				break;
			case 308:
				$reasonPhrase = "Permanent Redirect";
				break;
			case 400:
				$reasonPhrase = "Bad Request";
				break;
			case 401:
				$reasonPhrase = "Unauthorized";
				break;
			case 402:
				$reasonPhrase = "Payment Required";
				break;
			case 403:
				$reasonPhrase = "Forbidden";
				break;
			case 404:
				$reasonPhrase = "Not Found";
				break;
			case 405:
				$reasonPhrase = "Method Not Allowed";
				break;
			case 406:
				$reasonPhrase = "Not Acceptable";
				break;
			case 407:
				$reasonPhrase = "Proxy Authentication Required";
				break;
			case 408:
				$reasonPhrase = "Request Timeout";
				break;
			case 409:
				$reasonPhrase = "Conflict";
				break;
			case 410:
				$reasonPhrase = "Gone";
				break;
			case 411:
				$reasonPhrase = "Length Required";
				break;
			case 412:
				$reasonPhrase = "Precondition Failed";
				break;
			case 413:
				$reasonPhrase = "Payload Too Large";
				break;
			case 414:
				$reasonPhrase = "URI Too Long";
				break;
			case 415:
				$reasonPhrase = "Unsupported Media Type";
				break;
			case 416:
				$reasonPhrase = "Range Not Satisfiable";
				break;
			case 417:
				$reasonPhrase = "Expectation Failed";
				break;
			case 421:
				$reasonPhrase = "Misdirected Request";
				break;
			case 422:
				$reasonPhrase = "Unprocessable Entity";
				break;
			case 423:
				$reasonPhrase = "Locked";
				break;
			case 424:
				$reasonPhrase = "Failed Dependency";
				break;
			case 426:
				$reasonPhrase = "Upgrade Required";
				break;
			case 428:
				$reasonPhrase = "Precondition Required";
				break;
			case 429:
				$reasonPhrase = "Too Many Requests";
				break;
			case 431:
				$reasonPhrase = "Request Header Fields Too Large";
				break;
			case 451:
				$reasonPhrase = "Unavailable For Legal Reasons";
				break;
			case 500:
				$reasonPhrase = "Internal Server Error";
				break;
			case 501:
				$reasonPhrase = "Not Implemented";
				break;
			case 502:
				$reasonPhrase = "Bad Gateway";
				break;
			case 503:
				$reasonPhrase = "Service Unavailable";
				break;
			case 504:
				$reasonPhrase = "Gateway Timeout";
				break;
			case 505:
				$reasonPhrase = "HTTP Version Not Supported";
				break;
			case 506:
				$reasonPhrase = "Variant Also Negotiates";
				break;
			case 507:
				$reasonPhrase = "Insufficient Storage";
				break;
			case 508:
				$reasonPhrase = "Loop Detected";
				break;
			case 510:
				$reasonPhrase = "Not Extended";
				break;
			case 511:
				$reasonPhrase = "Network Authentication Required";
				break;
			default:
				if ($code < 100 or $code > 599) {
					throw new \InvalidArgumentException("Invalid status code");
				}
		}
		$self = clone $this;
		$self->statusCode = $code;
		$self->statusText = $reasonPhrase;
		return $self;
	}

	/**
	 * Gets the response reason phrase associated with the status code.
	 *
	 * Because a reason phrase is not a required element in a response
	 * status line, the reason phrase value MAY be empty. Implementations MAY
	 * choose to return the default RFC 7231 recommended reason phrase (or those
	 * listed in the IANA HTTP Status Code Registry) for the response's
	 * status code.
	 *
	 * @see http://tools.ietf.org/html/rfc7231#section-6
	 * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
	 * @return string Reason phrase; must return an empty string if none present.
	 */
	public function getReasonPhrase()
	{
		return $this->statusText;
	}
}
