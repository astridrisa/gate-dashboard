<?php

return [
    // Kata sandi yang harus diisi di form import (karena import menimpa seluruh data).
    // Atur di file .env:  GATE_IMPORT_PASSWORD=isi-kata-sandi-kamu
    'import_password' => env('GATE_IMPORT_PASSWORD'),
    'clear_password' => env('GATE_CLEAR_PASSWORD'),
];
