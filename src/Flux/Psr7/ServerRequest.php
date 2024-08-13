<?php
declare(strict_types=1);

namespace Flux\Psr7;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ServerRequestInterface;

class ServerRequest implements ServerRequestInterface
{

    use HeaderTrait;

    protected bool $hasproxy = false;
    protected array $frontend = array();
    protected array $backend = array();
    protected array $query = array();
    protected array $uploadedFiles = array();

    protected array $attributes = array();

    protected string $protocolversion = '';
    protected UriInterface $uri;
    protected string $method = '';

    public function __construct(protected array $request,
                                protected array $get,
                                protected array $post,
                                protected array $cookies,
                                protected array $files,
                                protected StreamInterface $body)
    {
        $this->initialize();
    }

    public static function createFromGlobals(): ServerRequestInterface
    {
        return new static($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES, new ServerInputStream());
    }

    public function __clone()
    {
        if (is_object($this->body))
            $this->body = clone($this->body);

        if (is_object($this->uri))
            $this->uri = clone($this->uri);

        $ufa = array();

        foreach ($this->uploadedFiles as $file) {
            $ufa[] = clone($file);
        }

        $this->uploadedFiles = $ufa;
    }

    protected function initialize()
    {
        $this->hasproxy = isset($this->request['HTTP_X_FORWARDED_FOR'])
            && isset($this->request['HTTP_X_FORWARDED_URI'])
            && isset($this->request['HTTP_X_FORWARDED_HOST_PORT'])
            && isset($this->request['HTTP_X_FORWARDED_PROTO']);

        if (!isset($this->request['REMOTE_ADDR']))
            throw new MissingParameterException('REMOTE_ADDR not set');

        if (!isset($this->request['REQUEST_URI']))
            throw new MissingParameterException('REQUEST_URI not set');

        if (!isset($this->request['SERVER_PORT']))
            throw new MissingParameterException('SERVER_PORT not set');

        if (!isset($this->request['REQUEST_SCHEME']))
            throw new MissingParameterException('REQUEST_SCHEME not set');

        if ($this->hasproxy) {

            $this->frontend['REMOTE_ADDR'] = $this->request['HTTP_X_FORWARDED_FOR'];
            $this->frontend['REQUEST_URI'] = urldecode($this->request['HTTP_X_FORWARDED_URI']);
            $this->frontend['SERVER_PORT'] = $this->request['HTTP_X_FORWARDED_HOST_PORT'];
            $this->frontend['REQUEST_SCHEME'] = $this->request['HTTP_X_FORWARDED_PROTO'];

            if (isset($this->request['HTTP_X_FORWARDED_HOST']))
                $this->frontend['HTTP_HOST'] = strtolower($this->request['HTTP_X_FORWARDED_HOST']);

            if (isset($this->request['HTTP_HOST']))
                $this->backend['HTTP_HOST'] = strtolower($this->request['HTTP_HOST']);

            $this->backend['REMOTE_ADDR'] = $this->request['REMOTE_ADDR'];
            $this->backend['REQUEST_URI'] = urldecode($this->request['REQUEST_URI']);
            $this->backend['SERVER_PORT'] = $this->request['SERVER_PORT'];
            $this->backend['REQUEST_SCHEME'] = $this->request['REQUEST_SCHEME'];

        } else {
            $this->frontend['HTTP_HOST'] = $this->request['HTTP_HOST'];
            $this->frontend['REMOTE_ADDR'] = $this->request['REMOTE_ADDR'];
            $this->frontend['REQUEST_URI'] = urldecode($this->request['REQUEST_URI']);
            $this->frontend['SERVER_PORT'] = $this->request['SERVER_PORT'];
            $this->frontend['REQUEST_SCHEME'] = $this->request['REQUEST_SCHEME'];
        }

        $this->frontend['QUERY_STRING'] = $this->request['QUERY_STRING'];

        $uri = $this->frontend['REQUEST_URI'];

        $this->uri = new Uri(urldecode($_SERVER['SCRIPT_URI']));    // full uri

        // für PHP_SELF den Query-String, falls vorhanden, abtrennen

        $pos = strrpos($uri, '?');
        if ($pos === false)  // drei Gleichheitszeichen, wichtig! Kein ? gefunden
            $this->frontend['PHP_SELF'] = $uri;
        else
            $this->frontend['PHP_SELF'] = substr($uri, 0, $pos);

        $PHP_SELF = $this->frontend['PHP_SELF'];

        $myuri = substr($PHP_SELF, 1); // führendes slash entfernen

        $pos = strrpos($myuri, '/');
        if ($pos === false) { // drei Gleichheitszeichen, wichtig!
            $cat = '';
        } else {
            $cat = substr($myuri, 0, $pos);
        }


        $this->frontend['path'] = $cat;

        foreach ($_SERVER as $field => $value) {
            if (strcmp($field, 'HTTP_COOKIE') == 0) // there are in $_COOKIES superglobal
                continue;
            if (strncasecmp($field, 'HTTP_', 5) == 0) {
                $field = substr($field, 5);
                $this->initHeader($field, $value);
            }
        }


        // $_SERVER['SERVER_PROTOCOL']	HTTP/1.1

        $proto = explode('/', $_SERVER['SERVER_PROTOCOL']);

        if (isset($proto[1]) && ($proto[1] > 0))
            $this->protocolversion = $proto[1];

        if (!empty($this->request['QUERY_STRING'])) {
            foreach(explode('&', $this->request['QUERY_STRING']) AS $line) {
                if (str_contains($line,'=')) {
                    $aline = explode('=', $line);
                    $this->query[$aline[0]] = $aline[1];
                }
            }
        }

        $this->method = strtolower($this->request['REQUEST_METHOD']);

        $this->processUploadedFiles();
    }

