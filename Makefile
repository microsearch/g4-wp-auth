.PHONY: build up down clear image

build:
	zip -r -9 -j g4-wp-auth.zip html/wp-content/plugins/g4-wp-auth/*

up:
	docker-compose up -d

down:
	docker-compose down

clear: down
	docker volume prune -f

image:
	docker build -t wp-xdebug .