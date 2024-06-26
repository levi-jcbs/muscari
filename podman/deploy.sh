#!/bin/bash

function echo_and_run() {
    echo ""
    echo -e "\033[1m$1\033[0m"
    $1
}

systemd_service_exists() {
    local n=$1
    if [[ $(systemctl --user list-units --all -t service --full --no-legend "$n.service" | sed 's/^\s*//g' | cut -f1 -d' ') == $n.service ]]; then
        return 0
    else
        return 1
    fi
}

number_regex='^[0-9]+$'
pwd=$(pwd)

if !(( [ "$1" == "start" ] || [ "$1" == "autostart" ] || [ "$1" == "create" ] ) && ( [ "$2" == "dev" ] || [ "$2" == "production" ] ) && ( [ "$3" == "" ] || [[ "$3" =~ $number_regex ]] ) && [ -f ".projectroot_muscari" ] ); then
    echo "    Usage: bash podman/deploy.sh create|start|autostart production|dev [port]"
    exit
fi

if [ "$3" != "" ]; then
    port=$3
else
    port=22125
fi

if systemd_service_exists pod-muscari; then
    echo_and_run "systemctl --user disable --now pod-muscari.service"
fi

echo_and_run "podman pod create --replace --publish $port:80 muscari"

echo_and_run "podman create --replace --pod muscari --volume muscari-mysql:/var/lib/mysql/:Z --name muscari-database muscari-database:latest"

if [ "$2" == "dev" ]; then
    echo_and_run "podman create --replace --pod muscari --volume $pwd/application/:/var/www/muscari/:Z --name muscari-webserver muscari-webserver:latest"
else
    echo_and_run "podman create --replace --pod muscari --name muscari-webserver muscari-webserver:latest"
fi

if [ "$1" == "start" ]; then
    echo_and_run "podman pod start muscari"
elif [ "$1" == "autostart" ]; then
    echo -e "\n\033[1mWorkdir: /podman/systemd/\033[0m"
    cd podman/systemd/

    echo_and_run "podman generate systemd --files --new --name muscari"
    echo_and_run "cp container-muscari-database.service $HOME/.config/systemd/user/"
    echo_and_run "cp container-muscari-webserver.service $HOME/.config/systemd/user/"
    echo_and_run "cp pod-muscari.service $HOME/.config/systemd/user/"
    echo_and_run "systemctl --user daemon-reload"
    echo_and_run "systemctl --user enable --now pod-muscari.service"

    cd ../../
fi
