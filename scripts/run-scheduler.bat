@echo off
rem Keeps Laravel's scheduler running for La Grange DMS (e.g. refreshing TextBee SMS statuses every 5 minutes).
rem Double-click to start it; leave the window open (minimized is fine). Close the window to stop it.
rem To start it automatically, see "Scheduler" in scripts\README.md.

cd /d "%~dp0.."
title La Grange DMS scheduler
echo Running the La Grange DMS scheduler. Keep this window open; close it to stop.
php artisan schedule:work