    protected function processUploadedFiles()
    {
        // TODO Implement processUploadedFiles()
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
        $obj = clone($this);
        $obj->body = $body;
        return $obj;
    }

    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget(): string
    {
        return (string)$this->uri;
    }

    /**
     * Return an instance with the specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request target.
     *
     * @link http://tools.ietf.org/html/rfc7230#section-5.3 (for the various
     *     request-target forms allowed in request messages)
     * @param mixed $requestTarget
     * @return static
     */
    public function withRequestTarget($requestTarget): static
    {
        $obj = clone($this);
        $obj->uri = $requestTarget;
        return $obj;
    }

    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string Returns the request method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Return an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request method.
     *
     * @param string $method Case-sensitive method.
     * @return static
     * @throws \InvalidArgumentException for invalid HTTP methods.
     */
    public function withMethod($method): static
    {
        $obj = clone($this);
        $obj->method = $method;
        return $obj;
    }

    /**
     * Retrieves the URI instance.
     *
     * This method MUST return a UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @return UriInterface Returns a UriInterface instance
     *     representing the URI of the request.
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Returns an instance with the provided URI.
     *
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     *
     * - If the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param UriInterface $uri New request URI to use.
     * @param bool $preserveHost Preserve the original state of the Host header.
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false): static
    {
        $obj = clone($this);
        $obj->uri = $uri;
        if ($preserveHost) {
            $host = $obj->uri->getHost();
            if (!empty($host))
                $obj->uri = $uri->withHost($host);
        }
        return $obj;
    }

    /**
     * Retrieve server parameters.
     *
     * Retrieves data related to the incoming request environment,
     * typically derived from PHP's $_SERVER superglobal. The data IS NOT
     * REQUIRED to originate from $_SERVER.
     *
     * @return array
     */
    public function getServerParams(): array
    {
        return $this->request;
    }

    /**
     * Retrieve cookies.
     *
     * Retrieves cookies sent by the client to the server.
     *
     * The data MUST be compatible with the structure of the $_COOKIE
     * superglobal.
     *
     * @return array
     */
    public function getCookieParams(): array
    {
        return $this->cookies;
    }

    /**
     * Return an instance with the specified cookies.
     *
     * The data IS NOT REQUIRED to come from the $_COOKIE superglobal, but MUST
     * be compatible with the structure of $_COOKIE. Typically, this data will
     * be injected at instantiation.
     *
     * This method MUST NOT update the related Cookie header of the request
     * instance, nor related values in the server params.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated cookie values.
     *
     * @param array $cookies Array of key/value pairs representing cookies.
     * @return static
     */
    public function withCookieParams(array $cookies): static
    {
        $obj = clone($this);
        $obj->cookies = $cookies;
        return $obj;

    }

    /**
     * Retrieve query string arguments.
     *
     * Retrieves the deserialized query string arguments, if any.
     *
     * Note: the query params might not be in sync with the URI or server
     * params. If you need to ensure you are only getting the original
     * values, you may need to parse the query string from `getUri()->getQuery()`
     * or from the `QUERY_STRING` server param.
     *
     * @return array
     */
    public function getQueryParams(): array
    {
        return $this->query;
    }

    /**
     * Return an instance with the specified query string arguments.
     *
     * These values SHOULD remain immutable over the course of the incoming
     * request. They MAY be injected during instantiation, such as from PHP's
     * $_GET superglobal, or MAY be derived from some other value such as the
     * URI. In cases where the arguments are parsed from the URI, the data
     * MUST be compatible with what PHP's parse_str() would return for
     * purposes of how duplicate query parameters are handled, and how nested
     * sets are handled.
     *
     * Setting query string arguments MUST NOT change the URI stored by the
     * request, nor the values in the server params.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated query string arguments.
     *
     * @param array $query Array of query string arguments, typically from
     *     $_GET.
     * @return static
     */
    public function withQueryParams(array $query): static
    {
        $obj = clone($this);
        $obj->query = $query;
        return $obj;
    }

