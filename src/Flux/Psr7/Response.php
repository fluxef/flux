<?php
declare(strict_types=1);

namespace Flux\Psr7;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{
    use HeaderTrait;

    protected string $protocolversion = '1.1';
    protected int $statuscode = 200;
    protected string $reasonphrase = 'OK';

    protected const REASONS = [
        100 => 'Continue', // 	[RFC7231, Section 6.2.1]
        101 => 'Switching Protocols', // 	[RFC7231, Section 6.2.2]
        102 => 'Processing', // 	[RFC2518]
        103 => 'Early Hints', // 	[RFC8297]
        200 => 'OK', // 	[RFC7231, Section 6.3.1]
        201 => 'Created', // 	[RFC7231, Section 6.3.2]
        202 => 'Accepted', // 	[RFC7231, Section 6.3.3]
        203 => 'Non-Authoritative Information', // 	[RFC7231, Section 6.3.4]
        204 => 'No Content', // 	[RFC7231, Section 6.3.5]
        205 => 'Reset Content', // 	[RFC7231, Section 6.3.6]
        206 => 'Partial Content', // 	[RFC7233, Section 4.1]
        207 => 'Multi-Status', // 	[RFC4918]
        208 => 'Already Reported', // 	[RFC5842]
        226 => 'IM Used', // 	[RFC3229]
        300 => 'Multiple Choices', // 	[RFC7231, Section 6.4.1]
        301 => 'Moved Permanently', // 	[RFC7231, Section 6.4.2]
        302 => 'Found', //  	[RFC7231, Section 6.4.3]
        303 => 'See Other', // 	[RFC7231, Section 6.4.4]
        304 => 'Not Modified', // 	[RFC7232, Section 4.1]
        305 => 'Use Proxy', // 	[RFC7231, Section 6.4.5]
        306 => '(Unused)', // 	[RFC7231, Section 6.4.6]
        307 => 'Temporary Redirect', // 	[RFC7231, Section 6.4.7]
        308 => 'Permanent Redirect', //  	[RFC7538]
        400 => 'Bad Request', // 	[RFC7231, Section 6.5.1]
        401 => 'Unauthorized', // 	[RFC7235, Section 3.1]
        402 => 'Payment Required', // 	[RFC7231, Section 6.5.2]
        403 => 'Forbidden', // 	[RFC7231, Section 6.5.3]
        404 => 'Not Found', // 	[RFC7231, Section 6.5.4]
        405 => 'Method Not Allowed', // 	[RFC7231, Section 6.5.5]
        406 => 'Not Acceptable', // 	[RFC7231, Section 6.5.6]
        407 => 'Proxy Authentication Required', // 	[RFC7235, Section 3.2]
        408 => 'Request Timeout', // 	[RFC7231, Section 6.5.7]
        409 => 'Conflict', // 	[RFC7231, Section 6.5.8]
        410 => 'Gone', // 	[RFC7231, Section 6.5.9]
        411 => 'Length Required', // 	[RFC7231, Section 6.5.10]
        412 => 'Precondition Failed', // 	[RFC7232, Section 4.2][RFC8144, Section 3.2]
        413 => 'Payload Too Large', // 	[RFC7231, Section 6.5.11]
        414 => 'URI Too Long', // 	[RFC7231, Section 6.5.12]
        415 => 'Unsupported Media Type', // 	[RFC7231, Section 6.5.13][RFC7694, Section 3]
        416 => 'Range Not Satisfiable', // 	[RFC7233, Section 4.4]
        417 => 'Expectation Failed', // 	[RFC7231, Section 6.5.14]
        421 => 'Misdirected Request', // 	[RFC7540, Section 9.1.2]
        422 => 'Unprocessable Entity', // 	[RFC4918]
        423 => 'Locked', // 	[RFC4918]
        424 => 'Failed Dependency', // 	[RFC4918]
        425 => 'Too Early', // 	[RFC8470]
        426 => 'Upgrade Required', // 	[RFC7231, Section 6.5.15]
        428 => 'Precondition Required', // 	[RFC6585]
        429 => 'Too Many Requests', // 	[RFC6585]
        431 => 'Request Header Fields Too Large', // 	[RFC6585]
        451 => 'Unavailable For Legal Reasons', // 	[RFC7725]
        500 => 'Internal Server Error', // 	[RFC7231, Section 6.6.1]
        501 => 'Not Implemented', // 	[RFC7231, Section 6.6.2]
        502 => 'Bad Gateway', // 	[RFC7231, Section 6.6.3]
        503 => 'Service Unavailable', // 	[RFC7231, Section 6.6.4]
        504 => 'Gateway Timeout',  // 	[RFC7231, Section 6.6.5]
        505 => 'HTTP Version Not Supported', // 	[RFC7231, Section 6.6.6]
        506 => 'Variant Also Negotiates', // 	[RFC2295]
        507 => 'Insufficient Storage', // 	[RFC4918]
        508 => 'Loop Detected', // 	[RFC5842]
        510 => 'Not Extended', // 	[RFC2774]
        511 => 'Network Authentication Required' // 	[RFC6585]
    ];


    public function __construct(protected ?StreamInterface $body = null)
    {

    }


    public function __clone()
    {
        // the stream can not be cloned
        // if (is_object($this->body))
        //    $this->body = clone($this->body);

    }

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolversion;
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
    public function withProtocolVersion($version): static
    {
        $obj = clone $this;
        $obj->protocolversion = $version;
        return $obj;
    }


    /**
     * Gets the body of the message.
     *
     * @return StreamInterface Returns the body as a stream.
     */
    public function getBody(): StreamInterface
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
    public function withBody(StreamInterface $body): static
    {
        $obj = clone $this;
        $obj->body = $body;
        return $obj;
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int Status code.
     */
    public function getStatusCode(): int
    {
        return $this->statuscode;
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
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @param int $code The 3-digit integer result code to set.
     * @param string $reasonPhrase The reason phrase to use with the
     *     provided status code; if none is provided, implementations MAY
     *     use the defaults as suggested in the HTTP specification.
     * @return static
     * @throws \InvalidArgumentException For invalid status code arguments.
     */
    public function withStatus($code, $reasonPhrase = ''): static
    {
        $obj = clone $this;
        $obj->statuscode = (int)$code;
        if (empty($reasonPhrase) && (isset(self::REASONS[$obj->statuscode])))
            $obj->reasonphrase = self::REASONS[$obj->statuscode];
        else
            $obj->reasonphrase = $reasonPhrase;
        return $obj;
    }

    /**
     * Gets the response reason phrase associated with the status code.
     *
     * Because a reason phrase is not a required element in a response
     * status line, the reason phrase value MAY be null. Implementations MAY
     * choose to return the default RFC 7231 recommended reason phrase (or those
     * listed in the IANA HTTP Status Code Registry) for the response's
     * status code.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @return string Reason phrase; must return an empty string if none present.
     */
    public function getReasonPhrase(): string
    {
        if (isset(self::REASONS[$this->statuscode]))
            return self::REASONS[$this->statuscode];
        else
            return '';

    }
}
