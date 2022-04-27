<?php

declare(strict_types=1);

namespace IKEA\Tradfri\Command;

use const FILTER_VALIDATE_IP;
use IKEA\Tradfri\Command\Coap\Keys;
use IKEA\Tradfri\Exception\RuntimeException;
use IKEA\Tradfri\Helper\Runner;
use InvalidArgumentException;
use function is_string;

/**
 * @deprecated will be removed in v1.0.0
 */
class Coaps
{
    public const PAYLOAD_START = ' -e \'{ "';
    public const PAYLOAD_OPEN  = '": [{ "';
    public const PAYLOAD_END   = ' }] }\' ';

    public const COAP_COMMAND_PUT = 'coap-client -m put -u "%s" -k "%s"';

    protected string $username;

    protected string $apiKey;

    protected string $ip;

    protected string $secret;

    /**
     * @throws InvalidArgumentException
     * @throws \IKEA\Tradfri\Exception\RuntimeException
     */
    public function __construct(
        string $gatewayAddress,
        string $secret,
        string $apiKey,
        string $username
    ) {
        $this->setIp($gatewayAddress);
        $this->secret = $secret;
        if (empty($apiKey)) {
            throw new RuntimeException('$apiKey can not be empty');
        }

        $this->setApiKey($apiKey);
        $this->setUsername($username);
    }

    /**
     * @throws \IKEA\Tradfri\Exception\RuntimeException
     */
    public function getSharedKeyFromGateway(): string
    {
        // get command to switch light
        $onCommand = $this->getPreSharedKeyCommand();

        // run command
        $result = $this->parseResult(
            (new Runner())->execWithTimeout($onCommand, 2)
        );

        // verify result
        if (is_string($result)) {
            return $result;
        }

        throw new RuntimeException('Could not get api key');
    }

    public function getPreSharedKeyCommand(): string
    {
        return sprintf(
            Post::COAP_COMMAND,
            'Client_identity',
            $this->secret
        )
        . ' -e \'{"9090":"' . $this->getUsername() . '"}\''
        . $this->_getRequestTypeCoapsUrl(
            Keys::ROOT_GATEWAY . '/' . Keys::ATTR_AUTH
        );
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setIp(string $gatewayAddress): self
    {
        if (filter_var($gatewayAddress, FILTER_VALIDATE_IP)) {
            $this->ip = $gatewayAddress;

            return $this;
        }

        throw new InvalidArgumentException('Invalid ip');
    }

    /**
     * Parse result.
     *
     * @return false|string
     */
    public function parseResult(array $result)
    {
        $parsed = false;
        foreach ($result as $part) {
            if (!empty($part)
                && false === strpos($part, 'decrypt')
                && false === strpos($part, 'v:1')) {
                $parsed = (string) $part;

                break;
            }
        }

        return $parsed;
    }

    /**
     * Get CoapsCommand GET string.
     *
     * @param int|string $requestType
     */
    public function getCoapsCommandGet($requestType): string
    {
        return sprintf(
            Get::COAP_COMMAND,
            $this->getUsername(),
            $this->getApiKey()
        ) . $this->_getRequestTypeCoapsUrl($requestType);
    }

    /**
     * Get ApiKey.
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Set ApiKey.
     *
     * @return Coaps
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * @param int|string $requestType
     */
    public function getCoapsCommandPost($requestType, string $inject): string
    {
        return sprintf(
            Post::COAP_COMMAND,
            $this->getUsername(),
            $this->getApiKey()
        )
        . $inject . $this->_getRequestTypeCoapsUrl($requestType);
    }

    public function getLightSwitchCommand(int $deviceId, bool $state): string
    {
        return $this->getCoapsCommandPut(
            Keys::ROOT_DEVICES . '/' . $deviceId,
            self::PAYLOAD_START
            . Keys::ATTR_LIGHT_CONTROL
            . self::PAYLOAD_OPEN
            . Keys::ATTR_LIGHT_STATE . '": ' . ($state ? '1' : '0')
            . self::PAYLOAD_END
        );
    }

    /**
     * @param int|string $requestType
     */
    public function getCoapsCommandPut($requestType, string $inject): string
    {
        return sprintf(
            Put::COAP_COMMAND,
            $this->getUsername(),
            $this->getApiKey()
        )
        . $inject
        . $this->_getRequestTypeCoapsUrl($requestType);
    }

    public function getGroupSwitchCommand(int $groupId, bool $state): string
    {
        return $this->getCoapsCommandPut(
            Keys::ROOT_GROUPS . '/' . $groupId,
            self::PAYLOAD_START . Keys::ATTR_LIGHT_STATE . '": ' . ($state ? '1'
                : '0') . ' }\' '
        );
    }

    public function getGroupDimmerCommand(int $groupId, int $value): string
    {
        return $this->getCoapsCommandPut(
            Keys::ROOT_GROUPS . '/' . $groupId,
            self::PAYLOAD_START . Keys::ATTR_LIGHT_DIMMER . '": ' . (int) round(
                $value * 2.55
            ) . ' }\' '
        );
    }

    public function getLightDimmerCommand(int $groupId, int $value): string
    {
        return $this->getCoapsCommandPut(
            Keys::ROOT_DEVICES . '/' . $groupId,
            self::PAYLOAD_START
            . Keys::ATTR_LIGHT_CONTROL
            . self::PAYLOAD_OPEN
            . Keys::ATTR_LIGHT_DIMMER . '": ' . (int) round($value * 2.55)
            . self::PAYLOAD_END
        );
    }

    public function getRollerBlindDarkenedStateCommand(int $deviceId, int $value): string
    {
        return $this->getCoapsCommandPut(
            Keys::ROOT_DEVICES . '/' . $deviceId,
            self::PAYLOAD_START
            . Keys::ATTR_FYRTUR_CONTROL
            . self::PAYLOAD_OPEN
            . Keys::ATTR_FYRTUR_STATE . '": ' . (float) $value
            . self::PAYLOAD_END
        );
    }

    /**
     * @param string $color (warm|normal|cold)
     *
     * @throws \IKEA\Tradfri\Exception\RuntimeException
     */
    public function getLightColorCommand(int $groupId, string $color): string
    {
        $payload = self::PAYLOAD_START
            . Keys::ATTR_LIGHT_CONTROL
            . self::PAYLOAD_OPEN
            . Keys::ATTR_LIGHT_COLOR_X
            . '": %s, "'
            . Keys::ATTR_LIGHT_COLOR_Y
            . '": %s }] }\' ';
        switch ($color) {
            case 'warm':
                $payload = sprintf($payload, '33135', '27211');

                break;
            case 'normal':
                $payload = sprintf($payload, '30140', '26909');

                break;
            case 'cold':
                $payload = sprintf($payload, '24930', '24684');

                break;
            default:
                throw new RuntimeException('unknown color');
        }

        return $this->getCoapsCommandPut(
            Keys::ROOT_DEVICES . '/' . $groupId,
            $payload
        );
    }

    /**
     * @param int|string $requestType
     */
    protected function _getRequestTypeCoapsUrl($requestType): string
    {
        return ' "coaps://' . $this->getIp() . ':5684/' . $requestType . '"';
    }
}
