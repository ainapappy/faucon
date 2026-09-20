<?php

namespace App\Services\Workflow\Log;

/**
 * The ONE place holding the French wording of the execution journal (A5).
 *
 * Every journal message is frozen at write time (no client-side business
 * wording); durations use the French formatting mirrored from the front's
 * formatDurationMs (« 860 ms », « 4,1 s », « 2 min 05 s »).
 */
final class ExecutionLogMessages
{
    /**
     * The line written when an attempt opens.
     */
    public static function started(string $triggerLabel): string
    {
        return __('Exécution démarrée (déclencheur : :label).', ['label' => $triggerLabel]);
    }

    /**
     * The line written when a node succeeds.
     */
    public static function nodeCompleted(string $name, int $durationMs): string
    {
        return __('Node « :name » terminé en :duration.', [
            'name' => $name,
            'duration' => self::formatDuration($durationMs),
        ]);
    }

    /**
     * The line written when a node fails.
     */
    public static function nodeFailed(string $message): string
    {
        return __('Le node a échoué : :message', ['message' => $message]);
    }

    /**
     * The line written when a retry is scheduled.
     */
    public static function retryScheduled(int $nextAttempt, int $maxTries, int $delaySeconds): string
    {
        return __('Retry programmé (tentative :next/:max dans :delay s).', [
            'next' => $nextAttempt,
            'max' => $maxTries,
            'delay' => $delaySeconds,
        ]);
    }

    /**
     * The line written on a successful run.
     */
    public static function completed(): string
    {
        return __('Exécution terminée avec succès.');
    }

    /**
     * The line written on a failed run — the first node error, when any.
     */
    public static function failed(?string $message): string
    {
        if ($message === null || $message === '') {
            return __('Exécution échouée.');
        }

        return __('Exécution échouée : :message', ['message' => $message]);
    }

    /**
     * The line written on a cancelled run.
     */
    public static function cancelled(): string
    {
        return __('Exécution annulée.');
    }

    /**
     * French duration format, mirroring the front's formatDurationMs:
     * below one second in ms, below one minute with a decimal comma,
     * minutes with zero-padded seconds.
     */
    private static function formatDuration(int $ms): string
    {
        if ($ms < 1000) {
            return $ms.' ms';
        }

        if ($ms < 60000) {
            return str_replace('.', ',', number_format($ms / 1000, 1)).' s';
        }

        $minutes = intdiv($ms, 60000);
        $seconds = intdiv($ms % 60000, 1000);

        return $minutes.' min '.str_pad((string) $seconds, 2, '0', STR_PAD_LEFT).' s';
    }
}
