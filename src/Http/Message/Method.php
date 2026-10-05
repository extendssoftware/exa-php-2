<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Message;

/**
 * Identifies supported HTTP request methods.
 */
enum Method: string
{
    /**
     * The GET request method.
     */
    case Get = 'GET';

    /**
     * The HEAD request method.
     */
    case Head = 'HEAD';

    /**
     * The POST request method.
     */
    case Post = 'POST';

    /**
     * The PUT request method.
     */
    case Put = 'PUT';

    /**
     * The DELETE request method.
     */
    case Delete = 'DELETE';

    /**
     * The CONNECT request method.
     */
    case Connect = 'CONNECT';

    /**
     * The OPTIONS request method.
     */
    case Options = 'OPTIONS';

    /**
     * The TRACE request method.
     */
    case Trace = 'TRACE';

    /**
     * The PATCH request method.
     */
    case Patch = 'PATCH';
}
