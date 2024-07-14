<?php

declare(strict_types=1);

namespace PhpCodeArch;

use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;

class ComposerEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => 'onPostInstall',
        ];
    }

    public static function onPostInstall(Event $event): void
    {
        self::copyConfigFile();
    }

    public static function onPostUpdate(Event $event): void
    {
        self::copyConfigFile();
    }

    private static function copyConfigFile(): void
    {
        $composerJsonPath = getenv('COMPOSER');
        $baseDir = dirname($composerJsonPath);

        $source = __DIR__ . '/../php-codearch-config-sample.yaml';
        $dest = $baseDir . '/php-codearch-config.yaml';

        if (copy($source, $dest)) {
            echo "Config file created: $dest" . PHP_EOL;
        } else {
            echo "Error creating config file: $dest" . PHP_EOL;
        }
    }
}
