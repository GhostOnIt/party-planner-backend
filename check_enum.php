<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check what the actual column type is
$columns = DB::select("SELECT column_name, data_type, udt_name FROM information_schema.columns WHERE table_name = 'collaborators' AND column_name = 'role'");
echo "Role column info:\n";
foreach($columns as $column) {
    echo "- column: " . $column->column_name . "\n";
    echo "- data_type: " . $column->data_type . "\n";
    echo "- udt_name: " . $column->udt_name . "\n";
}

// Check for check constraints
$constraints = DB::select("
    SELECT conname, conrelid::regclass, pg_get_constraintdef(oid)
    FROM pg_constraint
    WHERE conrelid = 'collaborators'::regclass
    AND contype = 'c'
");
echo "\nCheck constraints:\n";
foreach($constraints as $constraint) {
    echo "- " . $constraint->conname . ": " . $constraint->pg_get_constraintdef . "\n";
}

// Check enum types
$types = DB::select("SELECT typname FROM pg_type WHERE typname LIKE '%role%'");
echo "\nRole-related enum types:\n";
foreach($types as $type) {
    echo "- " . $type->typname . "\n";
}
