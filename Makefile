install: ## Download all the deps
	wget https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-oss-7.8.0-linux-x86_64.tar.gz -P bin -nc
	tar --directory bin/ -xzf bin/elasticsearch-oss-7.8.0-linux-x86_64.tar.gz
	cp synonyms/* bin/elasticsearch-7.8.0/config/
	cd tools/ && composer install

start: ## Start Elasticsearch
	./bin/elasticsearch-7.8.0/bin/elasticsearch -d
	echo "Waiting for ES to be up and running"; sleep 3; timeout 3m bash -c 'until curl -XGET http://127.0.0.1:9200; do sleep 3; done';

stop: ## Stop Elasticsearch
	pkill -f 'org.elasticsearch.bootstrap.Elasticsearch'

test: ## Run the tests
	./tools/vendor/bin/phpunit ./tools/tests

.PHONY: help
.DEFAULT_GOAL := help

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-16s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort
