<?php

use Illuminate\Support\Facades\Schedule;

// Collecter les emails toutes les 5 minutes
Schedule::command('triage:collect')->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/triage-collect.log'));

// Re-vérifier les emails en attente toutes les 15 minutes
// (propage les corrections récentes aux emails similaires)
Schedule::command('triage:recheck')->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/triage-recheck.log'));

// Maintenance hebdomadaire
Schedule::command('triage:prune')->weekly()
    ->appendOutputTo(storage_path('logs/triage-prune.log'));
