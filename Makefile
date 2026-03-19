PHPUNIT = vendor/bin/phpunit

.PHONY: test
test:
	$(PHPUNIT) --testsuite Unit

.PHONY: coverage
coverage:
	XDEBUG_MODE=coverage $(PHPUNIT) --testsuite Unit --coverage-html coverage/html

.PHONY: coverage-check
coverage-check:
	XDEBUG_MODE=coverage $(PHPUNIT) --testsuite Unit \
		--coverage-clover coverage/clover.xml && \
		php scripts/coverage-check.php coverage/clover.xml 80

.PHONY: analyse
analyse:
	vendor/bin/phpstan analyse

.PHONY: cs-check
cs-check:
	vendor/bin/php-cs-fixer fix --dry-run --diff

.PHONY: cs-fix
cs-fix:
	vendor/bin/php-cs-fixer fix

.PHONY: check
check: cs-check analyse test