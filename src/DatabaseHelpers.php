<?php declare(strict_types=1);

namespace Blockchain;

use PDO;
use PDOStatement;
use RuntimeException;

class DatabaseHelpers
{
    public const ALPHA_NUMERIC = 0;
    public const TEXT = 1;
    public const INT = 2;

    protected function __construct()
    {
    }

    public static function filterBindAll(PDOStatement $stmt, array $fields): PDOStatement
    {
        foreach ($fields as $field) {
            $stmt = self::filterBind(
                $stmt,
                $field['name'],
                $field['value'],
                $field['type'],
                $field['max_length']
            );
        }

        return $stmt;
    }

    public static function filterBind(
        PDOStatement $stmt,
        string $fieldName,
        string|int $value,
        int $pdoType,
        int $maxLength = 0
    ): PDOStatement {
        // network id
        switch ($pdoType) {
            case self::ALPHA_NUMERIC:
                $value = preg_replace("/[^a-zA-Z0-9]/", '', $value);
                $stmt->bindParam(param: ':' . $fieldName, var: $value, maxLength: $maxLength);
                break;

            case self::TEXT:
                $value = trim($value);
                if ($maxLength === 0) {
                    $stmt->bindParam(param: ':' . $fieldName, var: $value);
                } else {
                    $stmt->bindParam(param: ':' . $fieldName, var: $value, maxLength: $maxLength);
                }
                break;

            case self::INT:
                $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE);
                $stmt->bindParam(param: ':' . $fieldName, var: $value, type: PDO::PARAM_INT);
                break;

            default:
                throw new RuntimeException('Invalid type provided to DatabaseHelpers: ' . $pdoType);
        }

        return $stmt;
    }
}
