## Start development environment

	docker-compose up -d

* Point browser to http://localhost:8000
* Make changes in plugin directory.

## Stop development environment

	docker-compose down

## SSH into development server

	docker ps   # to get container process id
	docker exec -it <process id> bash

### Install error logging plugin (in SSH session)

	/var/www/xfer/update.sh