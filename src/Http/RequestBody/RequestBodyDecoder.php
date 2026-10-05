<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Http\RequestBody;

use ExtendsSoftware\ExaPHP\Http\HttpException;
use ExtendsSoftware\ExaPHP\Http\Request;

/**
 * Decodes request body bytes into representation data without application validation.
 */
interface RequestBodyDecoder
{
    /**
     * Decodes the supplied request body, potentially consuming it.
     *
     * @param Request $request The request to decode.
     *
     * @return mixed Decoded representation data.
     *
     * @throws HttpException When reading or decoding fails or the representation is unsupported.
     */
    public function decode(Request $request): mixed;
}
