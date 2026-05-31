<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Middag\Framework\Logging\LoggerFactory;
use Middag\Framework\Logging\NullActorResolver;
use Middag\Framework\Logging\NullOriginResolver;
use Middag\Framework\Shared\Enum\DebugMode;
use Middag\Framework\Shared\Util\Debug;
use Middag\Framework\Shared\Util\Environment;
use Middag\Framework\Shared\Util\Typing;

/**
 * Showcase for the framework's Shared\Util helpers (Environment / Typing / Debug).
 * For request debugging through the HTTP kernel, use `bin/console debug:request`.
 */
$projectRoot = dirname(__DIR__);

echo "== Shared\\Util\\Environment ==\n";
echo 'environment  : ' . Environment::getEnvironment() . "\n";
echo 'isDevelopment: ' . (Environment::isDevelopment() ? 'true' : 'false') . "\n";
echo 'isProduction : ' . (Environment::isProduction() ? 'true' : 'false') . "\n";

echo "\n== Shared\\Util\\Typing ==\n";
echo "toInt('42')     = " . var_export(Typing::toInt('42'), true) . "\n";
echo "toBool('yes')   = " . var_export(Typing::toBool('yes'), true) . "\n";
echo "toBool('off')   = " . var_export(Typing::toBool('off'), true) . "\n";
echo "toFloat('3.14') = " . var_export(Typing::toFloat('3.14'), true) . "\n";

echo "\n== Shared\\Util\\Debug (FULL) ==\n";
$logger = (new LoggerFactory(
    $projectRoot . '/var/log',
    new NullActorResolver(),
    new NullOriginResolver(),
))->forChannel('demo', 'debug');

Debug::setRuntime($logger, static fn (): int => DebugMode::FULL->value);
Debug::trace('bin/debug.php ran', DebugMode::NORMAL);

echo "Debug::trace emitted to var/log/demo/debug/*.log\n";
