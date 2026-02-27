<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Join path segments with current platform separator.
     */
    private function joinPath(string ...$segments): string
    {
        $normalized = [];

        foreach ($segments as $index => $segment) {
            $value = trim($segment);
            if ($value === '') {
                continue;
            }

            $trimmed = $index === 0
                ? rtrim($value, "\\/\t\n\r\0\x0B")
                : trim($value, "\\/\t\n\r\0\x0B");

            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $normalized);
    }

    /**
     * Ensure directory exists or throw meaningful exception.
     */
    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! @mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new \RuntimeException("Unable to create testing directory: {$path}");
        }
    }

    /**
     * Apply deterministic environment values before the app is bootstrapped.
     */
    private function configureTestingEnvironment(): void
    {
        $env = [
            'APP_ENV' => 'testing',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_DRIVER' => 'log',
            'CACHE_DRIVER' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'TELESCOPE_ENABLED' => 'false',
        ];

        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
    }

    /**
     * Build a stable per-process token so Storage::fake uses isolated folders
     * and does not collide across root/www-data runs in Docker.
     */
    private function resolveTestingToken(): string
    {
        $uid = function_exists('getmyuid') ? (string) getmyuid() : 'na';
        $pid = function_exists('getmypid') ? (string) getmypid() : 'na';

        return 'u'.$uid.'_p'.$pid;
    }

    /**
     * Point Laravel cache artifacts to an isolated temp location, so cached
     * config from non-testing runs never contaminates test bootstrap.
     */
    private function configureTestingCachePaths(string $basePath): void
    {
        $cacheDir = $this->joinPath($basePath, 'bootstrap-cache');
        $this->ensureDirectory($cacheDir);
        $projectRoot = dirname(__DIR__);

        $normalizedProjectRoot = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rtrim($projectRoot, "\\/"));
        $normalizedCacheDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rtrim($cacheDir, "\\/"));
        $cacheDirForEnv = $cacheDir;

        $projectPrefix = $normalizedProjectRoot.DIRECTORY_SEPARATOR;
        if (str_starts_with(strtolower($normalizedCacheDir), strtolower($projectPrefix))) {
            $relative = ltrim(substr($normalizedCacheDir, strlen($projectPrefix)), "\\/");
            $cacheDirForEnv = $relative !== '' ? $relative : $normalizedCacheDir;
        }

        $cacheToken = function_exists('random_bytes')
            ? bin2hex(random_bytes(6))
            : uniqid('t', true);

        $paths = [
            'APP_CONFIG_CACHE' => $this->joinPath($cacheDirForEnv, "config-{$cacheToken}.php"),
            'APP_PACKAGES_CACHE' => $this->joinPath('bootstrap', 'cache', 'packages.php'),
            'APP_SERVICES_CACHE' => $this->joinPath('bootstrap', 'cache', 'services.php'),
            'APP_ROUTES_CACHE' => $this->joinPath($cacheDirForEnv, "routes-{$cacheToken}.php"),
            'APP_EVENTS_CACHE' => $this->joinPath($cacheDirForEnv, "events-{$cacheToken}.php"),
        ];

        foreach ($paths as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
    }

    /**
     * Prepare isolated storage directories required by Laravel services.
     */
    private function configureTestingStoragePaths(string $basePath): string
    {
        $storagePath = $this->joinPath($basePath, 'storage');

        $directories = [
            $this->joinPath($storagePath, 'app'),
            $this->joinPath($storagePath, 'framework', 'cache', 'data'),
            $this->joinPath($storagePath, 'framework', 'sessions'),
            $this->joinPath($storagePath, 'framework', 'testing', 'disks'),
            $this->joinPath($storagePath, 'framework', 'views'),
            $this->joinPath($storagePath, 'logs'),
        ];

        foreach ($directories as $directory) {
            $this->ensureDirectory($directory);
        }

        $viewCompiledPath = $this->joinPath($storagePath, 'framework', 'views');
        $_ENV['VIEW_COMPILED_PATH'] = $viewCompiledPath;
        $_SERVER['VIEW_COMPILED_PATH'] = $viewCompiledPath;
        putenv('VIEW_COMPILED_PATH='.$viewCompiledPath);

        return $storagePath;
    }

    /**
     * Resolve writable base directory for test runtime artifacts.
     */
    private function resolveTestingBasePath(string $token): string
    {
        $projectRoot = dirname(__DIR__);

        $candidate = $this->joinPath($projectRoot, 'storage', 'framework', 'testing-runtime', $token);

        $this->ensureDirectory($candidate);
        if (is_writable($candidate)) {
            return $candidate;
        }

        throw new \RuntimeException('No writable directory available for Laravel test runtime artifacts.');
    }

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $this->configureTestingEnvironment();

        $token = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? $this->resolveTestingToken();
        $_SERVER['TEST_TOKEN'] = (string) $token;
        $_ENV['TEST_TOKEN'] = (string) $token;

        $testBasePath = $this->resolveTestingBasePath((string) $token);

        $this->configureTestingCachePaths($testBasePath);
        $storagePath = $this->configureTestingStoragePaths($testBasePath);

        $app = require __DIR__.'/../bootstrap/app.php';
        $app->useStoragePath($storagePath);

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
