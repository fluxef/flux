How To preprare and change test data

1. Create a new database with soll.json as the data for the connection
2. Apply (manually) the following sql-commands to the database

mysql:
DROP TABLE `suppliers`;
CREATE TABLE `orders` (
`id` int NOT NULL,
`ordernamename` int NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `customers` DROP INDEX `firstname`;
ALTER TABLE `customers` DROP `firstname`;
ALTER TABLE `customers` ADD `lastorder` date DEFAULT NULL;

postgres:
DROP TABLE suppliers;
CREATE TABLE orders (
id serial NOT NULL,
ordernamename int NOT NULL,
CONSTRAINT suppliers_pkey PRIMARY KEY (id)
);
DROP INDEX customers_firstname;
ALTER TABLE customers DROP COLUMN firstname;
ALTER TABLE customers ADD COLUMN lastorder DATE DEFAULT NULL;


3. dump the new database structure to a json file
    mysql-ist.json 
    postgres-ist.json

4. check and adjust the expected sql-statements in test class


thats it. add more changes if neccessary.

