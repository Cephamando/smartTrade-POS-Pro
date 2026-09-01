<?php

namespace App\Models;

use App\Core\Database;
use PDO;

abstract class Model
{
    protected static function db(): PDO
    {
        return Database::connection();
    }
}
