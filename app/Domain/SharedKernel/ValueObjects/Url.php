<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Represents a URL.
 * Ensures the URL format is valid upon creation.
 */
final class Url implements Stringable
{
    private readonly string $value;
    private readonly array $parts;

    /**
     * Private constructor to enforce creation via factory methods.
     *
     * @param string $value The URL string
     */
    private function __construct(string $value)
    {
        $value = trim($value);
        
        if (empty($value)) {
            throw new InvalidArgumentException("URL cannot be empty");
        }

        // Basic URL validation
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Invalid URL format: '{$value}'");
        }

        $this->value = $value;
        $this->parts = parse_url($value) ?: [];
    }

    /**
     * Creates a Url instance from a string.
     *
     * @param string $url The URL string
     * @return self
     * @throws InvalidArgumentException If the URL format is invalid
     */
    public static function fromString(string $url): self
    {
        return new self($url);
    }

    /**
     * Attempts to create a Url from a string, returning null if invalid.
     *
     * @param string $url The URL string
     * @return self|null
     */
    public static function tryFromString(string $url): ?self
    {
        try {
            return new self($url);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Creates a Url instance with components.
     *
     * @param string $scheme The URL scheme (e.g., 'http', 'https')
     * @param string $host The hostname
     * @param string $path The path (optional)
     * @param string|null $query The query string (optional)
     * @param string|null $fragment The fragment (optional)
     * @param int|null $port The port (optional)
     * @param string|null $user The username (optional)
     * @param string|null $pass The password (optional)
     * @return self
     * @throws InvalidArgumentException If the resulting URL is invalid
     */
    public static function fromComponents(
        string $scheme,
        string $host,
        string $path = '/',
        ?string $query = null,
        ?string $fragment = null,
        ?int $port = null,
        ?string $user = null,
        ?string $pass = null
    ): self {
        $url = '';

        // Build URL with authentication if provided
        if ($user !== null) {
            $url .= $user;
            if ($pass !== null) {
                $url .= ':' . $pass;
            }
            $url .= '@';
        }

        // Add scheme and host
        $url = $scheme . '://' . $url . $host;

        // Add port if specified
        if ($port !== null) {
            $url .= ':' . $port;
        }

        // Ensure path starts with a slash
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        $url .= $path;

        // Add query string if provided
        if ($query !== null && $query !== '') {
            $url .= '?' . ltrim($query, '?');
        }

        // Add fragment if provided
        if ($fragment !== null && $fragment !== '') {
            $url .= '#' . ltrim($fragment, '#');
        }

        return new self($url);
    }

    /**
     * Gets the URL string value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the URL as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Gets the scheme (e.g., 'http', 'https').
     *
     * @return string|null
     */
    public function getScheme(): ?string
    {
        return $this->parts['scheme'] ?? null;
    }

    /**
     * Gets the host.
     *
     * @return string|null
     */
    public function getHost(): ?string
    {
        return $this->parts['host'] ?? null;
    }

    /**
     * Gets the path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->parts['path'] ?? '/';
    }

    /**
     * Gets the query string.
     *
     * @return string|null
     */
    public function getQuery(): ?string
    {
        return $this->parts['query'] ?? null;
    }

    /**
     * Gets the fragment.
     *
     * @return string|null
     */
    public function getFragment(): ?string
    {
        return $this->parts['fragment'] ?? null;
    }

    /**
     * Gets the port.
     *
     * @return int|null
     */
    public function getPort(): ?int
    {
        return isset($this->parts['port']) ? (int)$this->parts['port'] : null;
    }

    /**
     * Gets the username.
     *
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->parts['user'] ?? null;
    }

    /**
     * Gets the password.
     *
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->parts['pass'] ?? null;
    }

    /**
     * Checks if this Url is equal to another.
     *
     * @param self $other The other Url to compare with
     * @return bool True if the values are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Checks if the URL is secure (uses HTTPS).
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return strtolower($this->getScheme() ?? '') === 'https';
    }

    /**
     * Checks if the URL is HTTP or HTTPS.
     *
     * @return bool
     */
    public function isWebUrl(): bool
    {
        $scheme = strtolower($this->getScheme() ?? '');
        return $scheme === 'http' || $scheme === 'https';
    }

    /**
     * Returns a new Url with the given path.
     *
     * @param string $path The new path
     * @return self
     */
    public function withPath(string $path): self
    {
        $parts = $this->parts;
        $parts['path'] = $path;
        
        return self::fromComponents(
            $parts['scheme'] ?? 'http',
            $parts['host'] ?? '',
            $parts['path'] ?? '/',
            $parts['query'] ?? null,
            $parts['fragment'] ?? null,
            $parts['port'] ?? null,
            $parts['user'] ?? null,
            $parts['pass'] ?? null
        );
    }

    /**
     * Returns a new Url with an added query parameter.
     *
     * @param string $name The parameter name
     * @param string $value The parameter value
     * @return self
     */
    public function withQueryParameter(string $name, string $value): self
    {
        $query = $this->getQuery() ?? '';
        parse_str($query, $params);
        $params[$name] = $value;
        $newQuery = http_build_query($params);
        
        $parts = $this->parts;
        $parts['query'] = $newQuery;
        
        return self::fromComponents(
            $parts['scheme'] ?? 'http',
            $parts['host'] ?? '',
            $parts['path'] ?? '/',
            $parts['query'] ?? null,
            $parts['fragment'] ?? null,
            $parts['port'] ?? null,
            $parts['user'] ?? null,
            $parts['pass'] ?? null
        );
    }

    /**
     * Returns a new Url with the HTTPS scheme.
     *
     * @return self
     */
    public function withHttps(): self
    {
        if ($this->isSecure()) {
            return $this;
        }
        
        $parts = $this->parts;
        $parts['scheme'] = 'https';
        
        return self::fromComponents(
            $parts['scheme'],
            $parts['host'] ?? '',
            $parts['path'] ?? '/',
            $parts['query'] ?? null,
            $parts['fragment'] ?? null,
            $parts['port'] ?? null,
            $parts['user'] ?? null,
            $parts['pass'] ?? null
        );
    }
} 