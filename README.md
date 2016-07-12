[![Build Status](https://travis-ci.org/JSbenchOrg/server.svg?branch=master)](https://travis-ci.org/JSbenchOrg/server)

## Usage

API reference [RAML](https://anypoint.mulesoft.com/apiplatform/freelancer-15/#/portals/organizations/6ea45a71-6412-4c0d-9d7c-de707cd17ee6/apis/67694/versions/70453/pages/112244)

----

## Development

### Install

#### Application

- copy `config.dist.php` to `config.php` and replace with real credentials.
- setup the database schema by importing all migrations found in `extra/migrations/*.sql`

#### Tests
- copy `phpunit.dist.xml` to `phpunit.xml`, replace the BASE_URL value
