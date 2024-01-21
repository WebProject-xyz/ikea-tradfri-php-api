<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Benjamin Fahl
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/WebProject-xyz/ikea-tradfri-php
 */

namespace IKEA\Tradfri\Command\Coap\Light;

use IKEA\Tradfri\Command\Put;
use IKEA\Tradfri\Command\Request;
use IKEA\Tradfri\Dto\CoapGatewayRequestPayloadDto;

final class LightChangeLightTemperatureCommand extends Put
{
    public function __construct(
        \IKEA\Tradfri\Dto\CoapGatewayAuthConfigDto $authConfig,
        private readonly int $deviceId,
        /**
         * @phpstan-param value-of<\IKEA\Tradfri\Dto\CoapGatewayRequestPayloadDto::COLORS>|string $color
         */
        private readonly string $color,
    ) {
        parent::__construct($authConfig);
    }

    public function __toString(): string
    {
        return $this->requestCommand(
            Request::RootDevices->withTargetId($this->deviceId),
            CoapGatewayRequestPayloadDto::formatToLightTemperature($this->color),
        );
    }
}
