build:
	docker build -t jmoati/php-ipmitool-monitor .

run: build
	docker run -it --rm --env-file=.env jmoati/php-ipmitool-monitor
