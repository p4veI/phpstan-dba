<?php

declare(strict_types=1);

namespace staabm\PHPStanDba\QueryReflection;

use PDO;
use PDOException;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Type;
use staabm\PHPStanDba\Error;
use staabm\PHPStanDba\TypeMapping\MysqlTypeMapper;
use staabm\PHPStanDba\TypeMapping\PDOTypeMapper;
use staabm\PHPStanDba\TypeMapping\TypeMapper;

use function array_key_exists;

final class PdoQueryReflector implements QueryReflector
{
    private const PSQL_INVALID_TEXT_REPRESENTATION = '22P02';
    private const PSQL_UNDEFINED_COLUMN = '42703';
    private const PSQL_UNDEFINED_TABLE = '42P01';

    private const PDO_ERROR_CODES = [
        self::PSQL_INVALID_TEXT_REPRESENTATION,
        self::PSQL_UNDEFINED_COLUMN,
        self::PSQL_UNDEFINED_TABLE,
    ];

    private const MAX_CACHE_SIZE = 50;

    /**
     * @var array<string, PDOException|list<array<'name'|'native_type'|'flags'|'len', mixed>>|null>
     */
    private array $cache = [];

    private MysqlTypeMapper $typeMapper;

    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->typeMapper = new MysqlTypeMapper();
    }

    public function validateQueryString(string $queryString): ?Error
    {
        $result = $this->simulateQuery($queryString);

        if (!$result instanceof PDOException) {
            return null;
        }

        $e = $result;
        if (\in_array($e->getCode(), self::PDO_ERROR_CODES, true)) {
            $message = $e->getMessage();

            if (
                self::PSQL_INVALID_TEXT_REPRESENTATION === $e->getCode()
                && QueryReflection::getRuntimeConfiguration()->isDebugEnabled()
            ) {
                $simulatedQuery = QuerySimulation::simulate($queryString);
                $message = $message."\n\nSimulated query: '".$simulatedQuery."' Failed.";
            }

            return new Error($message, $e->getCode());
        }

        return null;
    }

    public function getResultType(string $queryString, int $fetchType): ?Type
    {
        $result = $this->simulateQuery($queryString);

        if (!\is_array($result)) {
            return null;
        }

        $arrayBuilder = ConstantArrayTypeBuilder::createEmpty();

        $i = 0;
        foreach ($result as $val) {
            if (
                !array_key_exists('name', $val)
                || !array_key_exists('native_type', $val)
                || !array_key_exists('flags', $val)
                || !array_key_exists('len', $val)
            ) {
                throw new ShouldNotHappenException();
            }
            var_dump($val);

            if (self::FETCH_TYPE_ASSOC === $fetchType || self::FETCH_TYPE_BOTH === $fetchType) {
                $arrayBuilder->setOffsetValueType(
                    new ConstantStringType($val['name']),
                    $this->typeMapper->mapToPHPStanType($val['native_type'], $val['flags'], $val['len'])
                );
            }
            if (self::FETCH_TYPE_NUMERIC === $fetchType || self::FETCH_TYPE_BOTH === $fetchType) {
                $arrayBuilder->setOffsetValueType(
                    new ConstantIntegerType($i),
                    $this->typeMapper->mapToPHPStanType($val['native_type'], $val['flags'], $val['len'])
                );
            }
            ++$i;
        }

        return $arrayBuilder->getArray();
    }

    /** @return PDOException|list<array<'name'|'native_type'|'flags'|'len', mixed>>|null */
    private function simulateQuery(string $queryString)
    {
        if (\array_key_exists($queryString, $this->cache)) {
            return $this->cache[$queryString];
        }

        if (\count($this->cache) > self::MAX_CACHE_SIZE) {
            // make room for the next element by randomly removing a existing one
            array_shift($this->cache);
        }

        $simulatedQuery = QuerySimulation::simulate($queryString);
        if (null === $simulatedQuery) {
            return $this->cache[$queryString] = null;
        }

        try {
            $result = $this->pdo->query($simulatedQuery);
        } catch (PDOException $e) {
            return $this->cache[$queryString] = $e;
        }

        if (false === $result) {
            return $this->cache[$queryString] = null;
        }

        $this->cache[$queryString] = [];
        $columnCount = $result->columnCount();
        $columnIndex = 0;
        while ($columnIndex < $columnCount) {
            $this->cache[$queryString][$columnIndex] = $result->getColumnMeta($columnIndex);
            $columnIndex++;
        }

        return $this->cache[$queryString];
    }
}
