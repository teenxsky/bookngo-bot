-include ./.env.local
DOCKER_COMPOSE = docker-compose -f ./.docker/docker-compose.yaml --env-file .env.local
XDEBUG = $(DOCKER_COMPOSE) exec -u root backend php .docker/php/xdebug.php


#--------------- DOCKER COMPOSE COMMANDS ---------------#

# Build the Docker images for the development environment
build:
	$(DOCKER_COMPOSE) build

# Starts the services in detached mode
up:
	$(DOCKER_COMPOSE) up -d

# Starts the services and logs are displayed.
up-logs:
	$(DOCKER_COMPOSE) up

# Stops the services
down:
	$(DOCKER_COMPOSE) down

# Stops and removes all containers, networks, volumes, and images
clean:
	$(DOCKER_COMPOSE) down --rmi all

# Stops and removes all volumes
clean-volumes:
	$(DOCKER_COMPOSE) down -v

# Restart all containers
restart:
	$(DOCKER_COMPOSE) restart


#--------------- DATABASE COMMANDS ---------------#

# Creates a new migration file
make-migrations:
	$(DOCKER_COMPOSE) exec backend bash -c "bin/console make:migration"

# Creates a database for backend
create-db:
	$(DOCKER_COMPOSE) exec backend bash -c 'bin/console doctrine:database:create'

# Creates diff migration
diff-migration:
	$(DOCKER_COMPOSE) exec backend bash -c "bin/console doctrine:migrations:diff"

# Make dumb and after this executes Doctrine migrations
migrate-db:
	make create-dump && $(DOCKER_COMPOSE) exec backend bash -c "bin/console doctrine:migrations:migrate"

# Creates a new database for tests
create-test-db:
	$(DOCKER_COMPOSE) exec backend bash -c 'bin/console doctrine:database:create --env=test'

# Executes Doctrine migrations for test database
migrate-test-db:
	$(DOCKER_COMPOSE) exec backend bash -c 'bin/console doctrine:migrations:migrate --env=test'

# Creates database backup (dump.sql)
create-dump:
	$(DOCKER_COMPOSE) exec database bash -c 'PGPASSWORD=$(POSTGRES_PASSWORD) pg_dump --username $(POSTGRES_USER) $(POSTGRES_DB) > /docker-entrypoint-initdb.d/dump__$$(date +%H:%M:%S__%d-%m-%Y).sql'

# Load storage data into database
load-storage-data:
	$(DOCKER_COMPOSE) exec backend bash -c "bin/console app:load-storage-data"


#--------------- SYMFONY COMMANDS ---------------#

# Makes a new symfony entity
create-entity:
	$(DOCKER_COMPOSE) exec backend bash -c "bin/console make:entity"

# Makes a new symfony controller
create-controller:
	$(DOCKER_COMPOSE) exec backend bash -c "bin/console make:controller"

# Add a new symfony dependency
add-dependency:
	@bash -c 'read -p "Enter the dependency name: " dep_name && \
	echo "Installing dependency: $$dep_name" && \
	$(DOCKER_COMPOSE) exec backend bash -c "composer require $$dep_name"'

# Open backend container shell
shell-backend:
	$(DOCKER_COMPOSE) exec backend bash

# Sets webhook for telegram
set-webhook:
	$(DOCKER_COMPOSE) exec backend bash -c "bin/console app:set-webhook"


#--------------- LINTING COMMANDS ---------------#

# Run all php linters
run-lint:
	@echo "\033[1;34mPHPCS Linter\033[0m"
	@echo "\033[1;33m------------------------\033[0m"
	make phpcs
	@echo "\033[1;34mPHPCS-Fixer Linter\033[0m"
	@echo "\033[1;33m------------------------\033[0m"
	make phpcs-fixer
	@echo "\033[1;34mPSALM Linter\033[0m"
	@echo "\033[1;33m------------------------\033[0m"
	make psalm

# Run all fixers
run-fix:
	@echo "\033[1;34mPHPCS Fixer\033[0m"
	@echo "\033[1;33m------------------------\033[0m"
	make phpcs-fix
	@echo "\033[1;34mPHPCS-Fixer Fixer\033[0m"
	@echo "\033[1;33m------------------------\033[0m"
	make phpcs-fixer-fix
	@echo "\033[1;34mPSALM Fixer\033[0m"
	@echo "\033[1;33m------------------------\033[0m"
	make psalm-fix

# Run php linting by CodeSniffer
phpcs:
	$(DOCKER_COMPOSE) exec backend bash -c "vendor/bin/phpcs --standard='phpcs.xml'"

# Run php fixing errors by CodeSniffer 
phpcs-fix:
	$(DOCKER_COMPOSE) exec backend bash -c "vendor/bin/phpcbf --standard='phpcs.xml'"

# Run php linting by CsFixer
phpcs-fixer:
	$(DOCKER_COMPOSE) exec backend bash -c "vendor/bin/php-cs-fixer fix --verbose --allow-risky=yes --dry-run"

# Run php fixing errors by CsFixer
phpcs-fixer-fix:
	$(DOCKER_COMPOSE) exec backend bash -c "vendor/bin/php-cs-fixer fix --verbose --allow-risky=yes"

# Run php linting by Psalm
psalm:
	$(DOCKER_COMPOSE) exec backend bash -c "vendor/bin/psalm --diff"

# Run php fixing errors by Psalm
psalm-fix:
	$(DOCKER_COMPOSE) exec backend bash -c "vendor/bin/psalm --alter --issues=MissingOverrideAttribute,MissingReturnType,PossiblyUnusedMethod,ClassMustBeFinal --dry-run"


