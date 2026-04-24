<?php

declare(strict_types=1);

function dangerousFunc()
{
    exec('ls -la');
    system('whoami');
    shell_exec('cat /etc/passwd');
}

function weakHashFunc(string $password): string
{
    return md5($password);
}

function unsafeUnserialize(string $data): mixed
{
    return unserialize($data);
}

function sqlConcatFunc(string $userId): string
{
    $query = 'SELECT * FROM users WHERE id = '.$userId;

    return $query;
}

class SqlTableHolder
{
    public const TABLE_NAME = 'user_stats';
    public const PREFIX = 'wp_';
}

function sqlConcatWithClassConstants(): string
{
    // Only class constants interpolated — compile-time safe, must not be flagged.
    return 'INSERT INTO '.SqlTableHolder::TABLE_NAME.' (a, b, c) VALUES (:a, :b, :c)';
}

function sqlConcatWithMixedSafeAndUnsafe(string $userId): string
{
    // Class constant + user-controlled variable → must be flagged.
    return 'SELECT * FROM '.SqlTableHolder::TABLE_NAME.' WHERE id = '.$userId;
}

function sqlConcatWithGlobalConstant(): string
{
    // Global constants are compile-time safe too.
    return 'SELECT '.PHP_EOL.' * FROM users';
}

function safeFunc(int $a, int $b): int
{
    return $a + $b;
}

class DangerousClass
{
    public function runCommand(string $cmd): string
    {
        return shell_exec($cmd);
    }

    public function hashPassword(string $password): string
    {
        return sha1($password);
    }
}

class SafeClass
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
