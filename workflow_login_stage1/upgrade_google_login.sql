SET @google_sub_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'google_sub'
);
SET @google_sub_column_sql = IF(
    @google_sub_column_exists = 0,
    'ALTER TABLE users ADD COLUMN google_sub VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE google_sub_column_stmt FROM @google_sub_column_sql;
EXECUTE google_sub_column_stmt;
DEALLOCATE PREPARE google_sub_column_stmt;

SET @google_sub_unique_exists = (
    SELECT COUNT(DISTINCT s.index_name)
    FROM information_schema.statistics AS s
    WHERE s.table_schema = DATABASE()
      AND s.table_name = 'users'
      AND s.column_name = 'google_sub'
      AND s.non_unique = 0
      AND (
          SELECT COUNT(*)
          FROM information_schema.statistics AS one_column
          WHERE one_column.table_schema = s.table_schema
            AND one_column.table_name = s.table_name
            AND one_column.index_name = s.index_name
      ) = 1
);
SET @google_sub_index_sql = IF(
    @google_sub_unique_exists = 0,
    'CREATE UNIQUE INDEX uq_users_google_sub ON users (google_sub)',
    'SELECT 1'
);
PREPARE google_sub_index_stmt FROM @google_sub_index_sql;
EXECUTE google_sub_index_stmt;
DEALLOCATE PREPARE google_sub_index_stmt;
