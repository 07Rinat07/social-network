<?php

namespace App\Exceptions;

use App\Services\SiteErrorLogService;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e): void {
            if (!$this->shouldReport($e)) {
                return;
            }

            try {
                $request = app()->bound('request') ? request() : null;
                app(SiteErrorLogService::class)->logThrowable($e, $request);
            } catch (Throwable $_loggingFailure) {
                // Keep exception reporting non-blocking even if the custom text log cannot be written.
            }
        });
    }
}
