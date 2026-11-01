<?php

use Illuminate\Support\Facades\Schedule;

// De madrugada ninguém está usando o app. withoutOverlapping: um envio lento não encavala
// com o do dia seguinte.
Schedule::command('backup:criar')->dailyAt('03:00')->withoutOverlapping();
