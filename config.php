<?php
// config.php
return (object) [
    'db' => (object) [
        'host' => '127.0.0.1',
        'dbname' => 'taxshield', //codeopdq_taxshield
        'user' => 'root', // codeopdq_taxshield
        'pass' => '', //SIPDNF=uTo2Mx=(}
        'charset' => 'utf8mb4'
    ],
    // Replace with a long random secret in production
    'jwt_secret' => 'REPLACE_WITH_STRONG_RANDOM_SECRET_ChangeMe!',
    'jwt_issuer' => 'tax-shield',
    'low_balance_warning' => 2000.00
];
