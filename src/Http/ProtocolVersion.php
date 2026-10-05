<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http;

/**
 * Identifies the HTTP protocol version carried by a message.
 */
enum ProtocolVersion: string
{
    /**
     * HTTP/1.0.
     */
    case Http10 = '1.0';

    /**
     * HTTP/1.1.
     */
    case Http11 = '1.1';

    /**
     * HTTP/2.
     */
    case Http2 = '2';

    /**
     * HTTP/3.
     */
    case Http3 = '3';
}
