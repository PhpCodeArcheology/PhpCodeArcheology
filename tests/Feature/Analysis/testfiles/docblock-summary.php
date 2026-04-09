<?php

declare(strict_types=1);

/**
 * A service that handles user authentication.
 *
 * This is the main entry point for login flows.
 */
class ClassWithDocBlock
{
    /**
     * Authenticate the given user.
     *
     * Validates credentials against the database.
     */
    public function authenticate(string $username, string $password): bool
    {
        return true;
    }

    public function validateToken(string $token): bool
    {
        return true;
    }

    /**
     * Contact admin@example.com for access issues.
     */
    public function getHelpText(): string
    {
        return '';
    }

    /**
     * Check if the session is still valid.
     */
    private function isSessionValid(): bool
    {
        return true;
    }

    /**
     * Create a new instance with default settings.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config = [])
    {
    }
}

class ClassWithoutDocBlock
{
    public function doSomething(): void
    {
    }
}

/**
 * A standalone helper function.
 *
 * Does something useful.
 */
function helperWithDocBlock(int $value): int
{
    return $value;
}

function helperWithoutDocBlock(): void
{
}
