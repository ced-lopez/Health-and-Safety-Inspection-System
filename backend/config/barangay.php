<?php

return [
    'name' => env('BARANGAY_NAME', 'Barangay 178'),
    'city' => env('BARANGAY_CITY', 'North Caloocan City'),
    'office' => env('BARANGAY_OFFICE', 'Office of the Barangay Chairman'),
    'logo_path' => env('BARANGAY_LOGO_PATH'),
    'captain' => [
        'name' => env('BARANGAY_CAPTAIN_NAME', 'Hon. Juan Dela Cruz'),
        'title' => env('BARANGAY_CAPTAIN_TITLE', 'Punong Barangay'),
    ],
    'health_officer' => [
        'name' => env('BARANGAY_HEALTH_OFFICER_NAME', 'Dra. Maria Santos'),
        'title' => env('BARANGAY_HEALTH_OFFICER_TITLE', 'Barangay Health Officer'),
    ],
];
