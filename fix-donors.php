<?php
$donors = [
    [
        "name"         => "FreddyG",
        "total"        => 5.00,
        "count"        => 1,
        "currency"     => "USD",
        "last"         => "2026-05-23 00:00:00",
        "last_message" => "Sigamos metiendo mano",
        "email_hash"   => "",
    ]
];

$file = __DIR__ . '/donors.json';
file_put_contents($file, json_encode($donors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'OK — donors.json actualizado.';
