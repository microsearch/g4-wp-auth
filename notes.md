### Start development server

	docker-compose up -d

* Point browser to http://localhost:8000
* Make changes in the `plugin` directory. It is mapped to the `wp-content/plugins/g4-wp-auth` directory in WordPress.

### Stop development server

	docker-compose down

### SSH into development server

	# to get container process id
	docker ps
	# substitute <pid> with container process id
	docker exec -it <pid> bash

### Install error logging plugin (in SSH session)

	/var/www/xfer/update.sh