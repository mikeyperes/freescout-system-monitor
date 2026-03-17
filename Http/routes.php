<?php

Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'system-monitor'], function () {
    Route::get('/cron-logs', 'Modules\SystemMonitor\Http\Controllers\SystemMonitorController@cronLogs')
        ->name('system_monitor.cron_logs');
    Route::get('/email-logs', 'Modules\SystemMonitor\Http\Controllers\SystemMonitorController@emailLogs')
        ->name('system_monitor.email_logs');
});
