<?php

return [
    /*
     | A check-in this many minutes (or more) after the expected start time
     | counts as "late". 0 = every minute past the expected time counts.
     */
    'late_grace_minutes' => (int) env('ATTENDANCE_LATE_GRACE_MINUTES', 0),

    /*
     | How many minutes after the expected start time before the manager gets
     | a "has not checked in" notification. Computed live when a dashboard is
     | opened - there is no background scheduler.
     */
    'manager_alert_after_minutes' => (int) env('ATTENDANCE_MANAGER_ALERT_AFTER_MINUTES', 15),

    /*
     | When true, a sick-leave request that arrives with a medical certificate
     | is approved automatically instead of waiting for a manager.
     | Off by default so the manager Approve/Reject flow is exercised.
     */
    'auto_approve_sick_with_certificate' => (bool) env('ATTENDANCE_AUTO_APPROVE_SICK_WITH_CERTIFICATE', false),

    /*
     | Days of the week that count as working days (1 = Monday ... 7 = Sunday).
     | Non-working days are never reported as absent.
     */
    'working_days' => [1, 2, 3, 4, 5],

    'default_expected_start_time' => env('ATTENDANCE_DEFAULT_START', '08:00'),

    'max_certificate_kb' => 8192,
];
