<?php

namespace MediaWiki\Extension\PageTriage\Test;

use MediaWiki\Tests\Structure\AbstractSchemaTestBase;

/**
 * Validates the abstract schema and schema changes, and checks that the
 * generated SQL files are up to date.
 *
 * @coversNothing
 * @group PageTriage
 */
class PageTriageSchemaTest extends AbstractSchemaTestBase {

	protected static function getSchemasDirectory(): string {
		return __DIR__ . '/../../../sql';
	}

	protected static function getSchemaChangesDirectory(): string {
		return __DIR__ . '/../../../sql/abstractSchemaChanges';
	}

	protected static function getSchemaSQLDirs(): array {
		return [
			'mysql' => __DIR__ . '/../../../sql/mysql',
			'sqlite' => __DIR__ . '/../../../sql/sqlite',
			'postgres' => __DIR__ . '/../../../sql/postgres',
		];
	}
}
