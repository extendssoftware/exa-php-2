<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\Representation;

use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetails;
use ExtendsSoftware\ExaPHP\Http\ErrorHandling\ProblemDetails\ProblemDetailsResponseFactory;
use ExtendsSoftware\ExaPHP\Http\Message\Headers;
use ExtendsSoftware\ExaPHP\Http\Message\Request;
use ExtendsSoftware\ExaPHP\Http\Message\Response;
use ExtendsSoftware\ExaPHP\Http\Message\StatusCode;
use ExtendsSoftware\ExaPHP\Http\Representation\Exception\InvalidResponseFactoryException;
use Throwable;

use function array_key_exists;
use function count;
use function explode;
use function implode;
use function is_string;
use function preg_match;
use function str_contains;
use function strlen;
use function strtolower;
use function trim;

/**
 * Selects an available response factory using Accept, with JSON as the default media type.
 *
 * Registrations use concrete media types without parameters. Ranges with media parameters cannot match them.
 * Invalid ranges are ignored. The most specific matching range supplies quality, including explicit zero exclusions.
 * Candidates rank by quality, then range specificity, then the configured default, then registration order.
 */
final readonly class ContentNegotiatingResponseFactory
{
    /**
     * Factories indexed by normalized media type, with the default first.
     *
     * @var non-empty-array<string, ResponseFactory>
     */
    private array $factories;

    /**
     * Creates a negotiator over explicitly available representations.
     *
     * @param array<string, ResponseFactory> $factories Concrete parameterless media types mapped to their encoders.
     * @param string $defaultMediaType The registered media type preferred when client preferences are equal or absent.
     *
     * @throws InvalidResponseFactoryException When registrations are invalid, duplicated, or lack the default.
     */
    public function __construct(array $factories, string $defaultMediaType = 'application/json')
    {
        $normalized = [];
        foreach ($factories as $type => $factory) {
            if (!is_string($type) || !$factory instanceof ResponseFactory || str_contains($type, '*')
                || preg_match('~\A[!#$%&\x27+.^_`|\~0-9A-Za-z-]+/[!#$%&\x27+.^_`|\~0-9A-Za-z-]+\z~', $type) !== 1) {
                throw new InvalidResponseFactoryException(
                    'Response factories require concrete parameterless media types.',
                );
            }
            $type = strtolower($type);
            if (array_key_exists($type, $normalized)) {
                throw new InvalidResponseFactoryException('Response factory media types must be unique ignoring case.');
            }
            $normalized[$type] = $factory;
        }
        $defaultMediaType = strtolower($defaultMediaType);
        if (!array_key_exists($defaultMediaType, $normalized)) {
            throw new InvalidResponseFactoryException(
                'The default response media type must have a registered factory.',
            );
        }
        $this->factories = [$defaultMediaType => $normalized[$defaultMediaType]] + $normalized;
    }

    /**
     * Negotiates data or returns a Problem Details 406 without invoking registered representation factories.
     *
     * Missing Accept accepts all representations; an empty field accepts none. Vary retains existing entries and
     * includes Accept unless already covered. The response uses the request protocol. Factory failures are not retried.
     *
     * @param Request $request The request containing client format preferences.
     * @param mixed $data The representation data passed unchanged to the selected factory.
     * @param StatusCode $statusCode The requested successful negotiation status.
     * @param Headers $headers Additional response headers, used only when negotiation succeeds.
     *
     * @return Response The encoded response, or a Problem Details 406 with Vary: Accept.
     *
     * @throws Throwable When the selected factory fails, propagated unchanged.
     */
    public function create(
        Request $request,
        mixed $data,
        StatusCode $statusCode = StatusCode::Ok,
        Headers $headers = new Headers(),
    ): Response {
        $accept = $request->headers->has('Accept') ? implode(',', $request->headers->get('Accept')) : '*/*';
        $ranges = $this->ranges($accept);
        $selected = null;
        $best = [0.0, -1];
        foreach ($this->factories as $type => $factory) {
            [$major] = explode('/', $type);
            $quality = 0.0;
            $specificity = -1;
            foreach ($ranges as [$range, $weight]) {
                $rank = match ($range) {
                    $type => 2,
                    $major . '/*' => 1,
                    '*/*' => 0,
                    default => -1,
                };
                if ($rank > $specificity || ($rank === $specificity && $weight > $quality)) {
                    if ($rank < 0) {
                        continue;
                    }
                    $specificity = $rank;
                    $quality = $weight;
                }
            }
            if ($quality > 0 && [$quality, $specificity] > $best) {
                $best = [$quality, $specificity];
                $selected = $factory;
            }
        }
        $response = $selected === null
            ? new ProblemDetailsResponseFactory()->create(
                new ProblemDetails(StatusCode::NotAcceptable, 'Not Acceptable'),
            )
            : $selected->create($data, $statusCode, $headers);
        $headers = $response->headers;
        foreach ($headers->get('Vary') as $line) {
            foreach (explode(',', $line) as $field) {
                if (trim($field) === '*' || strtolower(trim($field)) === 'accept') {
                    return $response->withProtocolVersion($request->protocolVersion);
                }
            }
        }

        return $response
            ->withHeaders($headers->withAdded('Vary', 'Accept'))
            ->withProtocolVersion($request->protocolVersion);
    }

    /**
     * Parses valid parameterless media ranges and their quality values.
     *
     * @param string $accept The combined Accept field.
     *
     * @return list<array{string, float}> Recognized ranges and weights.
     */
    private function ranges(string $accept): array
    {
        $ranges = [];
        foreach ($this->split($accept, ',') as $entry) {
            $parts = $this->split($entry, ';');
            $range = strtolower(trim($parts[0]));
            $token = '[!#$%&\x27+.^_`|\~0-9A-Za-z-]+';
            if (preg_match('~\A(?:\*/\*|' . $token . '/(?:\*|' . $token . '))\z~', $range) !== 1) {
                continue;
            }
            $quality = 1.0;
            if (count($parts) > 1) {
                if (count($parts) !== 2
                    || preg_match('/\Aq=(0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)\z/i', trim($parts[1]), $match) !== 1) {
                    continue;
                }
                $quality = (float) $match[1];
            }
            $ranges[] = [$range, $quality];
        }

        return $ranges;
    }

    /**
     * Splits field members without splitting quoted parameter values.
     *
     * @param string $value The field value.
     * @param string $delimiter The separator character.
     *
     * @return non-empty-list<string> Field members, including empty ones.
     */
    private function split(string $value, string $delimiter): array
    {
        $parts = [''];
        $index = 0;
        $quoted = false;
        $escaped = false;
        for ($offset = 0, $length = strlen($value) ; $offset < $length ; ++$offset) {
            $character = $value[$offset];
            if ($character === $delimiter && !$quoted) {
                $parts[++$index] = '';
                continue;
            }
            $parts[$index] .= $character;
            if ($escaped) {
                $escaped = false;
            } elseif ($quoted && $character === '\\') {
                $escaped = true;
            } elseif ($character === '"') {
                $quoted = !$quoted;
            }
        }

        return $parts;
    }
}
