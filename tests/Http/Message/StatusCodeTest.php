<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http\Message;

use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use PHPUnit\Framework\TestCase;

final class StatusCodeTest extends TestCase
{
    public function testNamedCodesPreserveTheirNumericValues(): void
    {
        self::assertSame(100, StatusCode::Continue->value);
        self::assertSame(101, StatusCode::SwitchingProtocols->value);
        self::assertSame(102, StatusCode::Processing->value);
        self::assertSame(103, StatusCode::EarlyHints->value);
        self::assertSame(200, StatusCode::Ok->value);
        self::assertSame(201, StatusCode::Created->value);
        self::assertSame(202, StatusCode::Accepted->value);
        self::assertSame(203, StatusCode::NonAuthoritativeInformation->value);
        self::assertSame(204, StatusCode::NoContent->value);
        self::assertSame(205, StatusCode::ResetContent->value);
        self::assertSame(206, StatusCode::PartialContent->value);
        self::assertSame(207, StatusCode::MultiStatus->value);
        self::assertSame(208, StatusCode::AlreadyReported->value);
        self::assertSame(226, StatusCode::ImUsed->value);
        self::assertSame(300, StatusCode::MultipleChoices->value);
        self::assertSame(301, StatusCode::MovedPermanently->value);
        self::assertSame(302, StatusCode::Found->value);
        self::assertSame(303, StatusCode::SeeOther->value);
        self::assertSame(304, StatusCode::NotModified->value);
        self::assertSame(307, StatusCode::TemporaryRedirect->value);
        self::assertSame(308, StatusCode::PermanentRedirect->value);
        self::assertSame(400, StatusCode::BadRequest->value);
        self::assertSame(401, StatusCode::Unauthorized->value);
        self::assertSame(402, StatusCode::PaymentRequired->value);
        self::assertSame(403, StatusCode::Forbidden->value);
        self::assertSame(404, StatusCode::NotFound->value);
        self::assertSame(405, StatusCode::MethodNotAllowed->value);
        self::assertSame(406, StatusCode::NotAcceptable->value);
        self::assertSame(407, StatusCode::ProxyAuthenticationRequired->value);
        self::assertSame(408, StatusCode::RequestTimeout->value);
        self::assertSame(409, StatusCode::Conflict->value);
        self::assertSame(410, StatusCode::Gone->value);
        self::assertSame(411, StatusCode::LengthRequired->value);
        self::assertSame(412, StatusCode::PreconditionFailed->value);
        self::assertSame(413, StatusCode::ContentTooLarge->value);
        self::assertSame(414, StatusCode::UriTooLong->value);
        self::assertSame(415, StatusCode::UnsupportedMediaType->value);
        self::assertSame(416, StatusCode::RangeNotSatisfiable->value);
        self::assertSame(417, StatusCode::ExpectationFailed->value);
        self::assertSame(421, StatusCode::MisdirectedRequest->value);
        self::assertSame(422, StatusCode::UnprocessableContent->value);
        self::assertSame(423, StatusCode::Locked->value);
        self::assertSame(424, StatusCode::FailedDependency->value);
        self::assertSame(425, StatusCode::TooEarly->value);
        self::assertSame(426, StatusCode::UpgradeRequired->value);
        self::assertSame(428, StatusCode::PreconditionRequired->value);
        self::assertSame(429, StatusCode::TooManyRequests->value);
        self::assertSame(431, StatusCode::RequestHeaderFieldsTooLarge->value);
        self::assertSame(451, StatusCode::UnavailableForLegalReasons->value);
        self::assertSame(500, StatusCode::InternalServerError->value);
        self::assertSame(501, StatusCode::NotImplemented->value);
        self::assertSame(502, StatusCode::BadGateway->value);
        self::assertSame(503, StatusCode::ServiceUnavailable->value);
        self::assertSame(504, StatusCode::GatewayTimeout->value);
        self::assertSame(505, StatusCode::HttpVersionNotSupported->value);
        self::assertSame(506, StatusCode::VariantAlsoNegotiates->value);
        self::assertSame(507, StatusCode::InsufficientStorage->value);
        self::assertSame(508, StatusCode::LoopDetected->value);
        self::assertSame(511, StatusCode::NetworkAuthenticationRequired->value);
    }

    public function testKnownAndUnsupportedNumericCodes(): void
    {
        self::assertSame(StatusCode::NotFound, StatusCode::tryFrom(404));
        self::assertNull(StatusCode::tryFrom(599));
    }
}