#--------------- TESTING COMMANDS ---------------#

# Run all tests in the backend container
run-tests:
	$(DOCKER_COMPOSE) exec backend bash -c "vendor/bin/phpunit"

# Run service tests in the backend container 
run-tests-service:
	$(DOCKER_COMPOSE) exec backend bash -c "vendor/bin/phpunit tests/Service"

# Run controller tests in the backend container
run-tests-controller:
	$(DOCKER_COMPOSE) exec backend bash -c "vendor/bin/phpunit tests/Controller"


#--------------- DEBUG COMMANDS ---------------#

# Show Xdebug status
xdebug-status:
	$(DOCKER_COMPOSE) exec backend bash -c "bin/console app:xdebug status"

# Enable Xdebug
xdebug-enable:
	$(DOCKER_COMPOSE) exec backend bash -c "bin/console app:xdebug enable"
	$(DOCKER_COMPOSE) restart backend

# Disable Xdebug
xdebug-disable:
	$(DOCKER_COMPOSE) exec backend bash -c "bin/console app:xdebug disable"
	$(DOCKER_COMPOSE) restart backend


#--------------- HELP COMMAND ---------------#

# List of all commands
help:
	@echo "\033[1;34mMakefile commands:\033[0m"
	@echo "  \033[1;33m------------------------\033[0m"
	@echo "  \033[1;32mDocker Compose commands:\033[0m"
	@echo "  \033[1;36mbuild\033[0m                - Build the Docker images for the development environment"
	@echo "  \033[1;36mup\033[0m                   - Start the services in detached mode"
	@echo "  \033[1;36mup-logs\033[0m              - Start the services and display logs"
	@echo "  \033[1;36mdown\033[0m                 - Stop the services"
	@echo "  \033[1;36mclean\033[0m                - Stop and remove all containers, networks, volumes, and images"
	@echo "  \033[1;36mclean-volumes\033[0m        - Stop and remove all volumes"
	@echo "  \033[1;36mrestart\033[0m              - Restart all containers"
	@echo "  \033[1;33m------------------------\033[0m"
	@echo "  \033[1;32mDatabase commands:\033[0m"
	@echo "  \033[1;36mmake-migrations\033[0m      - Create a new migration file in the backend container"
	@echo "  \033[1;36mcreate-db\033[0m            - Create a database for backend"
	@echo "  \033[1;36mdiff-migration\033[0m       - Create diff migration"
	@echo "  \033[1;36mmigrate-db\033[0m           - Execute Doctrine migrations for the database"
	@echo "  \033[1;36mcreate-test-db\033[0m       - Create a new database for tests"
	@echo "  \033[1;36mmigrate-test-db\033[0m      - Execute Doctrine migrations for test database"
	@echo "  \033[1;36mcreate-dump\033[0m          - Create database backup (dump.sql)"
	@echo "  \033[1;36mload-storage-data\033[0m    - Load storage data into database"
	@echo "  \033[1;33m------------------------\033[0m"
	@echo "  \033[1;32mSymfony commands:\033[0m"
	@echo "  \033[1;36mcreate-entity\033[0m        - Make a new entity in the backend container"
	@echo "  \033[1;36mcreate-controller\033[0m    - Make a new symfony controller"
	@echo "  \033[1;36madd-dependency\033[0m       - Add a new symfony dependency"
	@echo "  \033[1;36mshell-backend\033[0m        - Open backend container shell"
	@echo "  \033[1;36mset-webhook\033[0m          - Set webhook for telegram"
	@echo "  \033[1;33m------------------------\033[0m"
	@echo "  \033[1;32mLinting commands:\033[0m"
	@echo "  \033[1;36mrun-lint\033[0m             - Run all php linters"
	@echo "  \033[1;36mrun-fix\033[0m              - Run all fixers"
	@echo "  \033[1;36mphpcs\033[0m                - Run php linting by CodeSniffer"
	@echo "  \033[1;36mphpcs-fix\033[0m            - Run php fixing errors by CodeSniffer"
	@echo "  \033[1;36mphpcs-fixer\033[0m          - Run php linting by CsFixer"
	@echo "  \033[1;36mphpcs-fixer-fix\033[0m      - Run php fixing errors by CsFixer"
	@echo "  \033[1;36mpsalm\033[0m                - Run php linting by Psalm"
	@echo "  \033[1;36mpsalm-fix\033[0m            - Run php fixing errors by Psalm"
	@echo "  \033[1;33m------------------------\033[0m"
	@echo "  \033[1;32mTesting commands:\033[0m"
	@echo "  \033[1;36mrun-tests\033[0m            - Run all tests in the backend container"
	@echo "  \033[1;36mrun-tests-service\033[0m    - Run service tests in the backend container"
	@echo "  \033[1;36mrun-tests-controller\033[0m - Run controller tests in the backend container"
	@echo "  \033[1;33m------------------------\033[0m"
	@echo "  \033[1;32mDebug commands:\033[0m"
	@echo "  \033[1;36mxdebug-status\033[0m        - Show Xdebug status"
	@echo "  \033[1;36mxdebug-enable\033[0m        - Enable Xdebug"
	@echo "  \033[1;36mxdebug-disable\033[0m       - Disable Xdebug"

.PHONY: build up up-logs down clean clean-volumes make-migrations create-db migrate-db create-test-db migrate-test-db create-dump create-entity create-controller add-dependency shell-backend run-tests run-tests-service run-tests-controller xdebug-status xdebug-enable xdebug-disable help run-lint phpcs phpcs-fix phpcs-fixer phpcs-fixer-fix run-fix psalm load-storage-data set-webhook diff-migration
