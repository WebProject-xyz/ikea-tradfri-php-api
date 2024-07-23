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

namespace IKEA\Tradfri\Group;

use IKEA\Tradfri\Collection\LightBulbs;
use IKEA\Tradfri\Device\BrightnessStateInterface;
use IKEA\Tradfri\Device\SwitchableInterface;

final class LightGroup extends DeviceGroup implements BrightnessStateInterface, SwitchableInterface
{
    use \IKEA\Tradfri\Traits\ProvidesBrightness;

    public function isOn(): bool
    {
        if (false === $this->getLights()->isEmpty()) {
            return $this->getLights()->getActive()->count() > 0;
        }

        return false;
    }

    public function isOff(): bool
    {
        if (false === $this->getLights()->isEmpty()) {
            return 0 === $this->getLights()->getActive()->count();
        }

        return false;
    }

    public function getLights(): LightBulbs
    {
        return $this->getDevices()->getLightBulbs();
    }

    public function switchOn(): bool
    {
        if ($this->_service->on($this)) {
            $this->setState(true);

            return true;
        }

        return false;
    }

    public function off(): self
    {
        if ($this->_service->off($this)) {
            $this->setState(false);
        }

        return $this;
    }

    /**
     * @phpstan-param int<0, 100> $levelInPercent
     */
    public function dim(int $levelInPercent): bool
    {
        if ($this->_service->dim($this, $levelInPercent)) {
            $this->setBrightnessLevel((float) $levelInPercent);

            return true;
        }

        return false;
    }

    public function switchOff(): bool
    {
        if ($this->_service->off($this)) {
            $this->setState(false);

            return true;
        }

        return false;
    }
}
