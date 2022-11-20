# Contributing

## Dev environment
### Postgres

To setup the database locally, run the following command:

```bash
psql -h localhost -U postgres < tests/pgsql-schema.sql
```

*Note:* 
Depending on your setup this might import the schema into a database named `postgres` instead of `phpstan_dba`.
If that is the case, you need to update the `DATABASE_URL` in `phpstan.neon` to match the database name.
Alternatively you can create the `phpstan_dba` database beforehand and run the import command after doing so.

### Mysql

To setup the mysql database locally, run the following command:

```bash
mysql -u root -h 127.0.0.1 -p < tests/schema.sql
```

### Dependencies

```bash
composer install
```

## Development

### Running tests

```bash
composer test
```

Alternatively, you can setup your IDE to run different set of tests based on environment variables, if your IDE supports it.
By default the test composer command runs test cases for the mysql database and mysql features of phpstan dba.

### Populating the cache files

Having the cache populated is required to pass the ci pipeline so don't forget to run the record command before committing the changes.

```bash
composer record
```
