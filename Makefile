PROJECT_NAME=tigriseuphrates

.PHONY: build

default: test

push: build
	lftp sftp://${BGA_SFTP_LOGIN}@1.studio.boardgamearena.com/ -e "mirror --reverse --parallel=10 ${PROJECT_NAME}/ ${PROJECT_NAME}/; exit" 

test: setup
	./phpab -o autoload.php ${PROJECT_NAME}
	./phpunit --bootstrap autoload.php ${PROJECT_NAME}
	@date

setup:
ifeq (,$(wildcard ./phpunit))
	wget -O phpunit https://phar.phpunit.de/phpunit-9.phar
	chmod +x phpunit
endif
ifeq (,$(wildcard ./phpab))
	wget -O phpab https://github.com/theseer/Autoload/releases/download/1.27.1/phpab-1.27.1.phar
	chmod +x phpab
endif
