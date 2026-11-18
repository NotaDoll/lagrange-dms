# Scripts

## Scheduler

Laravel runs scheduled jobs (such as `sms:sync-textbee`, which checks every 5 minutes whether TextBee
delivered each guardian reminder) only while its scheduler is running. On a Laragon machine nothing
starts it automatically.

The dashboard also refreshes reminder statuses itself whenever it is opened, so the scheduler is a
backup that keeps the SMS log current even when nobody has the dashboard open.

**Start it by hand:** double-click `scripts\run-scheduler.bat` and leave the window open.

**Start it automatically when you log in to Windows** (run once, in Command Prompt):

```bat
schtasks /Create /TN "La Grange DMS scheduler" /SC ONLOGON /TR "\"C:\laragon\www\lagrange-dms\scripts\run-scheduler.bat\""
```

To remove it later:

```bat
schtasks /Delete /TN "La Grange DMS scheduler" /F
```

The script needs PHP on the PATH, which Laragon sets up. Laragon's MySQL must be running too.
