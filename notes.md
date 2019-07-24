### Start development server

	docker-compose up -d

* Point browser to http://localhost:8000
* Make changes in the `plugin` directory. It is mapped to the `wp-content/plugins/g4-wp-auth` directory in WordPress.

### Stop development server

	docker-compose down

### Run a shell inside the container

	# to get container process id
	docker ps
	# substitute <pid> with container process id
	docker exec -it <pid> bash

### Install WordPress error logging plugin inside a container shell

	/var/www/xfer/update.sh