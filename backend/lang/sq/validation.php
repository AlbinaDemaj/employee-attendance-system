<?php

/*
 | Mesazhet e validimit në shqip. Çelësat që mungojnë këtu bien automatikisht
 | te lang/en/validation.php (APP_FALLBACK_LOCALE=en).
 */

return [
    'accepted' => 'Fusha :attribute duhet pranuar.',
    'after' => 'Fusha :attribute duhet të jetë një datë pas :date.',
    'after_or_equal' => 'Fusha :attribute duhet të jetë një datë pas ose e barabartë me :date.',
    'before' => 'Fusha :attribute duhet të jetë një datë para :date.',
    'before_or_equal' => 'Fusha :attribute duhet të jetë një datë para ose e barabartë me :date.',
    'boolean' => 'Fusha :attribute duhet të jetë e vërtetë ose e rreme.',
    'confirmed' => 'Konfirmimi i :attribute nuk përputhet.',
    'date' => 'Fusha :attribute nuk është një datë e vlefshme.',
    'date_format' => 'Fusha :attribute nuk përputhet me formatin :format.',
    'different' => 'Fushat :attribute dhe :other duhet të jenë të ndryshme.',
    'email' => 'Fusha :attribute duhet të jetë një adresë email e vlefshme.',
    'exists' => 'Vlera e zgjedhur për :attribute nuk ekziston.',
    'file' => 'Fusha :attribute duhet të jetë një skedar.',
    'image' => 'Fusha :attribute duhet të jetë një imazh.',
    'in' => 'Vlera e zgjedhur për :attribute nuk është e vlefshme.',
    'integer' => 'Fusha :attribute duhet të jetë numër i plotë.',
    'max' => [
        'array' => 'Fusha :attribute nuk mund të ketë më shumë se :max elemente.',
        'file' => 'Skedari :attribute nuk mund të jetë më i madh se :max kilobajt.',
        'numeric' => 'Fusha :attribute nuk mund të jetë më e madhe se :max.',
        'string' => 'Fusha :attribute nuk mund të jetë më e gjatë se :max karaktere.',
    ],
    'mimes' => 'Fusha :attribute duhet të jetë një skedar i tipit: :values.',
    'min' => [
        'array' => 'Fusha :attribute duhet të ketë të paktën :min elemente.',
        'file' => 'Skedari :attribute duhet të jetë të paktën :min kilobajt.',
        'numeric' => 'Fusha :attribute duhet të jetë të paktën :min.',
        'string' => 'Fusha :attribute duhet të ketë të paktën :min karaktere.',
    ],
    'numeric' => 'Fusha :attribute duhet të jetë numër.',
    'required' => 'Fusha :attribute është e detyrueshme.',
    'required_if' => 'Fusha :attribute është e detyrueshme kur :other është :value.',
    'string' => 'Fusha :attribute duhet të jetë tekst.',
    'unique' => 'Kjo vlerë për :attribute është e zënë tashmë.',
    'uploaded' => 'Ngarkimi i :attribute dështoi.',

    /*
     | Emrat e fushave siç i njeh përdoruesi, që mesazhet të lexohen natyrshëm.
     */
    'attributes' => [
        'name' => 'emri',
        'email' => 'email-i',
        'password' => 'fjalëkalimi',
        'role' => 'roli',
        'department' => 'departamenti',
        'expected_start_time' => 'ora e pritur e fillimit',
        'manager_id' => 'menaxheri',
        'phone' => 'telefoni',
        'is_active' => 'statusi aktiv',
        'type' => 'lloji',
        'start_date' => 'data e fillimit',
        'end_date' => 'data e mbarimit',
        'description' => 'përshkrimi',
        'certificate' => 'certifikata',
        'decision' => 'vendimi',
        'manager_note' => 'shënimi i menaxherit',
        'reason_category' => 'arsyeja',
        'reason_note' => 'shënimi',
        'work_date' => 'data e punës',
        'status' => 'statusi',
        'month' => 'muaji',
        'date' => 'data',
    ],
];
