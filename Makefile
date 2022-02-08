PROJECT_NAME=tigriseuphrates

.PHONY: build

default: build

push: build
	lftp sftp://${BGA_SFTP_LOGIN}@1.studio.boardgamearena.com/ -e "mirror --reverse --parallel=10 ${PROJECT_NAME}/ ${PROJECT_NAME}/; exit" 

test:
	./phpab -o autoload.php ${PROJECT_NAME}
	./phpunit --bootstrap autoload.php ${PROJECT_NAME}
