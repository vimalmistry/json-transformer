.PHONY: install test lint clean

install:
	composer install

test:
	./vendor/bin/phpunit --testdox

clean:
	rm -rf vendor composer.lock
