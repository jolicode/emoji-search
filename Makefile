TARGET?=7.8.1

# Handle new URL's:
# https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-6.8.23.tar.gz
# https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-7.8.1-linux-x86_64.tar.gz
# https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-7.11.1-linux-x86_64.tar.gz

ifeq (6.8.23, ${TARGET})
  TARGET_DOWNLOAD=${TARGET}
else
  TARGET_DOWNLOAD=${TARGET}-linux-x86_64
endif

install: ## Download all the deps
	wget https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-${TARGET_DOWNLOAD}.tar.gz -P bin -nc -nv
	tar --directory bin/ -xzf bin/elasticsearch-${TARGET_DOWNLOAD}.tar.gz
	cp synonyms/* bin/elasticsearch-${TARGET}/config/
	./bin/elasticsearch-${TARGET}/bin/elasticsearch-plugin install analysis-icu
	cd tools/ && composer install

start: ## Start Elasticsearch
	cp synonyms/* bin/elasticsearch-${TARGET}/config/
	./bin/elasticsearch-${TARGET}/bin/elasticsearch -d
	echo "Waiting for ES to be up and running"; sleep 3; timeout 3m bash -c 'until curl -XGET http://127.0.0.1:9200; do sleep 3; done';

stop: ## Stop Elasticsearch
	-pkill -f 'org.elasticsearch.bootstrap.Elasticsearch'

test: ## Run the tests
	./tools/vendor/bin/phpunit ./tools/tests

rebuild_restart: stop ## Stop, rebuild and restart
	./bin/elasticsearch-${TARGET}/bin/elasticsearch -d
	echo "Waiting for ES to be up and running"; sleep 3; timeout 3m bash -c 'until curl -XGET http://127.0.0.1:9200; do sleep 3; done';
	php ./tools/build-released.php
	cp synonyms/* bin/elasticsearch-${TARGET}/config/

.PHONY: help
.DEFAULT_GOAL := help

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-16s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort
