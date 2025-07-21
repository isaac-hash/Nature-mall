<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait LoggableController
{
    /**
     * Get the log channel name for the current controller.
     *
     * @return string
     */
    protected function getLogChannelName(): string
    {
        // Get the full class name of the controller (e.g., App\Http\Controllers\AuthController)
        $fullClassName = get_class($this);

        // Extract just the controller name (e.g., AuthController)
        $controllerName = class_basename($fullClassName);

        // Remove "Controller" suffix and convert to lowercase for channel name
        // e.g., AuthController -> auth_controller
        return strtolower(str_replace('Controller', '', $controllerName)) . '_controller';
    }

    /**
     * Log an informational message to the controller's dedicated channel.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::channel($this->getLogChannelName())->info($message, $context);
    }

    /**
     * Log a warning message to the controller's dedicated channel.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::channel($this->getLogChannelName())->warning($message, $context);
    }

    /**
     * Log an error message to the controller's dedicated channel.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::channel($this->getLogChannelName())->error($message, $context);
    }

    /**
     * Log a debug message to the controller's dedicated channel.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        Log::channel($this->getLogChannelName())->debug($message, $context);
    }

    // You can add more log levels (critical, alert, notice) as needed
}
