<?php

function dangerousFunc() {
    exec('ls -la');
    system('whoami');
    shell_exec('cat /etc/passwd');
}

function weakHashFunc(string $password): string {
    return md5($password);
}

function unsafeUnserialize(string $data): mixed {
    return unserialize($data);
}

function sqlConcatFunc(string $userId): string {
    $query = "SELECT * FROM users WHERE id = " . $userId;
    return $query;
}

function safeFunc(int $a, int $b): int {
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
