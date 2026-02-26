<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
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
        $cacheDir = $basePath.'/bootstrap-cache';

        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $paths = [
            'APP_CONFIG_CACHE' => $cacheDir.'/config.php',
            'APP_PACKAGES_CACHE' => $cacheDir.'/packages.php',
            'APP_SERVICES_CACHE' => $cacheDir.'/services.php',
            'APP_ROUTES_CACHE' => $cacheDir.'/routes.php',
            'APP_EVENTS_CACHE' => $cacheDir.'/events.php',
        ];

        foreach ($paths as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Prepare isolated storage directories required by Laravel services.
     */
    private function configureTestingStoragePaths(string $basePath): string
    {
        $storagePath = $basePath.'/storage';

        $directories = [
            $storagePath.'/app',
            $storagePath.'/framework/cache/data',
            $storagePath.'/framework/sessions',
            $storagePath.'/framework/testing/disks',
            $storagePath.'/framework/views',
            $storagePath.'/logs',
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        $viewCompiledPath = $storagePath.'/framework/views';
        $_ENV['VIEW_COMPILED_PATH'] = $viewCompiledPath;
        $_SERVER['VIEW_COMPILED_PATH'] = $viewCompiledPath;
        putenv('VIEW_COMPILED_PATH='.$viewCompiledPath);

        return $storagePath;
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

        $testBasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'social-network-testing'.DIRECTORY_SEPARATOR.$token;

        if (! is_dir($testBasePath)) {
            mkdir($testBasePath, 0777, true);
        }

        $this->configureTestingCachePaths($testBasePath);
        $storagePath = $this->configureTestingStoragePaths($testBasePath);

        $app = require __DIR__.'/../bootstrap/app.php';
        $app->useStoragePath($storagePath);

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
