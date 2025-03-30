<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use JsonSerializable;

/**
 * Represents the platforms supported by a game.
 */
final class PlatformSupport implements JsonSerializable
{
    /**
     * @param bool $windows Whether Windows is supported
     * @param bool $linux Whether Linux is supported
     * @param bool $mac Whether Mac is supported
     * @param bool $android Whether Android is supported
     * @param bool $web Whether web browser is supported
     */
    public function __construct(
        private readonly bool $windows = false,
        private readonly bool $linux = false,
        private readonly bool $mac = false,
        private readonly bool $android = false,
        private readonly bool $web = false
    ) {
    }

    /**
     * Creates a PlatformSupport instance from an array.
     *
     * @param array $data Associative array with platform values
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            windows: $data['windows'] ?? false,
            linux: $data['linux'] ?? false,
            mac: $data['mac'] ?? false,
            android: $data['android'] ?? false,
            web: $data['web'] ?? false
        );
    }

    /**
     * Creates an empty PlatformSupport with no platforms enabled.
     *
     * @return self
     */
    public static function none(): self
    {
        return new self(false, false, false, false, false);
    }

    /**
     * Creates a PlatformSupport with all desktop platforms enabled.
     *
     * @return self
     */
    public static function allDesktop(): self
    {
        return new self(true, true, true, false, false);
    }

    /**
     * Creates a PlatformSupport with all platforms enabled.
     *
     * @return self
     */
    public static function all(): self
    {
        return new self(true, true, true, true, true);
    }

    /**
     * Gets whether Windows is supported.
     *
     * @return bool
     */
    public function isWindows(): bool
    {
        return $this->windows;
    }

    /**
     * Gets whether Linux is supported.
     *
     * @return bool
     */
    public function isLinux(): bool
    {
        return $this->linux;
    }

    /**
     * Gets whether Mac is supported.
     *
     * @return bool
     */
    public function isMac(): bool
    {
        return $this->mac;
    }

    /**
     * Gets whether Android is supported.
     *
     * @return bool
     */
    public function isAndroid(): bool
    {
        return $this->android;
    }

    /**
     * Gets whether web browser is supported.
     *
     * @return bool
     */
    public function isWeb(): bool
    {
        return $this->web;
    }

    /**
     * Checks if any platform is supported.
     *
     * @return bool
     */
    public function hasAnySupport(): bool
    {
        return $this->windows || $this->linux || $this->mac || $this->android || $this->web;
    }

    /**
     * Checks if desktop platforms are supported.
     *
     * @return bool
     */
    public function hasDesktopSupport(): bool
    {
        return $this->windows || $this->linux || $this->mac;
    }

    /**
     * Checks if mobile platforms are supported.
     *
     * @return bool
     */
    public function hasMobileSupport(): bool
    {
        return $this->android;
    }

    /**
     * Gets an array of supported platform names.
     *
     * @return array
     */
    public function getSupportedPlatforms(): array
    {
        $platforms = [];
        
        if ($this->windows) $platforms[] = 'windows';
        if ($this->linux) $platforms[] = 'linux';
        if ($this->mac) $platforms[] = 'mac';
        if ($this->android) $platforms[] = 'android';
        if ($this->web) $platforms[] = 'web';
        
        return $platforms;
    }

    /**
     * Converts the PlatformSupport to an array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'windows' => $this->windows,
            'linux' => $this->linux,
            'mac' => $this->mac,
            'android' => $this->android,
            'web' => $this->web,
        ];
    }

    /**
     * Checks if this PlatformSupport is equal to another.
     *
     * @param self $other The other PlatformSupport to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->windows === $other->windows
            && $this->linux === $other->linux
            && $this->mac === $other->mac
            && $this->android === $other->android
            && $this->web === $other->web;
    }

    /**
     * Provides data for JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns a new PlatformSupport with Windows support.
     *
     * @param bool $enabled Whether the platform should be enabled
     * @return self
     */
    public function withWindows(bool $enabled = true): self
    {
        return new self(
            $enabled, 
            $this->linux, 
            $this->mac, 
            $this->android, 
            $this->web
        );
    }

    /**
     * Returns a new PlatformSupport with Linux support.
     *
     * @param bool $enabled Whether the platform should be enabled
     * @return self
     */
    public function withLinux(bool $enabled = true): self
    {
        return new self(
            $this->windows, 
            $enabled, 
            $this->mac, 
            $this->android, 
            $this->web
        );
    }

    /**
     * Returns a new PlatformSupport with Mac support.
     *
     * @param bool $enabled Whether the platform should be enabled
     * @return self
     */
    public function withMac(bool $enabled = true): self
    {
        return new self(
            $this->windows, 
            $this->linux, 
            $enabled, 
            $this->android, 
            $this->web
        );
    }

    /**
     * Returns a new PlatformSupport with Android support.
     *
     * @param bool $enabled Whether the platform should be enabled
     * @return self
     */
    public function withAndroid(bool $enabled = true): self
    {
        return new self(
            $this->windows, 
            $this->linux, 
            $this->mac, 
            $enabled, 
            $this->web
        );
    }

    /**
     * Returns a new PlatformSupport with web support.
     *
     * @param bool $enabled Whether the platform should be enabled
     * @return self
     */
    public function withWeb(bool $enabled = true): self
    {
        return new self(
            $this->windows, 
            $this->linux, 
            $this->mac, 
            $this->android, 
            $enabled
        );
    }
} 