    /**
     * Retrieve normalized file upload data.
     *
     * This method returns upload metadata in a normalized tree, with each leaf
     * an instance of Psr\Http\Message\UploadedFileInterface.
     *
     * These values MAY be prepared from $_FILES or the message body during
     * instantiation, or MAY be injected via withUploadedFiles().
     *
     * @return array An array tree of UploadedFileInterface instances; an empty
     *     array MUST be returned if no data is present.
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * Create a new instance with the specified uploaded files.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated body parameters.
     *
     * @param array $uploadedFiles An array tree of UploadedFileInterface instances.
     * @return static
     * @throws \InvalidArgumentException if an invalid structure is provided.
     */
    public function withUploadedFiles(array $uploadedFiles): static
    {
        $obj = clone($this);
        $obj->uploadedFiles = $uploadedFiles;
        return $obj;
    }

    /**
     * Retrieve any parameters provided in the request body.
     *
     * If the request Content-Type is either application/x-www-form-urlencoded
     * or multipart/form-data, and the request method is POST, this method MUST
     * return the contents of $_POST.
     *
     * Otherwise, this method may return any results of deserializing
     * the request body content; as parsing returns structured content, the
     * potential types MUST be arrays or objects only. A null value indicates
     * the absence of body content.
     *
     * @return null|array|object The deserialized body parameters, if any.
     *     These will typically be an array or object.
     */
    public function getParsedBody(): null|array|object
    {
        return $this->post;
    }

    /**
     * Return an instance with the specified body parameters.
     *
     * These MAY be injected during instantiation.
     *
     * If the request Content-Type is either application/x-www-form-urlencoded
     * or multipart/form-data, and the request method is POST, use this method
     * ONLY to inject the contents of $_POST.
     *
     * The data IS NOT REQUIRED to come from $_POST, but MUST be the results of
     * deserializing the request body content. Deserialization/parsing returns
     * structured data, and, as such, this method ONLY accepts arrays or objects,
     * or a null value if nothing was available to parse.
     *
     * As an example, if content negotiation determines that the request data
     * is a JSON payload, this method could be used to create a request
     * instance with the deserialized parameters.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated body parameters.
     *
     * @param null|array|object $data The deserialized body data. This will
     *     typically be in an array or object.
     * @return static
     * @throws \InvalidArgumentException if an unsupported argument type is
     *     provided.
     */
    public function withParsedBody($data): static
    {
        $obj = clone($this);
        $obj->post = $data;     // TODO conversion, if required
        return $obj;
    }

    /**
     * Retrieve attributes derived from the request.
     *
     * The request "attributes" may be used to allow injection of any
     * parameters derived from the request: e.g., the results of path
     * match operations; the results of decrypting cookies; the results of
     * deserializing non-form-encoded message bodies; etc. Attributes
     * will be application and request specific, and CAN be mutable.
     *
     * @return array Attributes derived from the request.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Retrieve a single derived request attribute.
     *
     * Retrieves a single derived request attribute as described in
     * getAttributes(). If the attribute has not been previously set, returns
     * the default value as provided.
     *
     * This method obviates the need for a hasAttribute() method, as it allows
     * specifying a default value to return if the attribute is not found.
     *
     * @param string $name The attribute name.
     * @param mixed $default Default value to return if the attribute does not exist.
     * @return mixed
     * @see getAttributes()
     */
    public function getAttribute($name, $default = null): mixed
    {
        if (!isset($this->attributes[$name]))
            return $default;

        return $this->attributes[$name];

    }

    /**
     * Return an instance with the specified derived request attribute.
     *
     * This method allows setting a single derived request attribute as
     * described in getAttributes().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated attribute.
     *
     * @param string $name The attribute name.
     * @param mixed $value The value of the attribute.
     * @return static
     * @see getAttributes()
     */
    public function withAttribute($name, $value): static
    {
        $obj = clone($this);
        $obj->attributes[$name] = $value;
        return $obj;
    }

    /**
     * Return an instance that removes the specified derived request attribute.
     *
     * This method allows removing a single derived request attribute as
     * described in getAttributes().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the attribute.
     *
     * @param string $name The attribute name.
     * @return static
     * @see getAttributes()
     */
    public function withoutAttribute($name): static
    {
        $obj = clone($this);
        unset($obj->attributes[$name]);
        return $obj;
    }

}
