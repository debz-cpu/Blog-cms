<?php

declare(strict_types=1);

namespace App\Migration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\MetadataStorageError;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\Metadata\ExecutedMigrationsList;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorage;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\Version\Comparator;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\ExecutionResult;
use Doctrine\Migrations\Version\Version;

use function array_change_key_case;
use function class_exists;
use function count;
use function floatval;
use function method_exists;
use function round;
use function sprintf;
use function strtolower;
use function uasort;

use const CASE_LOWER;

final class LenientMetadataStorage implements MetadataStorage
{
    private readonly Connection $connection;

    private readonly TableMetadataStorageConfiguration $configuration;

    private readonly Comparator $comparator;

    private readonly TableMetadataStorage $inner;

    public function __construct(DependencyFactory $dependencyFactory)
    {
        $configuration = $dependencyFactory->getConfiguration()->getMetadataStorageConfiguration();

        if (! $configuration instanceof TableMetadataStorageConfiguration) {
            throw new \RuntimeException('Expected table metadata storage configuration.');
        }

        $this->connection = $dependencyFactory->getConnection();
        $this->configuration = $configuration;
        $this->comparator = $dependencyFactory->getVersionComparator();
        $this->inner = new TableMetadataStorage(
            $this->connection,
            $this->comparator,
            $configuration,
            $dependencyFactory->getMigrationRepository(),
        );
    }

    public function ensureInitialized(): void
    {
        try {
            $this->inner->ensureInitialized();
        } catch (MetadataStorageError $exception) {
            if (! $this->isIgnorableMetadataDiff()) {
                throw $exception;
            }
        }
    }

    public function getExecutedMigrations(): ExecutedMigrationsList
    {
        try {
            return $this->inner->getExecutedMigrations();
        } catch (MetadataStorageError $exception) {
            if (! $this->isIgnorableMetadataDiff()) {
                throw $exception;
            }
        }

        $rows = $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s', $this->configuration->getTableName()));
        $migrations = [];

        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $version = new Version($row[strtolower($this->configuration->getVersionColumnName())]);

            $executedAt = $row[strtolower($this->configuration->getExecutedAtColumnName())] ?? '';
            $executedAt = $executedAt !== ''
                ? DateTimeImmutable::createFromFormat($this->connection->getDatabasePlatform()->getDateTimeFormatString(), $executedAt)
                : null;

            $executionTime = isset($row[strtolower($this->configuration->getExecutionTimeColumnName())])
                ? floatval($row[strtolower($this->configuration->getExecutionTimeColumnName())] / 1000)
                : null;

            $migrations[(string) $version] = new ExecutedMigration(
                $version,
                $executedAt instanceof DateTimeImmutable ? $executedAt : null,
                $executionTime,
            );
        }

        uasort(
            $migrations,
            fn (ExecutedMigration $left, ExecutedMigration $right): int => $this->comparator->compare($left->getVersion(), $right->getVersion()),
        );

        return new ExecutedMigrationsList($migrations);
    }

    public function complete(ExecutionResult $result): void
    {
        try {
            $this->inner->complete($result);

            return;
        } catch (MetadataStorageError $exception) {
            if (! $this->isIgnorableMetadataDiff()) {
                throw $exception;
            }
        }

        if ($result->getDirection() === Direction::DOWN) {
            $this->connection->delete($this->configuration->getTableName(), [
                $this->configuration->getVersionColumnName() => (string) $result->getVersion(),
            ]);

            return;
        }

        $this->connection->insert($this->configuration->getTableName(), [
            $this->configuration->getVersionColumnName() => (string) $result->getVersion(),
            $this->configuration->getExecutedAtColumnName() => ($result->getExecutedAt() ?? new DateTimeImmutable())->format('Y-m-d H:i:s'),
            $this->configuration->getExecutionTimeColumnName() => $result->getTime() === null ? null : (int) round($result->getTime() * 1000),
        ]);
    }

    public function reset(): void
    {
        try {
            $this->inner->reset();

            return;
        } catch (MetadataStorageError $exception) {
            if (! $this->isIgnorableMetadataDiff()) {
                throw $exception;
            }
        }

        $this->connection->executeStatement(sprintf('DELETE FROM %s WHERE 1 = 1', $this->configuration->getTableName()));
    }

    /** @return iterable<Query> */
    public function getSql(ExecutionResult $result): iterable
    {
        return $this->inner->getSql($result);
    }

    private function isIgnorableMetadataDiff(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (! $schemaManager->tablesExist([$this->configuration->getTableName()])) {
            return false;
        }

        $expectedTable = new Table($this->configuration->getTableName());
        $expectedTable->addColumn(
            $this->configuration->getVersionColumnName(),
            'string',
            ['notnull' => true, 'length' => $this->configuration->getVersionColumnLength()],
        );
        $expectedTable->addColumn($this->configuration->getExecutedAtColumnName(), 'datetime', ['notnull' => false]);
        $expectedTable->addColumn($this->configuration->getExecutionTimeColumnName(), 'integer', ['notnull' => false]);
        $expectedTable->setPrimaryKey([$this->configuration->getVersionColumnName()]);

        $comparator = class_exists(ComparatorConfig::class)
            ? $schemaManager->createComparator((new ComparatorConfig())->withReportModifiedIndexes(false))
            : $schemaManager->createComparator();

        if (method_exists($schemaManager, 'introspectTableByUnquotedName')) {
            $currentTable = $schemaManager->introspectTableByUnquotedName($this->configuration->getTableName());
        } else {
            /** @phpstan-ignore method.deprecated */
            $currentTable = $schemaManager->introspectTable($this->configuration->getTableName());
        }

        $diff = $comparator->compareTables($currentTable, $expectedTable);

        if (
            $diff->getAddedColumns() !== []
            || $diff->getDroppedColumns() !== []
            || $diff->getAddedIndexes() !== []
            || $diff->getModifiedIndexes() !== []
            || $diff->getDroppedIndexes() !== []
            || $diff->getAddedForeignKeys() !== []
            || $diff->getModifiedForeignKeys() !== []
            || $diff->getDroppedForeignKeys() !== []
        ) {
            return false;
        }

        $changedColumns = $diff->getChangedColumns();

        if (count($changedColumns) !== 1 || ! isset($changedColumns[$this->configuration->getExecutedAtColumnName()])) {
            return false;
        }

        $columnDiff = $changedColumns[$this->configuration->getExecutedAtColumnName()];

        return ! $columnDiff->hasTypeChanged()
            && ! $columnDiff->hasNotNullChanged()
            && ! $columnDiff->hasLengthChanged()
            && ! $columnDiff->hasPrecisionChanged()
            && ! $columnDiff->hasScaleChanged()
            && ! $columnDiff->hasFixedChanged()
            && ! $columnDiff->hasUnsignedChanged()
            && ! $columnDiff->hasAutoIncrementChanged()
            && ! $columnDiff->hasCommentChanged()
            && ! $columnDiff->hasNameChanged();
    }
}