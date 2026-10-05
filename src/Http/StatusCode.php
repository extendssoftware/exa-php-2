<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http;

/**
 * Identifies supported HTTP response status codes.
 */
enum StatusCode: int
{
    /**
     * Continue.
     */
    case Continue = 100;

    /**
     * Switching Protocols.
     */
    case SwitchingProtocols = 101;

    /**
     * Processing.
     */
    case Processing = 102;

    /**
     * Early Hints.
     */
    case EarlyHints = 103;

    /**
     * OK.
     */
    case Ok = 200;

    /**
     * Created.
     */
    case Created = 201;

    /**
     * Accepted.
     */
    case Accepted = 202;

    /**
     * Non-Authoritative Information.
     */
    case NonAuthoritativeInformation = 203;

    /**
     * No Content.
     */
    case NoContent = 204;

    /**
     * Reset Content.
     */
    case ResetContent = 205;

    /**
     * Partial Content.
     */
    case PartialContent = 206;

    /**
     * Multi Status.
     */
    case MultiStatus = 207;

    /**
     * Already Reported.
     */
    case AlreadyReported = 208;

    /**
     * IM Used.
     */
    case ImUsed = 226;

    /**
     * Multiple Choices.
     */
    case MultipleChoices = 300;

    /**
     * Moved Permanently.
     */
    case MovedPermanently = 301;

    /**
     * Found.
     */
    case Found = 302;

    /**
     * See Other.
     */
    case SeeOther = 303;

    /**
     * Not Modified.
     */
    case NotModified = 304;

    /**
     * Temporary Redirect.
     */
    case TemporaryRedirect = 307;

    /**
     * Permanent Redirect.
     */
    case PermanentRedirect = 308;

    /**
     * Bad Request.
     */
    case BadRequest = 400;

    /**
     * Unauthorized.
     */
    case Unauthorized = 401;

    /**
     * Payment Required.
     */
    case PaymentRequired = 402;

    /**
     * Forbidden.
     */
    case Forbidden = 403;

    /**
     * Not Found.
     */
    case NotFound = 404;

    /**
     * Method Not Allowed.
     */
    case MethodNotAllowed = 405;

    /**
     * Not Acceptable.
     */
    case NotAcceptable = 406;

    /**
     * Proxy Authentication Required.
     */
    case ProxyAuthenticationRequired = 407;

    /**
     * Request Timeout.
     */
    case RequestTimeout = 408;

    /**
     * Conflict.
     */
    case Conflict = 409;

    /**
     * Gone.
     */
    case Gone = 410;

    /**
     * Length Required.
     */
    case LengthRequired = 411;

    /**
     * Precondition Failed.
     */
    case PreconditionFailed = 412;

    /**
     * Content Too Large.
     */
    case ContentTooLarge = 413;

    /**
     * URI Too Long.
     */
    case UriTooLong = 414;

    /**
     * Unsupported Media Type.
     */
    case UnsupportedMediaType = 415;

    /**
     * Range Not Satisfiable.
     */
    case RangeNotSatisfiable = 416;

    /**
     * Expectation Failed.
     */
    case ExpectationFailed = 417;

    /**
     * Misdirected Request.
     */
    case MisdirectedRequest = 421;

    /**
     * Unprocessable Content.
     */
    case UnprocessableContent = 422;

    /**
     * Locked.
     */
    case Locked = 423;

    /**
     * Failed Dependency.
     */
    case FailedDependency = 424;

    /**
     * Too Early.
     */
    case TooEarly = 425;

    /**
     * Upgrade Required.
     */
    case UpgradeRequired = 426;

    /**
     * Precondition Required.
     */
    case PreconditionRequired = 428;

    /**
     * Too Many Requests.
     */
    case TooManyRequests = 429;

    /**
     * Request Header Fields Too Large.
     */
    case RequestHeaderFieldsTooLarge = 431;

    /**
     * Unavailable For Legal Reasons.
     */
    case UnavailableForLegalReasons = 451;

    /**
     * Internal Server Error.
     */
    case InternalServerError = 500;

    /**
     * Not Implemented.
     */
    case NotImplemented = 501;

    /**
     * Bad Gateway.
     */
    case BadGateway = 502;

    /**
     * Service Unavailable.
     */
    case ServiceUnavailable = 503;

    /**
     * Gateway Timeout.
     */
    case GatewayTimeout = 504;

    /**
     * HTTP Version Not Supported.
     */
    case HttpVersionNotSupported = 505;

    /**
     * Variant Also Negotiates.
     */
    case VariantAlsoNegotiates = 506;

    /**
     * Insufficient Storage.
     */
    case InsufficientStorage = 507;

    /**
     * Loop Detected.
     */
    case LoopDetected = 508;

    /**
     * Network Authentication Required.
     */
    case NetworkAuthenticationRequired = 511;
}
