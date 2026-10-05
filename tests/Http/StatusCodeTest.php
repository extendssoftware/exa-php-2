<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Http;

use ExtendsSoftware\ExaPHP\Http\StatusCode;
use PHPUnit\Framework\TestCase;

final class StatusCodeTest extends TestCase
{
    public function testNamedCodesPreserveTheirNumericValues(): void
    {
        $this->assertSame(100, StatusCode::Continue->value);
        $this->assertSame(101, StatusCode::SwitchingProtocols->value);
        $this->assertSame(102, StatusCode::Processing->value);
        $this->assertSame(103, StatusCode::EarlyHints->value);
        $this->assertSame(200, StatusCode::Ok->value);
        $this->assertSame(201, StatusCode::Created->value);
        $this->assertSame(202, StatusCode::Accepted->value);
        $this->assertSame(203, StatusCode::NonAuthoritativeInformation->value);
        $this->assertSame(204, StatusCode::NoContent->value);
        $this->assertSame(205, StatusCode::ResetContent->value);
        $this->assertSame(206, StatusCode::PartialContent->value);
        $this->assertSame(207, StatusCode::MultiStatus->value);
        $this->assertSame(208, StatusCode::AlreadyReported->value);
        $this->assertSame(226, StatusCode::ImUsed->value);
        $this->assertSame(300, StatusCode::MultipleChoices->value);
        $this->assertSame(301, StatusCode::MovedPermanently->value);
        $this->assertSame(302, StatusCode::Found->value);
        $this->assertSame(303, StatusCode::SeeOther->value);
        $this->assertSame(304, StatusCode::NotModified->value);
        $this->assertSame(307, StatusCode::TemporaryRedirect->value);
        $this->assertSame(308, StatusCode::PermanentRedirect->value);
        $this->assertSame(400, StatusCode::BadRequest->value);
        $this->assertSame(401, StatusCode::Unauthorized->value);
        $this->assertSame(402, StatusCode::PaymentRequired->value);
        $this->assertSame(403, StatusCode::Forbidden->value);
        $this->assertSame(404, StatusCode::NotFound->value);
        $this->assertSame(405, StatusCode::MethodNotAllowed->value);
        $this->assertSame(406, StatusCode::NotAcceptable->value);
        $this->assertSame(407, StatusCode::ProxyAuthenticationRequired->value);
        $this->assertSame(408, StatusCode::RequestTimeout->value);
        $this->assertSame(409, StatusCode::Conflict->value);
        $this->assertSame(410, StatusCode::Gone->value);
        $this->assertSame(411, StatusCode::LengthRequired->value);
        $this->assertSame(412, StatusCode::PreconditionFailed->value);
        $this->assertSame(413, StatusCode::ContentTooLarge->value);
        $this->assertSame(414, StatusCode::UriTooLong->value);
        $this->assertSame(415, StatusCode::UnsupportedMediaType->value);
        $this->assertSame(416, StatusCode::RangeNotSatisfiable->value);
        $this->assertSame(417, StatusCode::ExpectationFailed->value);
        $this->assertSame(421, StatusCode::MisdirectedRequest->value);
        $this->assertSame(422, StatusCode::UnprocessableContent->value);
        $this->assertSame(423, StatusCode::Locked->value);
        $this->assertSame(424, StatusCode::FailedDependency->value);
        $this->assertSame(425, StatusCode::TooEarly->value);
        $this->assertSame(426, StatusCode::UpgradeRequired->value);
        $this->assertSame(428, StatusCode::PreconditionRequired->value);
        $this->assertSame(429, StatusCode::TooManyRequests->value);
        $this->assertSame(431, StatusCode::RequestHeaderFieldsTooLarge->value);
        $this->assertSame(451, StatusCode::UnavailableForLegalReasons->value);
        $this->assertSame(500, StatusCode::InternalServerError->value);
        $this->assertSame(501, StatusCode::NotImplemented->value);
        $this->assertSame(502, StatusCode::BadGateway->value);
        $this->assertSame(503, StatusCode::ServiceUnavailable->value);
        $this->assertSame(504, StatusCode::GatewayTimeout->value);
        $this->assertSame(505, StatusCode::HttpVersionNotSupported->value);
        $this->assertSame(506, StatusCode::VariantAlsoNegotiates->value);
        $this->assertSame(507, StatusCode::InsufficientStorage->value);
        $this->assertSame(508, StatusCode::LoopDetected->value);
        $this->assertSame(511, StatusCode::NetworkAuthenticationRequired->value);
    }

    public function testKnownAndUnsupportedNumericCodes(): void
    {
        $this->assertSame(StatusCode::NotFound, StatusCode::tryFrom(404));
        $this->assertNull(StatusCode::tryFrom(599));
    }
}
