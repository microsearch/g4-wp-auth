### Start development server

    make up

- Point browser to http://localhost:8000
- Make changes in the `html/wp-content/plugins/g4-wp-auth` directory.

### Stop development server

    make down

Use `make clear` to delete wordpress database as well.

### Run a shell inside the container

    # to get container process id
    docker ps
    # substitute <pid> with container process id
    docker exec -it <pid> bash

### Install WordPress error logging plugin inside a container shell

    /var/www/xfer/update.sh

NB: Don't forget to run `make image` to make a local docker image of wp-xdebug.
Otherwise, you'll end up with a version imported from docker hub which doesn't work.
