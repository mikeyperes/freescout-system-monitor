# FreeScout System Monitor

Dashboard panels for FreeScout system health monitoring.

## Features

- Cron status monitoring
- Email send/receive activity reports
- Recently viewed conversations
- Telegram notification status
- Incremental error email alerts with locked, atomic delivery checkpoints and retry after mail failure
- Daily-log rollover, log replacement/truncation, and partial-line handling

## Requirements

- FreeScout v1.8+
- PHP 8.0+

## Installation

1. Upload the `SystemMonitor` folder to `Modules/`
2. Go to Manage → Modules and activate it

## Error alert monitor

`bin/hexa-freescout-monitor` is the source for the hourly root cron command at
`/usr/local/sbin/hexa-freescout-monitor`. That operational path is a symlink
into this live module. Keep the existing cron schedule, sender, and recipient.

The monitor reads new complete entries from daily Laravel logs. It updates
`/var/lib/hexa-freescout-monitor/state.json` only after mail accepts the alert,
so unchanged logs do not trigger duplicate emails and failures remain pending.
The checkpoint records byte offsets and file identity; logs are never modified.
An exclusive lock prevents overlapping monitor executions.

- `--dry-run`: count pending errors without email or checkpoint changes.
- `--initialize-current`: explicitly mark existing entries as already reported
  when migrating the old monitor; sends nothing.
- `--log-dir` and `--state-dir`: isolate disposable verification fixtures.

Mail acceptance means queued locally, not proof of recipient delivery. A crash
between mail acceptance and checkpoint persistence may repeat that alert on retry.
